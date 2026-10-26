<?php

namespace App\Http\Controllers;

use App\Enums\ProductionUnitStatus;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\ProductionUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): RedirectResponse
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
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('production-units', 'public');
        }

        ProductionUnit::create($validated + [
            'farm_id' => $farmId,
            'type' => 'plot',
            'photo_path' => $photoPath,
        ]);

        return redirect()->route('production-units.index')
            ->with('status', 'Parcelle créée.');
    }

    public function edit(ProductionUnit $productionUnit): View
    {
        Gate::authorize('update', $productionUnit);

        return view('production-units.edit', compact('productionUnit'));
    }

    public function update(Request $request, ProductionUnit $productionUnit): RedirectResponse
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
            'photo' => ['nullable', 'image', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_photo') && $productionUnit->photo_path) {
            if (Storage::disk('public')->exists($productionUnit->photo_path)) {
                Storage::disk('public')->delete($productionUnit->photo_path);
            }
            $validated['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($productionUnit->photo_path && Storage::disk('public')->exists($productionUnit->photo_path)) {
                Storage::disk('public')->delete($productionUnit->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('production-units', 'public');
        }

        unset($validated['photo'], $validated['remove_photo']);

        $productionUnit->update($validated);

        return redirect()->route('production-units.index')
            ->with('status', 'Parcelle mise à jour.');
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
