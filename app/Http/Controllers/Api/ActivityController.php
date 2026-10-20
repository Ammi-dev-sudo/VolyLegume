<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    use UsesFarmContext;

    public function store(StoreActivityRequest $request): JsonResponse
    {
        $activity = Activity::create($request->toActivityAttributes() + [
            'farm_id' => $this->farmId(),
            'created_by' => $request->user()->id,
        ]);

        return (new ActivityResource($activity->load('campaign')))
            ->response()
            ->setStatusCode(201);
    }
}
