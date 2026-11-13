<?php

namespace App\Http\Controllers;

use App\Enums\CropStage;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\CampaignStageEvent;
use App\Services\PhotoStorage;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CampaignStageController extends Controller
{
    use UsesFarmContext;

    public function store(Request $request, Campaign $campaign, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('view', $campaign);
        Gate::authorize('create', CampaignStageEvent::class);

        $validated = $request->validate([
            'stage' => ['required', Rule::enum(CropStage::class)],
            'recorded_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($campaign, $validated, $request, $photos): void {
            $existing = CampaignStageEvent::query()
                ->where('farm_id', $campaign->farm_id)
                ->where('campaign_id', $campaign->id)
                ->where('stage', $validated['stage'])
                ->first();

            $oldPath = $existing?->photo_path;
            $path = $oldPath;

            if ($request->hasFile('photo')) {
                $path = $photos->store($request->file('photo'), 'campaign-photos');
            }

            CampaignStageEvent::updateOrCreate(
                [
                    'farm_id' => $campaign->farm_id,
                    'campaign_id' => $campaign->id,
                    'stage' => $validated['stage'],
                ],
                [
                    'recorded_at' => $validated['recorded_at'],
                    'notes' => $validated['notes'] ?? null,
                    'photo_path' => $path,
                    'recorded_by' => $request->user()->id,
                ],
            );

            if ($request->hasFile('photo')) {
                if ($oldPath !== null && $oldPath !== $path) {
                    $campaign->photos()->where('photo_path', $oldPath)->delete();
                    $photos->delete($oldPath);
                }

                $campaign->photos()->create([
                    'farm_id' => $campaign->farm_id,
                    'production_unit_id' => $campaign->production_unit_id,
                    'photo_path' => $path,
                    'caption' => $validated['caption'] ?? $validated['stage'],
                    'growth_stage' => $validated['stage'],
                    'observation_type' => 'stage',
                    'taken_at' => $validated['recorded_at'],
                    'uploaded_by' => $request->user()->id,
                ]);
            }
        });

        DashboardCache::forget($campaign->farm);

        return redirect()->route('campaigns.show', $campaign)
            ->with('status', 'Étape culturale enregistrée.');
    }

    public function destroy(CampaignStageEvent $stage, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('delete', $stage);

        $photos->delete($stage->photo_path);
        $campaign = $stage->campaign;
        $stage->delete();

        DashboardCache::forget($campaign->farm);

        return back()->with('status', 'Étape culturale supprimée.');
    }
}
