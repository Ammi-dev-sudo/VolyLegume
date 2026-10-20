<?php

namespace App\Http\Controllers;

use App\Exceptions\HarvestTooEarlyException;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Harvest;
use App\Services\HarvestService;
use App\Services\TreatmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HarvestController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $harvests = Harvest::query()
            ->forFarm($this->farmId())
            ->with(['campaign.crop', 'recordedBy'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('harvested_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('harvested_at', '<=', $request->date('to')))
            ->orderByDesc('harvested_at')
            ->paginate(15)
            ->withQueryString();

        return view('harvests.index', [
            'harvests' => $harvests,
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
        ]);
    }

    public function create(Request $request, TreatmentService $treatments): View
    {
        $selectedCampaign = $request->integer('campaign_id') ?: null;
        $campaign = $selectedCampaign !== null
            ? Campaign::query()->forFarm($this->farmId())->with('crop')->find($selectedCampaign)
            : null;

        return view('harvests.create', [
            'campaigns' => Campaign::query()->forFarm($this->farmId())->with('crop')->orderByDesc('start_date')->get(),
            'selectedCampaign' => $selectedCampaign,
            'minimumHarvestAt' => $campaign !== null ? $treatments->minimumHarvestAt($campaign, now()) : null,
        ]);
    }

    public function store(Request $request, HarvestService $service): RedirectResponse
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'campaign_id' => ['required', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'harvested_at' => ['required', 'date', 'before_or_equal:now'],
            'quality' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $campaign = Campaign::query()->forFarm($farmId)->with('crop')->findOrFail($validated['campaign_id']);

        try {
            $service->record($campaign, $request->user(), $validated);
        } catch (HarvestTooEarlyException $exception) {
            return back()->withInput()->withErrors(['harvested_at' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Récolte enregistrée.');
    }

    public function show(Harvest $harvest): View
    {
        $harvest->load(['campaign.crop', 'recordedBy', 'revenues']);

        return view('harvests.show', compact('harvest'));
    }

    public function destroy(Harvest $harvest): RedirectResponse
    {
        if ($harvest->revenues()->exists()) {
            return back()->withErrors([
                'harvest' => 'Cette récolte est liée à des recettes ; supprimez d’abord ces recettes.',
            ]);
        }

        $campaign = $harvest->campaign;
        $harvest->delete();

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Récolte supprimée.');
    }
}
