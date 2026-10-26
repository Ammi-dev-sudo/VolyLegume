<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductionUnitPhotoController extends Controller
{
    use UsesFarmContext;

    public function store(Request $request, ProductionUnit $productionUnit): RedirectResponse
    {
        Gate::authorize('view', $productionUnit);
        Gate::authorize('create', ProductionUnitPhoto::class);

        $farmId = $this->farmId();

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
            'taken_at' => ['nullable', 'date'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')
                ->where('farm_id', $farmId)
                ->where('production_unit_id', $productionUnit->id)],
            'activity_id' => ['nullable', 'integer', Rule::exists('activities', 'id')->where('farm_id', $farmId)],
        ]);

        $path = $request->file('photo')->store('production-unit-photos', 'public');

        ProductionUnitPhoto::create([
            'farm_id' => $this->farmId(),
            'production_unit_id' => $productionUnit->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'activity_id' => $validated['activity_id'] ?? null,
            'photo_path' => $path,
            'caption' => $validated['caption'] ?? null,
            'taken_at' => $validated['taken_at'] ?? now(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Photo ajoutée à la parcelle.');
    }

    public function destroy(ProductionUnitPhoto $productionUnitPhoto): RedirectResponse
    {
        Gate::authorize('delete', $productionUnitPhoto);

        if ($productionUnitPhoto->photo_path && Storage::disk('public')->exists($productionUnitPhoto->photo_path)) {
            Storage::disk('public')->delete($productionUnitPhoto->photo_path);
        }

        $productionUnitPhoto->delete();

        return back()->with('status', 'Photo supprimée.');
    }
}
