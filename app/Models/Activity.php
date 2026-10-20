<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToFarm;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'campaign_id',
        'calendar_step_id',
        'type',
        'title',
        'description',
        'planned_date',
        'performed_at',
        'status',
        'assigned_to',
        'created_by',
        'performed_by',
        'estimated_cost',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'status' => ActivityStatus::class,
            'planned_date' => 'date',
            'performed_at' => 'datetime',
            'estimated_cost' => 'decimal:2',
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

    public function calendarStep(): BelongsTo
    {
        return $this->belongsTo(CalendarStep::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Le retard est calculé (RG / MLD) : activité prévue dont la date est dépassée.
     */
    public function isLate(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->status === ActivityStatus::Planned
            && $this->planned_date->endOfDay()->lt($at);
    }

    public function scopeOverdue(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('status', ActivityStatus::Planned->value)
            ->where('planned_date', '<', $at->toDateString());
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ActivityStatus::Completed->value);
    }
}
