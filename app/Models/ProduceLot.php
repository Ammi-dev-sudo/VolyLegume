<?php

namespace App\Models;

use App\Enums\ProduceLotStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduceLot extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'harvest_id',
        'campaign_id',
        'code',
        'initial_quantity',
        'available_quantity',
        'unit',
        'quality',
        'harvested_at',
        'storage_location',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'decimal:3',
            'available_quantity' => 'decimal:3',
            'harvested_at' => 'datetime',
            'status' => ProduceLotStatus::class,
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function harvest(): BelongsTo
    {
        return $this->belongsTo(Harvest::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProduceMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isDepleted(): bool
    {
        return (float) $this->available_quantity <= 0;
    }

    public function soldQuantity(): float
    {
        return (float) $this->saleItems()->sum('quantity');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', ProduceLotStatus::Available->value)
            ->where('available_quantity', '>', 0);
    }
}
