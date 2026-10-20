<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarStep extends Model
{
    use BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'crop_id',
        'name',
        'activity_type',
        'offset_days',
        'position',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'offset_days' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
