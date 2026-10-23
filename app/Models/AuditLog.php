<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Journal d'audit transversal de l'application.
 *
 * Une ligne décrit qui a fait quoi, sur quelle entité et dans quelle
 * exploitation, avec les valeurs avant/après pour les modifications sensibles.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'farm_id',
        'user_id',
        'action',
        'description',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'context',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForFarm(Builder $query, ?int $farmId): Builder
    {
        return $query->where('audit_logs.farm_id', $farmId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('action', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');
        });
    }

    public function actionLabel(): string
    {
        return str($this->action)->replace('.', ' ')->headline()->toString();
    }
}
