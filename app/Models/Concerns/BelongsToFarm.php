<?php

namespace App\Models\Concerns;

use App\Support\FarmContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Applique l'isolation par exploitation aux modèles métier.
 *
 * - `forFarm()` restreint une requête à une exploitation.
 * - Le binding de route renvoie un 404 si la ressource appartient à une autre
 *   exploitation que l'exploitation active du compte.
 */
trait BelongsToFarm
{
    public function scopeForFarm(Builder $query, ?int $farmId): Builder
    {
        return $query->where($this->getTable().'.farm_id', $farmId);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $model = parent::resolveRouteBinding($value, $field);

        if ($model !== null && app(FarmContext::class)->id() !== (int) $model->farm_id) {
            throw (new ModelNotFoundException)->setModel(static::class, [$value]);
        }

        return $model;
    }
}
