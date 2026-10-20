<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\ReferenceType;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\ProductionUnit;
use App\Services\CropCalendarService;
use App\Services\DashboardService;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CampaignController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $campaigns = Campaign::query()
            ->forFarm($this->farmId())
            ->with(['productionUnit', 'crop'])
            ->withCount(['activities', 'treatments', 'harvests'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('campaigns.index', [
            'campaigns' => $campaigns,
            'statuses' => CampaignStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-referentials');

        return view('campaigns.create', $this->formData() + [
            'campaign' => new Campaign([
                'reference_type' => ReferenceType::Sowing,
                'status' => CampaignStatus::Planned,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        Campaign::create($this->validated($request) + ['farm_id' => $this->farmId()]);

        return redirect()->route('campaigns.index')->with('status', 'Campagne créée.');
    }

    public function show(Campaign $campaign, CropCalendarService $calendar, DashboardService $dashboard): View
    {
        $campaign->load(['productionUnit', 'crop']);

        return view('campaigns.show', [
            'campaign' => $campaign,
            'activities' => $campaign->activities()->with(['assignedTo', 'performedBy'])->orderBy('planned_date')->get(),
            'treatments' => $campaign->treatments()->with('input')->orderByDesc('applied_at')->get(),
            'harvests' => $campaign->harvests()->orderByDesc('harvested_at')->get(),
            'calendarPreview' => $calendar->preview($campaign),
            'metrics' => $dashboard->forFarm($this->farm(), ['campaign_id' => $campaign->id]),
            'latestHarvestBound' => $campaign->treatments()->max('minimum_harvest_at'),
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        Gate::authorize('manage-referentials');

        return view('campaigns.edit', $this->formData() + ['campaign' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        $campaign->update($this->validated($request, $campaign));

        DashboardCache::forget($campaign->farm);

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campagne mise à jour.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        if ($campaign->harvests()->exists() || $campaign->treatments()->exists() || $campaign->expenses()->exists() || $campaign->revenues()->exists()) {
            return back()->withErrors([
                'campaign' => 'Cette campagne porte des écritures métier ; annulez-la plutôt que de la supprimer.',
            ]);
        }

        $campaign->activities()->delete();
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('status', 'Campagne supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'productionUnits' => ProductionUnit::query()->forFarm($this->farmId())->orderBy('code')->get(),
            'crops' => Crop::query()->forFarm($this->farmId())->where('is_active', true)->orderBy('name')->get(),
            'referenceTypes' => ReferenceType::cases(),
            'statuses' => CampaignStatus::cases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Campaign $campaign = null): array
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'production_unit_id' => ['required', Rule::exists('production_units', 'id')->where('farm_id', $farmId)],
            'crop_id' => ['required', Rule::exists('crops', 'id')->where('farm_id', $farmId)],
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'reference_type' => ['required', Rule::enum(ReferenceType::class)],
            'expected_end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'cultivated_area_m2' => ['required', 'numeric', 'gt:0'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $parcel = ProductionUnit::query()->forFarm($farmId)->findOrFail($validated['production_unit_id']);

        if ((float) $validated['cultivated_area_m2'] > (float) $parcel->area_m2) {
            throw ValidationException::withMessages([
                'cultivated_area_m2' => 'La superficie cultivée ne peut pas dépasser celle de la parcelle.',
            ]);
        }

        return $validated;
    }
}
