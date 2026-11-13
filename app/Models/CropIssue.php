<?php

namespace App\Models;

use App\Enums\IssuePhotoKind;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CropIssue extends Model
{
    use Auditable, BelongsToFarm, HasFactory;

    protected $fillable = [
        'farm_id',
        'production_unit_id',
        'campaign_id',
        'reference',
        'type',
        'title',
        'description',
        'observed_at',
        'severity',
        'affected_area_m2',
        'status',
        'recommended_actions',
        'resolution_notes',
        'resolved_at',
        'reported_by',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'observed_at' => 'date',
            'resolved_at' => 'datetime',
            'affected_area_m2' => 'decimal:2',
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CropIssuePhoto::class)->orderByDesc('taken_at')->orderByDesc('id');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(PhytosanitaryTreatment::class, 'crop_issue_id')->orderByDesc('applied_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '<>', IssueStatus::Resolved->value);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', IssueSeverity::Critical->value);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function beforePhoto(): ?CropIssuePhoto
    {
        return $this->photos->firstWhere('kind', IssuePhotoKind::Before);
    }

    public function afterPhoto(): ?CropIssuePhoto
    {
        return $this->photos->firstWhere('kind', IssuePhotoKind::After);
    }

    public static function nextReference(int $farmId): string
    {
        $prefix = 'PB-'.now()->format('Ymd');
        $sequence = static::query()
            ->forFarm($farmId)
            ->where('reference', 'like', $prefix.'-%')
            ->count() + 1;

        do {
            $reference = sprintf('%s-%03d', $prefix, $sequence);
            $sequence++;
        } while (static::query()->forFarm($farmId)->where('reference', $reference)->exists());

        return $reference;
    }
}
