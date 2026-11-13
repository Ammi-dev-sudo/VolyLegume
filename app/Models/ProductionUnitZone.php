<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionUnitZone extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'production_unit_id',
        'code',
        'name',
        'area_m2',
        'soil_type',
        'irrigation_type',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'production_unit_zone_id');
    }
}
