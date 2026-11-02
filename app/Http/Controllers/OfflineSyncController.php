<?php

namespace App\Http\Controllers;

use App\Exceptions\HarvestTooEarlyException;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\StoreHarvestRequest;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Harvest;
use App\Services\HarvestService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Réception des saisies mises en file hors connexion.
 *
 * Chaque envoi porte une référence cliente : un renvoi du même formulaire met
 * à jour la réponse sans créer de doublon.
 */
class OfflineSyncController extends Controller
{
    use UsesFarmContext;

    public function activity(StoreActivityRequest $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        if (! $request->filled('client_reference')) {
            return $this->missingReference();
        }

        $farmId = $this->farmId();
        $attributes = $request->toActivityAttributes();
        $clientReference = $attributes['client_reference'];

        $existing = Activity::query()
            ->forFarm($farmId)
            ->where('client_reference', $clientReference)
            ->first();

        if ($existing !== null) {
            return $this->activityResponse($existing, 'existing');
        }

        try {
            $activity = Activity::create($attributes + [
                'farm_id' => $farmId,
                'created_by' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            $activity = Activity::query()
                ->forFarm($farmId)
                ->where('client_reference', $clientReference)
                ->firstOrFail();

            return $this->activityResponse($activity, 'existing');
        }

        return $this->activityResponse($activity, 'created');
    }

    public function harvest(StoreHarvestRequest $request, HarvestService $service): JsonResponse
    {
        Gate::authorize('create', Harvest::class);

        if (! $request->filled('client_reference')) {
            return $this->missingReference();
        }

        $farmId = $this->farmId();
        $validated = $request->toHarvestData();
        $clientReference = $validated['client_reference'];

        $existing = Harvest::query()
            ->forFarm($farmId)
            ->where('client_reference', $clientReference)
            ->first();

        if ($existing !== null) {
            return $this->harvestResponse($existing, 'existing');
        }

        $campaign = Campaign::query()->forFarm($farmId)->with('crop')->findOrFail($validated['campaign_id']);

        try {
            $harvest = $service->record($campaign, $request->user(), $validated);
        } catch (HarvestTooEarlyException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['harvested_at' => [$exception->getMessage()]],
            ], 422);
        } catch (UniqueConstraintViolationException) {
            $harvest = Harvest::query()
                ->forFarm($farmId)
                ->where('client_reference', $clientReference)
                ->firstOrFail();

            return $this->harvestResponse($harvest, 'existing');
        }

        return $this->harvestResponse($harvest, 'created');
    }

    private function activityResponse(Activity $activity, string $status): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'id' => $activity->id,
            'redirect' => route('activities.show', $activity),
        ]);
    }

    private function harvestResponse(Harvest $harvest, string $status): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'id' => $harvest->id,
            'redirect' => route('campaigns.show', $harvest->campaign_id),
        ]);
    }

    private function missingReference(): JsonResponse
    {
        return response()->json([
            'message' => 'La référence de synchronisation est obligatoire.',
            'errors' => ['client_reference' => ['La référence de synchronisation est obligatoire.']],
        ], 422);
    }
}
