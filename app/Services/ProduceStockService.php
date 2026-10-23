<?php

namespace App\Services;

use App\Enums\ProduceLotStatus;
use App\Enums\ProduceMovementType;
use App\Exceptions\InsufficientProduceStockException;
use App\Models\Harvest;
use App\Models\ProduceLot;
use App\Models\ProduceMovement;
use App\Models\Sale;
use App\Models\User;
use App\Support\DashboardCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProduceStockService
{
    public function createLotFromHarvest(Harvest $harvest, User $actor): ProduceLot
    {
        return DB::transaction(function () use ($harvest, $actor) {
            $existing = ProduceLot::query()->where('harvest_id', $harvest->getKey())->first();

            if ($existing !== null) {
                return $existing;
            }

            $lot = ProduceLot::create([
                'farm_id' => $harvest->farm_id,
                'harvest_id' => $harvest->getKey(),
                'campaign_id' => $harvest->campaign_id,
                'code' => 'LOT-'.str_pad((string) $harvest->getKey(), 6, '0', STR_PAD_LEFT),
                'initial_quantity' => $harvest->quantity,
                'available_quantity' => $harvest->quantity,
                'unit' => $harvest->unit,
                'quality' => $harvest->quality,
                'harvested_at' => $harvest->harvested_at,
                'status' => ProduceLotStatus::Available,
                'notes' => $harvest->notes,
            ]);

            $lot->movements()->create([
                'farm_id' => $harvest->farm_id,
                'type' => ProduceMovementType::Harvest,
                'quantity_delta' => $harvest->quantity,
                'reason' => 'Entrée automatique depuis la récolte #'.$harvest->getKey(),
                'occurred_at' => $harvest->harvested_at,
                'created_by' => $actor->id,
            ]);

            DashboardCache::forget($harvest->farm);

            return $lot;
        });
    }

    public function remove(
        ProduceLot $lot,
        float $quantity,
        ProduceMovementType $type,
        User $actor,
        string $reason,
        ?Sale $sale = null,
        ?CarbonInterface $occurredAt = null,
    ): ProduceMovement {
        if (! in_array($type, [ProduceMovementType::Sale, ProduceMovementType::Loss, ProduceMovementType::Gift, ProduceMovementType::Consumption], true)) {
            throw new InvalidArgumentException('Ce type de mouvement ne retire pas de stock.');
        }

        return $this->apply($lot, -abs($quantity), $type, $actor, $reason, $sale, $occurredAt, false);
    }

    public function restore(
        ProduceLot $lot,
        float $quantity,
        ProduceMovementType $type,
        User $actor,
        string $reason,
        ?Sale $sale = null,
        ?CarbonInterface $occurredAt = null,
    ): ProduceMovement {
        if (! in_array($type, [ProduceMovementType::Return, ProduceMovementType::Adjustment], true)) {
            throw new InvalidArgumentException('Ce type de mouvement ne remet pas de stock.');
        }

        return $this->apply($lot, abs($quantity), $type, $actor, $reason, $sale, $occurredAt, false);
    }

    public function adjust(ProduceLot $lot, float $signedDelta, User $actor, string $reason): ProduceMovement
    {
        if (abs($signedDelta) < 0.0005) {
            throw new InvalidArgumentException('La variation de stock doit être strictement non nulle.');
        }

        return $this->apply($lot, $signedDelta, ProduceMovementType::Adjustment, $actor, $reason, null, null, true);
    }

    private function apply(
        ProduceLot $lot,
        float $delta,
        ProduceMovementType $type,
        User $actor,
        string $reason,
        ?Sale $sale,
        ?CarbonInterface $occurredAt,
        bool $adjustInitial,
    ): ProduceMovement {
        $delta = round($delta, 3);

        if (abs($delta) < 0.0005) {
            throw new InvalidArgumentException('La variation de stock doit être strictement non nulle.');
        }

        [$movement, $farm] = DB::transaction(function () use ($lot, $delta, $type, $actor, $reason, $sale, $occurredAt, $adjustInitial) {
            $locked = ProduceLot::query()->whereKey($lot->getKey())->lockForUpdate()->firstOrFail();

            $before = (float) $locked->available_quantity;
            $after = round($before + $delta, 3);

            if ($after < 0) {
                throw new InsufficientProduceStockException($locked, abs($delta), $before);
            }

            $initial = (float) $locked->initial_quantity + ($adjustInitial && $delta > 0 ? $delta : 0);

            if ($after > $initial) {
                throw new InvalidArgumentException('Le stock disponible ne peut pas dépasser la quantité initiale du lot.');
            }

            $movement = $locked->movements()->create([
                'farm_id' => $locked->farm_id,
                'sale_id' => $sale?->getKey(),
                'type' => $type,
                'quantity_delta' => $delta,
                'reason' => $reason,
                'occurred_at' => $occurredAt ?? now(),
                'created_by' => $actor->id,
            ]);

            $locked->initial_quantity = $initial;
            $locked->available_quantity = $after;
            $locked->status = $after <= 0
                ? ProduceLotStatus::Depleted
                : ($locked->status === ProduceLotStatus::Lost ? ProduceLotStatus::Lost : ProduceLotStatus::Available);
            $locked->save();

            return [$movement, $locked->farm];
        });

        DashboardCache::forget($farm);

        return $movement;
    }
}
