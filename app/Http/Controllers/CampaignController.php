<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\ReferenceType;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitZone;
use App\Services\CropCalendarService;
use App\Services\CropProgressService;
use App\Services\DashboardService;
use App\Services\IrrigationService;
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
            ->with(['productionUnit', 'zone', 'crop'])
            ->withCount(['activities', 'treatments', 'harvests', 'issues'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('campaigns.index', [
            'campaigns' => $campaigns,
            'statuses' => collect(CampaignStatus::cases()),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Campaign::class);

        return view('campaigns.create', $this->formData() + [
            'campaign' => new Campaign([
                'reference_type' => ReferenceType::Sowing,
                'status' => CampaignStatus::Planned,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Campaign::class);

        Campaign::create($this->validated($request) + ['farm_id' => $this->farmId()]);

        return redirect()->route('campaigns.index')->with('status', 'Campagne créée.');
    }

    public function show(Campaign $campaign, CropCalendarService $calendar, DashboardService $dashboard, IrrigationService $irrigation, CropProgressService $progress): View
    {
        $campaign->load(['productionUnit', 'zone', 'crop', 'stageEvents.recordedBy']);

        return view('campaigns.show', [
            'campaign' => $campaign,
            'activities' => $campaign->activities()->with(['assignedTo', 'performedBy'])->orderBy('planned_date')->get(),
            'treatments' => $campaign->treatments()->with('input')->orderByDesc('applied_at')->get(),
            'harvests' => $campaign->harvests()->orderByDesc('harvested_at')->get(),
            'calendarPreview' => $calendar->preview($campaign),
            'metrics' => $dashboard->forFarm($this->farm(), ['campaign_id' => $campaign->id]),
            'latestHarvestBound' => $campaign->treatments()->max('minimum_harvest_at'),
            'irrigationSummary' => $irrigation->summaryForCampaign($campaign),
            'progress' => $progress->forCampaign($campaign),
            'issues' => $campaign->issues()->with('reportedBy:id,name')->get(),
            'photoCount' => $campaign->photos()->count(),
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        Gate::authorize('update', $campaign);

        return view('campaigns.edit', $this->formData() + ['campaign' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('update', $campaign);

        $campaign->update($this->validated($request, $campaign));

        DashboardCache::forget($campaign->farm);

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campagne mise à jour.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('delete', $campaign);

        if ($campaign->harvests()->exists()
            || $campaign->treatments()->exists()
            || $campaign->expenses()->exists()
            || $campaign->revenues()->exists()
            || $campaign->issues()->exists()
            || $campaign->stageEvents()->exists()) {
            return back()->withErrors([
                'campaign' => 'Cette campagne porte des écritures de suivi ; annulez-la plutôt que de la supprimer.',
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
            'zones' => ProductionUnitZone::query()->forFarm($this->farmId())->where('is_active', true)->with('productionUnit:id,code,name')->orderBy('code')->get(),
            'crops' => Crop::query()->forFarm($this->farmId())->where('is_active', true)->orderBy('name')->get(),
            'referenceTypes' => collect(ReferenceType::cases()),
            'statuses' => collect(CampaignStatus::cases()),
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
            'production_unit_zone_id' => ['nullable', Rule::exists('production_unit_zones', 'id')->where('farm_id', $farmId)],
            'crop_id' => ['required', Rule::exists('crops', 'id')->where('farm_id', $farmId)],
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'reference_type' => ['required', Rule::enum(ReferenceType::class)],
            'expected_end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'cultivated_area_m2' => ['required', 'numeric', 'gt:0'],
            'planting_density' => ['nullable', 'numeric', 'gte:0'],
            'expected_yield_kg' => ['nullable', 'numeric', 'gte:0'],
            'actual_yield_kg' => ['nullable', 'numeric', 'gte:0'],
            'planned_budget' => ['nullable', 'numeric', 'gte:0'],
            'water_quota_m3' => ['nullable', 'numeric', 'gte:0'],
            'water_alert_threshold_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'water_notes' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $parcel = ProductionUnit::query()->forFarm($farmId)->findOrFail($validated['production_unit_id']);

        $limitArea = (float) $parcel->area_m2;

        if (! empty($validated['production_unit_zone_id'])) {
            $zone = ProductionUnitZone::query()->forFarm($farmId)->findOrFail($validated['production_unit_zone_id']);

            if ((int) $zone->production_unit_id !== (int) $parcel->id) {
                throw ValidationException::withMessages([
                    'production_unit_zone_id' => 'La zone sélectionnée n’appartient pas à cette parcelle.',
                ]);
            }

            $limitArea = (float) $zone->area_m2;
        }

        if ((float) $validated['cultivated_area_m2'] > $limitArea) {
            throw ValidationException::withMessages([
                'cultivated_area_m2' => 'La superficie cultivée ne peut pas dépasser la surface disponible (parcelle ou zone).',
            ]);
        }

        return $validated;
    }
}
