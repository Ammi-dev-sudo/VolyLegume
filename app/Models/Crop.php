<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Crop extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'category_id',
        'name',
        'scientific_name',
        'family',
        'variety',
        'production_unit',
        'estimated_cycle_days',
        'planting_density',
        'average_yield_kg_m2',
        'seed_rate_kg_m2',
        'default_sale_price',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cycle_days' => 'integer',
            'planting_density' => 'decimal:2',
            'average_yield_kg_m2' => 'decimal:3',
            'seed_rate_kg_m2' => 'decimal:4',
            'default_sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CropCategory::class, 'category_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function calendarSteps(): HasMany
    {
        return $this->hasMany(CalendarStep::class);
    }

    public function guide(): HasOne
    {
        return $this->hasOne(CropGuide::class);
    }
}
