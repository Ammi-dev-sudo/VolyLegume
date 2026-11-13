<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\ReferenceType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'production_unit_id',
        'production_unit_zone_id',
        'crop_id',
        'name',
        'start_date',
        'reference_type',
        'expected_end_date',
        'actual_end_date',
        'cultivated_area_m2',
        'planting_density',
        'expected_yield_kg',
        'actual_yield_kg',
        'planned_budget',
        'water_quota_m3',
        'water_alert_threshold_percent',
        'water_notes',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
            'cultivated_area_m2' => 'decimal:2',
            'planting_density' => 'decimal:2',
            'expected_yield_kg' => 'decimal:3',
            'actual_yield_kg' => 'decimal:3',
            'planned_budget' => 'decimal:2',
            'water_quota_m3' => 'decimal:3',
            'water_alert_threshold_percent' => 'decimal:2',
            'status' => CampaignStatus::class,
            'reference_type' => ReferenceType::class,
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class, 'production_unit_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ProductionUnitZone::class, 'production_unit_zone_id');
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(CampaignStageEvent::class)->orderBy('recorded_at')->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductionUnitPhoto::class)->orderByDesc('taken_at')->orderByDesc('id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(CropIssue::class)->orderByDesc('observed_at');
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(PhytosanitaryTreatment::class);
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function produceLots(): HasMany
    {
        return $this->hasMany(ProduceLot::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    public function irrigationEvents(): HasMany
    {
        return $this->hasMany(IrrigationEvent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CampaignStatus::Planned->value,
            CampaignStatus::Ongoing->value,
        ]);
    }
}
