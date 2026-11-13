<?php

namespace App\Http\Controllers;

use App\Enums\CropStage;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\ProductionUnitPhoto;
use App\Services\PhotoStorage;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignPhotoController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request, Campaign $campaign): View
    {
        Gate::authorize('view', $campaign);

        $photos = $campaign->photos()
            ->with('uploadedBy:id,name')
            ->when($request->filled('stage'), fn ($query) => $query->where('growth_stage', $request->string('stage')))
            ->when($request->filled('type'), fn ($query) => $query->where('observation_type', $request->string('type')))
            ->get();

        return view('campaigns.photos', [
            'campaign' => $campaign->load(['crop', 'productionUnit', 'zone']),
            'photos' => $photos,
            'stages' => collect(CropStage::cases()),
            'observationTypes' => $this->observationTypes(),
            'selectedStage' => $request->string('stage')->toString(),
            'selectedType' => $request->string('type')->toString(),
        ]);
    }

    public function store(Request $request, Campaign $campaign, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('view', $campaign);
        Gate::authorize('create', ProductionUnitPhoto::class);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
            'taken_at' => ['nullable', 'date'],
            'growth_stage' => ['nullable', Rule::enum(CropStage::class)],
            'observation_type' => ['nullable', 'string', 'max:30'],
        ]);

        $path = $photos->store($request->file('photo'), 'campaign-photos');

        $campaign->photos()->create([
            'farm_id' => $campaign->farm_id,
            'production_unit_id' => $campaign->production_unit_id,
            'photo_path' => $path,
            'caption' => $validated['caption'] ?? null,
            'growth_stage' => $validated['growth_stage'] ?? null,
            'observation_type' => $validated['observation_type'] ?? 'observation',
            'taken_at' => $validated['taken_at'] ?? now(),
            'uploaded_by' => $request->user()->id,
        ]);

        DashboardCache::forget($campaign->farm);

        return back()->with('status', 'Photo ajoutée à la chronologie de la culture.');
    }

    public function compare(Request $request, Campaign $campaign): View
    {
        Gate::authorize('view', $campaign);

        $photos = $campaign->photos()->with('uploadedBy:id,name')->get();

        $before = $request->integer('before') ? $photos->firstWhere('id', $request->integer('before')) : null;
        $after = $request->integer('after') ? $photos->firstWhere('id', $request->integer('after')) : null;

        if ($before === null) {
            $before = $photos->sortBy('taken_at')->first();
        }

        if ($after === null) {
            $after = $photos->sortByDesc('taken_at')->first();
        }

        return view('campaigns.compare', [
            'campaign' => $campaign->load(['crop', 'productionUnit', 'zone']),
            'photos' => $photos,
            'before' => $before,
            'after' => $after,
        ]);
    }

    public function destroy(Campaign $campaign, ProductionUnitPhoto $photo, PhotoStorage $photos): RedirectResponse
    {
        abort_unless((int) $photo->campaign_id === (int) $campaign->id, 404);

        Gate::authorize('delete', $photo);

        $photos->delete($photo->photo_path);
        $photo->delete();

        DashboardCache::forget($campaign->farm);

        return back()->with('status', 'Photo supprimée.');
    }

    /**
     * @return array<string, string>
     */
    private function observationTypes(): array
    {
        return [
            'sowing' => 'Semis',
            'growth' => 'Croissance',
            'maintenance' => 'Entretien',
            'disease' => 'Maladie / problème',
            'treatment' => 'Après traitement',
            'before_harvest' => 'Avant récolte',
            'after_harvest' => 'Après récolte',
            'observation' => 'Observation',
            'stage' => 'Étape culturale',
        ];
    }
}
