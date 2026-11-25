<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Résultat d'un modèle de prédiction rattaché à une entité métier.
 *
 * Une prédiction est identifiée par le couple (modèle, sujet) : une campagne
 * ne conserve qu'une prévision courante par modèle.
 */
class Prediction extends Model
{
    protected $fillable = [
        'farm_id',
        'model',
        'subject_type',
        'subject_id',
        'label',
        'value',
        'score',
        'context',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'score' => 'float',
            'context' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForFarm(Builder $query, ?int $farmId): Builder
    {
        return $query->where('predictions.farm_id', $farmId);
    }

    public function scopeForModel(Builder $query, string $model): Builder
    {
        return $query->where('predictions.model', $model);
    }
}
