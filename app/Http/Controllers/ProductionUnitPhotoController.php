<?php

namespace App\Http\Controllers;

use App\Enums\CropStage;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitPhoto;
use App\Services\PhotoStorage;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProductionUnitPhotoController extends Controller
{
    use UsesFarmContext;

    public function store(Request $request, ProductionUnit $productionUnit, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('view', $productionUnit);
        Gate::authorize('create', ProductionUnitPhoto::class);

        $farmId = $this->farmId();

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
            'taken_at' => ['nullable', 'date'],
            'growth_stage' => ['nullable', Rule::enum(CropStage::class)],
            'observation_type' => ['nullable', 'string', 'max:30'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')
                ->where('farm_id', $farmId)
                ->where('production_unit_id', $productionUnit->id)],
            'activity_id' => ['nullable', 'integer', Rule::exists('activities', 'id')->where('farm_id', $farmId)],
        ]);

        $path = $photos->store($request->file('photo'), 'production-unit-photos');

        ProductionUnitPhoto::create([
            'farm_id' => $farmId,
            'production_unit_id' => $productionUnit->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'activity_id' => $validated['activity_id'] ?? null,
            'photo_path' => $path,
            'caption' => $validated['caption'] ?? null,
            'growth_stage' => $validated['growth_stage'] ?? null,
            'observation_type' => $validated['observation_type'] ?? null,
            'taken_at' => $validated['taken_at'] ?? now(),
            'uploaded_by' => $request->user()->id,
        ]);

        DashboardCache::forget($this->farm());

        return back()->with('status', 'Photo ajoutée à la parcelle.');
    }

    public function destroy(ProductionUnitPhoto $productionUnitPhoto, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('delete', $productionUnitPhoto);

        $photos->delete($productionUnitPhoto->photo_path);
        $productionUnitPhoto->delete();

        DashboardCache::forget($this->farm());

        return back()->with('status', 'Photo supprimée.');
    }
}
