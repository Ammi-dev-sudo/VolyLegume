<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Support\DashboardCache;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    use UsesFarmContext;

    public function store(StoreActivityRequest $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $farmId = $this->farmId();
        $attributes = $request->toActivityAttributes();
        $clientReference = $attributes['client_reference'] ?? null;

        $activity = $clientReference !== null
            ? Activity::query()->forFarm($farmId)->where('client_reference', $clientReference)->first()
            : null;

        if ($activity === null) {
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
            }
        }

        DashboardCache::forget($this->farm());

        return (new ActivityResource($activity->load('campaign')))
            ->response()
            ->setStatusCode(201);
    }
}
