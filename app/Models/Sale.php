<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'customer_id',
        'reference',
        'sale_date',
        'status',
        'subtotal',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'payment_status',
        'notes',
        'recorded_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'status' => SaleStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function revenue(): HasOne
    {
        return $this->hasOne(Revenue::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function balanceDue(): float
    {
        return round((float) $this->total_amount - (float) $this->amount_paid, 2);
    }

    public function canBeCancelled(): bool
    {
        return $this->status === SaleStatus::Confirmed && (float) $this->amount_paid <= 0;
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Confirmed->value);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('payment_status', [
            PaymentStatus::Unpaid->value,
            PaymentStatus::Partial->value,
        ]);
    }
}
