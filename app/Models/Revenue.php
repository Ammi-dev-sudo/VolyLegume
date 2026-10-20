<?php

namespace App\Models;

use App\Enums\RevenueCategory;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Revenue extends Model
{
    use BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'campaign_id',
        'harvest_id',
        'category',
        'description',
        'amount',
        'revenue_date',
        'reference',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => RevenueCategory::class,
            'amount' => 'decimal:2',
            'revenue_date' => 'date',
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

    public function harvest(): BelongsTo
    {
        return $this->belongsTo(Harvest::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
