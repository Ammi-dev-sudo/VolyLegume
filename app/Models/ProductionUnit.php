<?php

namespace App\Models;

use App\Enums\ProductionUnitStatus;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionUnit extends Model
{
    use BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'area_m2',
        'location',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area_m2' => 'decimal:2',
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
