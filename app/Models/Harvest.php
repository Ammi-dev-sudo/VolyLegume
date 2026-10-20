<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Harvest extends Model
{
    use BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'campaign_id',
        'harvested_at',
        'quantity',
        'unit',
        'quality',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'harvested_at' => 'datetime',
            'quantity' => 'decimal:3',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }
}
