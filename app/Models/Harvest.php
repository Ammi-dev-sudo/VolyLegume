<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Harvest extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'campaign_id',
        'harvest_code',
        'harvested_at',
        'quantity',
        'harvested_area_m2',
        'loss_quantity',
        'unit',
        'quality',
        'grade',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'harvested_at' => 'datetime',
            'quantity' => 'decimal:3',
            'harvested_area_m2' => 'decimal:2',
            'loss_quantity' => 'decimal:3',
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

    public function produceLot(): HasOne
    {
        return $this->hasOne(ProduceLot::class);
    }
}
