<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Events\StockCritical;
use App\Exceptions\InsufficientStockException;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Input;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\DashboardCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Journal des mouvements de stock avec verrouillage pessimiste.
 *
 * Toute variation passe par cette classe : le solde `inputs.quantity` est
 * toujours la somme des `quantity_delta` et ne peut jamais devenir négatif.
 */
class StockService
{
    public function record(
        Input $input,
        StockMovementType $type,
        float $delta,
        User $actor,
        string $reason,
        ?Campaign $campaign = null,
        ?Activity $activity = null,
        ?CarbonInterface $occurredAt = null,
    ): StockMovement {
        $delta = round($delta, 3);

        if (abs($delta) < 0.0005) {
            throw new InvalidArgumentException('La variation de stock doit être strictement non nulle.');
        }

        [$movement, $farm] = DB::transaction(function () use ($input, $type, $delta, $actor, $reason, $campaign, $activity, $occurredAt) {
            $locked = Input::query()->whereKey($input->getKey())->lockForUpdate()->firstOrFail();

            $before = (float) $locked->quantity;
            $after = round($before + $delta, 3);

            if ($after < 0) {
                throw new InsufficientStockException($locked, $delta, $before);
            }

            $movement = $locked->stockMovements()->create([
                'farm_id' => $locked->farm_id,
                'campaign_id' => $campaign?->id,
                'activity_id' => $activity?->id,
                'type' => $type,
                'quantity_delta' => $delta,
                'occurred_at' => $occurredAt ?? now(),
                'created_by' => $actor->id,
                'reason' => $reason,
            ]);

            $wasCritical = $before < (float) $locked->minimum_stock;
            $isCritical = $after < (float) $locked->minimum_stock;

            $locked->quantity = $after;
            $locked->critical_notified_at = $isCritical
                ? ($locked->critical_notified_at ?? now())
                : null;
            $locked->save();

            if (! $wasCritical && $isCritical) {
                DB::afterCommit(fn () => event(new StockCritical($locked, $after)));
            }

            return [$movement, $locked->farm];
        });

        DashboardCache::forget($farm);

        return $movement;
    }

    public function setInitial(Input $input, float $quantity, User $actor, string $reason = 'Stock initial'): StockMovement
    {
        return $this->record($input, StockMovementType::Initial, abs($quantity), $actor, $reason);
    }

    public function receive(Input $input, float $quantity, User $actor, string $reason, ?Campaign $campaign = null): StockMovement
    {
        return $this->record($input, StockMovementType::Entry, abs($quantity), $actor, $reason, $campaign);
    }

    public function consume(Input $input, float $quantity, User $actor, string $reason, ?Campaign $campaign = null, ?Activity $activity = null, ?CarbonInterface $occurredAt = null): StockMovement
    {
        return $this->record($input, StockMovementType::Consumption, -abs($quantity), $actor, $reason, $campaign, $activity, $occurredAt);
    }

    public function release(Input $input, float $quantity, User $actor, string $reason, ?Campaign $campaign = null): StockMovement
    {
        return $this->record($input, StockMovementType::Exit, -abs($quantity), $actor, $reason, $campaign);
    }

    public function adjust(Input $input, float $signedDelta, User $actor, string $reason): StockMovement
    {
        return $this->record($input, StockMovementType::Adjustment, $signedDelta, $actor, $reason);
    }
}
