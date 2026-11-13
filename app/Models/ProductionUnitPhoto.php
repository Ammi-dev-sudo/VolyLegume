<?php

namespace App\Models;

use App\Enums\CropStage;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionUnitPhoto extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'production_unit_id',
        'campaign_id',
        'activity_id',
        'photo_path',
        'caption',
        'growth_stage',
        'observation_type',
        'is_demo',
        'taken_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'growth_stage' => CropStage::class,
            'taken_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
