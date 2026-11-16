<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\ProductionUnitStatus;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Activity;
use App\Models\CropIssue;
use App\Models\Harvest;
use App\Models\ProductionUnit;
use App\Rules\ValidParcelBoundary;
use App\Services\PhotoStorage;
use App\Support\ParcelBoundary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionUnitController extends Controller
{
    use UsesFarmContext;

    public function index(): View
    {
        $units = ProductionUnit::query()
            ->forFarm($this->farmId())
            ->withCount('photos')
            ->orderBy('code')
            ->paginate(15);

        return view('production-units.index', compact('units'));
    }

    public function create(): View
    {
        Gate::authorize('create', ProductionUnit::class);

        return view('production-units.create', [
            'productionUnit' => new ProductionUnit(['status' => ProductionUnitStatus::Available]),
        ]);
    }

    public function store(Request $request, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('create', ProductionUnit::class);

        $farmId = $this->farmId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('production_units', 'code')->where('farm_id', $farmId)],
            'name' => ['required', 'string', 'max:120'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
            'length_m' => ['nullable', 'numeric', 'gt:0'],
            'width_m' => ['nullable', 'numeric', 'gt:0'],
            'location' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProductionUnitStatus::class)],
            'soil_type' => ['nullable', 'string', 'max:60'],
            'irrigation_type' => ['nullable', 'string', 'max:60'],
            'sun_exposure' => ['nullable', 'string', 'max:40'],
            'slope' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->assertParcelFitsFarm((float) $validated['area_m2']);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $photos->store($request->file('photo'), 'production-units');
        }

        ProductionUnit::create($validated + [
            'farm_id' => $farmId,
            'type' => 'plot',
            'photo_path' => $photoPath,
        ]);

        return redirect()->route('production-units.index')
            ->with('status', 'Parcelle créée.');
    }

    public function show(ProductionUnit $productionUnit): View
    {
        Gate::authorize('view', $productionUnit);

        $campaigns = $productionUnit->campaigns()
            ->with('crop')
            ->orderByDesc('start_date')
            ->get();

        $campaignIds = $campaigns->pluck('id')->all();

        $activities = Activity::query()
            ->forFarm($this->farmId())
            ->whereIn('campaign_id', $campaignIds)
            ->with('campaign:id,name')
            ->orderByDesc('planned_date')
            ->limit(12)
            ->get();

        $harvests = Harvest::query()
            ->forFarm($this->farmId())
            ->whereIn('campaign_id', $campaignIds)
            ->with('campaign:id,name')
            ->orderByDesc('harvested_at')
            ->limit(12)
            ->get();

        $irrigations = $productionUnit->irrigationEvents()
            ->with('campaign:id,name')
            ->orderByDesc('irrigated_at')
            ->limit(12)
            ->get();

        $zones = $productionUnit->zones()->withCount('campaigns')->get();

        $issues = CropIssue::query()
            ->forFarm($this->farmId())
            ->where('production_unit_id', $productionUnit->id)
            ->with(['campaign:id,name', 'reportedBy:id,name'])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderByDesc('observed_at')
            ->limit(10)
            ->get();

        $harvestQuery = Harvest::query()
            ->forFarm($this->farmId())
            ->whereIn('campaign_id', $campaignIds);

        $harvestUnits = (clone $harvestQuery)
            ->selectRaw('unit, SUM(quantity) as total')
            ->groupBy('unit')
            ->pluck('total', 'unit')
            ->map(fn ($total): float => round((float) $total, 2))
            ->sortKeys()
            ->all();

        $stats = [
            'campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->filter(
                fn ($campaign): bool => in_array($campaign->status, [CampaignStatus::Planned, CampaignStatus::Ongoing], true),
            )->count(),
            'harvest_quantity' => (float) ($harvestUnits['kg'] ?? 0.0),
            'harvest_units' => $harvestUnits,
            'irrigation_volume' => (float) $productionUnit->irrigationEvents()->sum('volume_m3'),
        ];

        return view('production-units.show', compact(
            'productionUnit',
            'campaigns',
            'activities',
            'harvests',
            'irrigations',
            'zones',
            'issues',
            'stats',
        ));
    }

    public function edit(ProductionUnit $productionUnit): View
    {
        Gate::authorize('update', $productionUnit);

        return view('production-units.edit', compact('productionUnit'));
    }

    public function update(Request $request, ProductionUnit $productionUnit, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('update', $productionUnit);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('production_units', 'code')
                ->where('farm_id', $this->farmId())
                ->ignore($productionUnit->id)],
            'name' => ['required', 'string', 'max:120'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
            'length_m' => ['nullable', 'numeric', 'gt:0'],
            'width_m' => ['nullable', 'numeric', 'gt:0'],
            'location' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProductionUnitStatus::class)],
            'soil_type' => ['nullable', 'string', 'max:60'],
            'irrigation_type' => ['nullable', 'string', 'max:60'],
            'sun_exposure' => ['nullable', 'string', 'max:40'],
            'slope' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $this->assertParcelFitsFarm((float) $validated['area_m2'], $productionUnit);
        $this->assertParcelCoversZones($productionUnit, (float) $validated['area_m2']);

        if ($request->boolean('remove_photo') && $productionUnit->photo_path) {
            $photos->delete($productionUnit->photo_path);
            $validated['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            $photos->delete($productionUnit->photo_path);
            $validated['photo_path'] = $photos->store($request->file('photo'), 'production-units');
        }

        unset($validated['photo'], $validated['remove_photo']);

        $productionUnit->update($validated);

        return redirect()->route('production-units.index')
            ->with('status', 'Parcelle mise à jour.');
    }

    public function editBoundary(ProductionUnit $productionUnit): View
    {
        Gate::authorize('update', $productionUnit);

        $boundary = $productionUnit->boundaryGeoJson();
        $points = ParcelBoundary::points($boundary);
        $centroid = ParcelBoundary::centroid($points);
        $farm = $this->farm();

        $center = $centroid
            ?? ($productionUnit->latitude !== null && $productionUnit->longitude !== null
                ? ['lat' => (float) $productionUnit->latitude, 'lng' => (float) $productionUnit->longitude]
                : ($farm->latitude !== null && $farm->longitude !== null
                    ? ['lat' => (float) $farm->latitude, 'lng' => (float) $farm->longitude]
                    : null));

        return view('production-units.boundary', [
            'productionUnit' => $productionUnit,
            'boundary' => $boundary,
            'points' => $points,
            'areaM2' => $points === [] ? null : round(ParcelBoundary::areaM2($points), 2),
            'perimeterM' => $points === [] ? null : round(ParcelBoundary::perimeterM($points), 2),
            'center' => $center,
            'zoom' => $center === null ? 2 : ($points === [] ? 17 : 18),
        ]);
    }

    public function updateBoundary(Request $request, ProductionUnit $productionUnit): RedirectResponse
    {
        Gate::authorize('update', $productionUnit);

        $validated = $request->validate([
            'boundary' => ['nullable', 'required_without:remove_boundary', new ValidParcelBoundary],
            'remove_boundary' => ['nullable', 'boolean'],
            'update_area' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_boundary')) {
            $productionUnit->update(['boundary' => null]);

            return redirect()->route('production-units.boundary.edit', $productionUnit)
                ->with('status', 'Contour supprimé.');
        }

        $boundary = ParcelBoundary::normalize($validated['boundary'] ?? null);

        if ($boundary === null) {
            return back()->withErrors([
                'boundary' => 'Le contour enregistré est invalide.',
            ])->withInput();
        }

        $points = ParcelBoundary::points($boundary);
        $area = ParcelBoundary::areaM2($points);
        $attributes = ['boundary' => $boundary];

        if ($request->boolean('update_area')) {
            $computedArea = max(0.01, round($area, 2));
            $this->assertParcelFitsFarm($computedArea, $productionUnit);
            $this->assertParcelCoversZones($productionUnit, $computedArea);
            $attributes['area_m2'] = $computedArea;
        }

        if ($productionUnit->latitude === null || $productionUnit->longitude === null) {
            $centroid = ParcelBoundary::centroid($points);

            if ($centroid !== null) {
                $attributes['latitude'] = round($centroid['lat'], 7);
                $attributes['longitude'] = round($centroid['lng'], 7);
            }
        }

        $productionUnit->update($attributes);

        return redirect()->route('production-units.boundary.edit', $productionUnit)
            ->with('status', 'Contour enregistré.');
    }

    private function assertParcelFitsFarm(float $area, ?ProductionUnit $productionUnit = null): void
    {
        if ($productionUnit !== null && $area <= (float) $productionUnit->area_m2) {
            return;
        }

        $used = (float) ProductionUnit::query()
            ->forFarm($this->farmId())
            ->when($productionUnit !== null, fn ($query) => $query->whereKeyNot($productionUnit->getKey()))
            ->sum('area_m2');

        $limit = (float) $this->farm()->area_m2;

        if ($used + $area > $limit + 0.009) {
            throw ValidationException::withMessages([
                'area_m2' => 'La somme des parcelles ne peut pas dépasser la superficie de l’exploitation ('
                    .number_format($limit, 2, ',', ' ').' m²).',
            ]);
        }
    }

    private function assertParcelCoversZones(ProductionUnit $productionUnit, float $area): void
    {
        if ($area >= (float) $productionUnit->area_m2) {
            return;
        }

        $zonesArea = (float) $productionUnit->zones()->sum('area_m2');

        if ($area + 0.009 < $zonesArea) {
            throw ValidationException::withMessages([
                'area_m2' => 'La superficie de la parcelle ne peut pas être inférieure à la somme de ses zones ('
                    .number_format($zonesArea, 2, ',', ' ').' m²).',
            ]);
        }
    }

    public function destroy(ProductionUnit $productionUnit): RedirectResponse
    {
        Gate::authorize('delete', $productionUnit);

        if ($productionUnit->campaigns()->exists()) {
            return back()->withErrors([
                'production_unit' => 'Cette parcelle est référencée par des campagnes ; désactivez-la plutôt que de la supprimer.',
            ]);
        }

        $productionUnit->delete();

        return redirect()->route('production-units.index')
            ->with('status', 'Parcelle supprimée.');
    }
}
