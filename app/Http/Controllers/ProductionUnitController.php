<?php

namespace App\Http\Controllers;

use App\Enums\ProductionUnitStatus;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\ProductionUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionUnitController extends Controller
{
    use UsesFarmContext;

    public function index(): View
    {
        $units = ProductionUnit::query()
            ->forFarm($this->farmId())
            ->orderBy('code')
            ->paginate(15);

        return view('production-units.index', compact('units'));
    }

    public function create(): View
    {
        Gate::authorize('manage-referentials');

        return view('production-units.create', [
            'productionUnit' => new ProductionUnit(['status' => ProductionUnitStatus::Available]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        $farmId = $this->farmId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('production_units', 'code')->where('farm_id', $farmId)],
            'name' => ['required', 'string', 'max:120'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
            'location' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProductionUnitStatus::class)],
        ]);

        ProductionUnit::create($validated + [
            'farm_id' => $farmId,
            'type' => 'plot',
        ]);

        return redirect()->route('production-units.index')
            ->with('status', 'Parcelle créée.');
    }

    public function edit(ProductionUnit $productionUnit): View
    {
        Gate::authorize('manage-referentials');

        return view('production-units.edit', compact('productionUnit'));
    }

    public function update(Request $request, ProductionUnit $productionUnit): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('production_units', 'code')
                ->where('farm_id', $this->farmId())
                ->ignore($productionUnit->id)],
            'name' => ['required', 'string', 'max:120'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
            'location' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProductionUnitStatus::class)],
        ]);

        $productionUnit->update($validated);

        return redirect()->route('production-units.index')
            ->with('status', 'Parcelle mise à jour.');
    }

    public function destroy(ProductionUnit $productionUnit): RedirectResponse
    {
        Gate::authorize('manage-referentials');

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
