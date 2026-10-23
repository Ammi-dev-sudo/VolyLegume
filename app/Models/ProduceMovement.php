<?php

namespace App\Models;

use App\Enums\ProduceMovementType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduceMovement extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'produce_lot_id',
        'sale_id',
        'type',
        'quantity_delta',
        'reason',
        'occurred_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProduceMovementType::class,
            'quantity_delta' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function produceLot(): BelongsTo
    {
        return $this->belongsTo(ProduceLot::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
