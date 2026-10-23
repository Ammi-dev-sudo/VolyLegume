<?php

namespace App\Models;

use App\Enums\ProductionUnitStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionUnit extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'area_m2',
        'length_m',
        'width_m',
        'soil_type',
        'irrigation_type',
        'sun_exposure',
        'slope',
        'latitude',
        'longitude',
        'location',
        'type',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'decimal:2',
            'length_m' => 'decimal:2',
            'width_m' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => ProductionUnitStatus::class,
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'production_unit_id');
    }
}
