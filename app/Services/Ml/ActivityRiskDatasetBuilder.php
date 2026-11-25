<?php

namespace App\Services\Ml;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Support\Collection;

/**
 * Construit le jeu de données du risque de retard d'une activité.
 *
 * Les variables sont disponibles au moment de la planification. La cible vaut
 * 1 lorsqu'une activité réalisée l'a été après sa date prévue, ou lorsqu'une
 * activité planifiée est déjà en retard au moment de l'export.
 */
class ActivityRiskDatasetBuilder implements MlDatasetBuilder
{
    public const ID_COLUMN = 'sample_id';

    public const TARGET_COLUMN = 'late';

    /**
     * @var list<string>
     */
    public const NUMERIC_FEATURES = [
        'days_since_campaign_start',
        'campaign_progress',
        'activities_same_week',
        'activities_before_in_campaign',
        'estimated_cost',
        'has_assignee',
    ];

    /**
     * @var list<string>
     */
    public const CATEGORICAL_FEATURES = [
        'activity_type',
        'crop_category',
    ];

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return array_merge(self::NUMERIC_FEATURES, self::CATEGORICAL_FEATURES);
    }

    /**
     * @return list<string>
     */
    public function trainingHeader(): array
    {
        return array_merge([self::ID_COLUMN], $this->features(), [self::TARGET_COLUMN]);
    }

    /**
     * @return list<string>
     */
    public function predictionHeader(): array
    {
        return array_merge([self::ID_COLUMN], $this->features());
    }

    /**
     * Activités réalisées (0/1 selon le retard) et activités planifiées déjà
     * en retard (1).
     *
     * @return list<array<string, mixed>>
     */
    public function trainingRows(): array
    {
        $activities = $this->activities();
        $context = $this->context($activities);

        return $activities
            ->map(function (Activity $activity) use ($context): ?array {
                $label = $this->label($activity);

                if ($label === null) {
                    return null;
                }

                $row = $this->featureRow($activity, $context);
                $row[self::ID_COLUMN] = $activity->id;
                $row[self::TARGET_COLUMN] = $label;

                return $row;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Activités planifiées à venir, sans issue connue.
     *
     * @return list<array<string, mixed>>
     */
    public function predictionRows(): array
    {
        $activities = $this->activities();
        $context = $this->context($activities);

        return $activities
            ->filter(fn (Activity $activity): bool => $activity->status === ActivityStatus::Planned
                && ! $activity->isLate())
            ->map(function (Activity $activity) use ($context): array {
                $row = $this->featureRow($activity, $context);
                $row[self::ID_COLUMN] = $activity->id;

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Activity>
     */
    private function activities(): Collection
    {
        return Activity::query()
            ->with(['campaign.crop.category'])
            ->whereIn('status', [ActivityStatus::Planned->value, ActivityStatus::Completed->value])
            ->orderBy('planned_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array{same_week: array<string, int>, before_in_campaign: array<int, int>}
     */
    private function context(Collection $activities): array
    {
        $sameWeek = [];
        $beforeInCampaign = [];

        foreach ($activities as $activity) {
            if ($activity->planned_date === null) {
                continue;
            }

            $key = $activity->farm_id.'|'.$activity->planned_date->format('o-W');
            $sameWeek[$key] = ($sameWeek[$key] ?? 0) + 1;
        }

        foreach ($activities->groupBy('campaign_id') as $group) {
            $sorted = $group->sortBy([['planned_date', 'asc'], ['id', 'asc']])->values();

            foreach ($sorted as $index => $activity) {
                $beforeInCampaign[$activity->id] = $index;
            }
        }

        return ['same_week' => $sameWeek, 'before_in_campaign' => $beforeInCampaign];
    }

    /**
     * @param  array{same_week: array<string, int>, before_in_campaign: array<int, int>}  $context
     * @return array<string, mixed>
     */
    private function featureRow(Activity $activity, array $context): array
    {
        $campaign = $activity->campaign;
        $crop = $campaign?->crop;
        $planned = $activity->planned_date;
        $start = $campaign?->start_date;
        $cycle = max(1, (int) ($crop?->estimated_cycle_days ?? 0));

        $daysSinceStart = 0;

        if ($start !== null && $planned !== null) {
            $daysSinceStart = max(0, (int) round($start->diffInDays($planned, false)));
        }

        $sameWeekKey = $planned !== null ? $activity->farm_id.'|'.$planned->format('o-W') : null;

        return [
            'days_since_campaign_start' => $daysSinceStart,
            'campaign_progress' => round($daysSinceStart / $cycle, 4),
            'activities_same_week' => (int) ($context['same_week'][$sameWeekKey] ?? 0),
            'activities_before_in_campaign' => (int) ($context['before_in_campaign'][$activity->id] ?? 0),
            'estimated_cost' => (float) ($activity->estimated_cost ?? 0),
            'has_assignee' => $activity->assigned_to !== null ? 1 : 0,
            'activity_type' => $activity->type instanceof ActivityType ? $activity->type->value : 'inconnu',
            'crop_category' => $crop?->category?->name ?? 'inconnue',
        ];
    }

    private function label(Activity $activity): ?int
    {
        if ($activity->planned_date === null) {
            return null;
        }

        if ($activity->status === ActivityStatus::Completed) {
            if ($activity->performed_at === null) {
                return null;
            }

            return $activity->performed_at->startOfDay()->gt($activity->planned_date->copy()->startOfDay()) ? 1 : 0;
        }

        if ($activity->status === ActivityStatus::Planned && $activity->isLate()) {
            return 1;
        }

        return null;
    }
}
