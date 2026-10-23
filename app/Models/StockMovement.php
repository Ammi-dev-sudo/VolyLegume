<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'input_id',
        'campaign_id',
        'activity_id',
        'type',
        'quantity_delta',
        'occurred_at',
        'created_by',
        'reason',
        'corrects_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity_delta' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function input(): BelongsTo
    {
        return $this->belongsTo(Input::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function correctsMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_movement_id');
    }
}
