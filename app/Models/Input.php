<?php

namespace App\Models;

use App\Enums\BaseUnit;
use App\Enums\InputCategory;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Input extends Model
{
    use BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'category',
        'base_unit',
        'quantity',
        'minimum_stock',
        'default_pre_harvest_days',
        'delay_source',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => InputCategory::class,
            'base_unit' => BaseUnit::class,
            'quantity' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'default_pre_harvest_days' => 'integer',
            'critical_notified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(PhytosanitaryTreatment::class);
    }

    /**
     * RG13 : un stock est critique lorsque la quantité disponible est strictement
     * inférieure au seuil minimal.
     */
    public function isCritical(): bool
    {
        return (float) $this->quantity < (float) $this->minimum_stock;
    }

    public function isUsablePhytosanitary(): bool
    {
        return $this->category === InputCategory::Phytosanitary
            && $this->default_pre_harvest_days !== null
            && $this->delay_source !== null;
    }
}
