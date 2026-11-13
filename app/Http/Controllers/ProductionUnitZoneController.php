<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitZone;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionUnitZoneController extends Controller
{
    use UsesFarmContext;

    public function store(Request $request, ProductionUnit $productionUnit): RedirectResponse
    {
        Gate::authorize('update', $productionUnit);

        $validated = $this->validated($request, $productionUnit);

        DB::transaction(function () use ($validated, $productionUnit): void {
            $used = (float) $productionUnit->zones()->lockForUpdate()->sum('area_m2');

            if ($used + (float) $validated['area_m2'] > (float) $productionUnit->area_m2) {
                throw ValidationException::withMessages([
                    'area_m2' => 'La somme des zones ne peut pas dépasser la superficie de la parcelle.',
                ]);
            }

            ProductionUnitZone::create($validated + [
                'farm_id' => $productionUnit->farm_id,
                'production_unit_id' => $productionUnit->id,
            ]);
        });

        DashboardCache::forget($this->farm());

        return back()->with('status', 'Zone de culture ajoutée.');
    }

    public function update(Request $request, ProductionUnitZone $zone): RedirectResponse
    {
        Gate::authorize('update', $zone);

        $unit = $zone->productionUnit;
        $validated = $this->validated($request, $unit, $zone);

        $otherZonesArea = (float) $unit->zones()->whereKeyNot($zone->id)->sum('area_m2');

        if ($otherZonesArea + (float) $validated['area_m2'] > (float) $unit->area_m2) {
            throw ValidationException::withMessages([
                'area_m2' => 'La somme des zones ne peut pas dépasser la superficie de la parcelle.',
            ]);
        }

        $zone->update($validated);

        DashboardCache::forget($this->farm());

        return back()->with('status', 'Zone de culture mise à jour.');
    }

    public function destroy(ProductionUnitZone $zone): RedirectResponse
    {
        Gate::authorize('delete', $zone);

        if ($zone->campaigns()->exists()) {
            return back()->withErrors([
                'zone' => 'Cette zone est rattachée à des campagnes ; désactivez-la plutôt que de la supprimer.',
            ]);
        }

        $zone->delete();

        DashboardCache::forget($this->farm());

        return back()->with('status', 'Zone de culture supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ProductionUnit $unit, ?ProductionUnitZone $zone = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('production_unit_zones', 'code')
                ->where('production_unit_id', $unit->id)
                ->ignore($zone?->id)],
            'name' => ['required', 'string', 'max:120'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
            'soil_type' => ['nullable', 'string', 'max:60'],
            'irrigation_type' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : $zone === null;

        return $validated;
    }
}
