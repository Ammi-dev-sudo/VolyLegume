<?php

namespace App\Services;

use App\Exceptions\HarvestTooEarlyException;
use App\Models\Campaign;
use App\Models\Harvest;
use App\Models\User;
use App\Support\DashboardCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enregistrement d'une récolte avec contrôle du délai avant récolte.
 */
class HarvestService
{
    public function __construct(
        private TreatmentService $treatments,
        private ProduceStockService $produceStock,
    ) {}

    /**
     * @param  array{quantity: float|int|string, harvested_at: string, unit?: string, quality?: ?string, notes?: ?string, harvest_code?: ?string, harvested_area_m2?: float|int|string|null, loss_quantity?: float|int|string|null, grade?: ?string}  $data
     *
     * @throws HarvestTooEarlyException
     */
    public function record(Campaign $campaign, User $actor, array $data): Harvest
    {
        $harvestedAt = Carbon::parse($data['harvested_at']);
        $quantity = (float) $data['quantity'];

        if ($quantity <= 0) {
            throw new InvalidArgumentException('La quantité récoltée doit être strictement positive.');
        }

        $harvest = DB::transaction(function () use ($campaign, $actor, $data, $harvestedAt, $quantity) {
            $lockedCampaign = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();

            $minimum = $this->treatments->minimumHarvestAt($lockedCampaign, $harvestedAt);

            if ($minimum !== null && $harvestedAt->lt($minimum)) {
                throw new HarvestTooEarlyException($lockedCampaign, $harvestedAt, $minimum);
            }

            $harvest = $lockedCampaign->harvests()->create([
                'farm_id' => $lockedCampaign->farm_id,
                'harvested_at' => $harvestedAt,
                'quantity' => $quantity,
                'unit' => $data['unit'] ?? $lockedCampaign->crop->production_unit,
                'quality' => $data['quality'] ?? null,
                'recorded_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
                'harvest_code' => $data['harvest_code'] ?? null,
                'harvested_area_m2' => $data['harvested_area_m2'] ?? null,
                'loss_quantity' => $data['loss_quantity'] ?? 0,
                'grade' => $data['grade'] ?? null,
            ]);

            $this->produceStock->createLotFromHarvest($harvest, $actor);

            return $harvest;
        });

        DashboardCache::forget($campaign->farm);

        return $harvest;
    }
}
