<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): AnonymousResourceCollection
    {
        $campaigns = Campaign::query()
            ->forFarm($this->farmId())
            ->with(['productionUnit', 'crop'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('crop_id'), fn ($query) => $query->where('crop_id', $request->integer('crop_id')))
            ->when($request->filled('production_unit_id'), fn ($query) => $query->where('production_unit_id', $request->integer('production_unit_id')))
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return CampaignResource::collection($campaigns);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        return new CampaignResource(
            $campaign->load(['productionUnit', 'crop']),
        );
    }
}
