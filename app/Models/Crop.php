<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crop extends Model
{
    use BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'name',
        'variety',
        'production_unit',
        'estimated_cycle_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cycle_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function calendarSteps(): HasMany
    {
        return $this->hasMany(CalendarStep::class);
    }
}
