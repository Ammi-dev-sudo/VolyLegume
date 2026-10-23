<?php

namespace App\Http\Controllers;

use App\Enums\RevenueCategory;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Harvest;
use App\Models\Revenue;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RevenueController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $revenues = Revenue::query()
            ->forFarm($this->farmId())
            ->with(['campaign.crop', 'harvest', 'recordedBy'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('revenue_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('revenue_date', '<=', $request->date('to')))
            ->orderByDesc('revenue_date')
            ->paginate(20)
            ->withQueryString();

        return view('revenues.index', [
            'revenues' => $revenues,
            'campaigns' => $this->campaigns(),
            'categories' => collect(RevenueCategory::cases()),
            'total' => (float) $revenues->sum('amount'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Revenue::class);

        return view('revenues.create', $this->formData() + [
            'revenue' => new Revenue([
                'category' => RevenueCategory::HarvestSale,
                'revenue_date' => now()->toDateString(),
                'campaign_id' => $request->integer('campaign_id') ?: null,
                'harvest_id' => $request->integer('harvest_id') ?: null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Revenue::class);

        Revenue::create($this->validated($request) + [
            'farm_id' => $this->farmId(),
            'recorded_by' => $request->user()->id,
        ]);

        DashboardCache::forget($this->farm());

        return redirect()->route('revenues.index')->with('status', 'Recette enregistrée.');
    }

    public function edit(Revenue $revenue): View
    {
        Gate::authorize('update', $revenue);

        return view('revenues.edit', $this->formData() + ['revenue' => $revenue]);
    }

    public function update(Request $request, Revenue $revenue): RedirectResponse
    {
        Gate::authorize('update', $revenue);

        $revenue->update($this->validated($request));

        DashboardCache::forget($this->farm());

        return redirect()->route('revenues.index')->with('status', 'Recette mise à jour.');
    }

    public function destroy(Revenue $revenue): RedirectResponse
    {
        Gate::authorize('delete', $revenue);

        $revenue->delete();

        DashboardCache::forget($this->farm());

        return redirect()->route('revenues.index')->with('status', 'Recette supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'campaigns' => $this->campaigns(),
            'harvests' => Harvest::query()->forFarm($this->farmId())->with('campaign.crop')->orderByDesc('harvested_at')->get(),
            'categories' => collect(RevenueCategory::cases()),
        ];
    }

    private function campaigns()
    {
        return Campaign::query()->forFarm($this->farmId())->with('crop')->orderByDesc('start_date')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'harvest_id' => ['nullable', Rule::exists('harvests', 'id')->where('farm_id', $farmId)],
            'category' => ['required', Rule::enum(RevenueCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'revenue_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if (! empty($validated['harvest_id']) && empty($validated['campaign_id'])) {
            $harvest = Harvest::query()->forFarm($farmId)->findOrFail($validated['harvest_id']);
            $validated['campaign_id'] = $harvest->campaign_id;
        }

        if (! empty($validated['harvest_id'])) {
            $harvest = Harvest::query()->forFarm($farmId)->findOrFail($validated['harvest_id']);

            if ((int) $harvest->campaign_id !== (int) $validated['campaign_id']) {
                throw ValidationException::withMessages([
                    'harvest_id' => 'La récolte sélectionnée n’appartient pas à cette campagne.',
                ]);
            }
        }

        return $validated;
    }
}
