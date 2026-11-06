<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CampaignStatus;
use App\Enums\ProductionUnitStatus;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Farm;
use App\Models\Input;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitPhoto;
use App\Support\ParcelBoundary;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Prépare le plan interactif d'une exploitation.
 *
 * Les contours enregistrés (GeoJSON) alimentent les vues 2D et 3D. Les
 * parcelles sans contour restent disponibles dans un plan schématique de
 * repli ; la 3D est une extrusion symbolique, pas un modèle topographique.
 */
class FarmMapService
{
    private const MAP_WIDTH = 100.0;

    private const MAP_HEIGHT = 62.5;

    /**
     * Palette stable : la couleur d'une culture ne dépend pas de l'ordre des
     * requêtes, ce qui évite les changements visuels entre deux chargements.
     *
     * @var list<string>
     */
    private const CROP_COLORS = [
        '#059669', '#d97706', '#0284c7', '#7c3aed', '#db2777',
        '#0d9488', '#65a30d', '#ea580c', '#4f46e5', '#475569',
    ];

    /**
     * @return array{
     *     units: list<array<string, mixed>>,
     *     legend: list<array{label: string, color: string, kind: string}>,
     *     stats: array<string, int|float>,
     *     geometry: array<string, mixed>,
     *     harvest: array<string, mixed>,
     *     critical_inputs: list<array{name: string, quantity: float, minimum_stock: float, unit: string}>,
     *     generated_at: string
     * }
     */
    public function forFarm(Farm $farm): array
    {
        $now = now();

        $units = ProductionUnit::query()
            ->forFarm($farm->id)
            ->with([
                'campaigns' => fn ($query) => $query
                    ->with([
                        'crop:id,name,variety,estimated_cycle_days',
                        'activities' => fn ($activities) => $activities
                            ->where('status', ActivityStatus::Planned->value)
                            ->orderBy('planned_date'),
                        'harvests' => fn ($harvests) => $harvests->orderByDesc('harvested_at'),
                        'treatments' => fn ($treatments) => $treatments
                            ->where('applied_at', '<=', $now)
                            ->orderByDesc('minimum_harvest_at'),
                        'irrigationEvents' => fn ($events) => $events
                            ->orderByDesc('irrigated_at')
                            ->orderByDesc('id'),
                    ])
                    ->orderByDesc('start_date'),
                'photos' => fn ($photos) => $photos->orderByDesc('taken_at')->orderByDesc('id')->limit(12),
            ])
            ->orderBy('code')
            ->get();

        $rows = $units
            ->map(fn (ProductionUnit $unit): array => $this->unitRow($unit, $now))
            ->values();

        $layout = $this->layout($rows);

        $rows = $rows->map(function (array $row) use ($layout): array {
            $row['layout'] = $layout[$row['unit']->id] ?? [
                'left' => 1.0,
                'top' => 1.0,
                'width' => 98.0,
                'height' => 98.0,
            ];

            return $row;
        });

        $criticalInputs = Input::query()
            ->forFarm($farm->id)
            ->whereColumn('quantity', '<', 'minimum_stock')
            ->orderBy('name')
            ->get()
            ->map(fn (Input $input): array => [
                'name' => $input->name,
                'quantity' => (float) $input->quantity,
                'minimum_stock' => (float) $input->minimum_stock,
                'unit' => $input->base_unit->value,
            ])
            ->all();

        $overdue = (int) $rows->sum(fn (array $row): int => $row['overdue_count']);
        $blocked = $rows->filter(fn (array $row): bool => $row['blocked_until'] !== null)->count();
        $mapped = $rows->filter(fn (array $row): bool => $row['has_boundary']);
        $withoutBoundary = $rows->reject(fn (array $row): bool => $row['has_boundary']);
        $withoutCoordinates = $withoutBoundary->filter(fn (array $row): bool => $row['map_point'] === null);
        $legend = $this->legend($rows);
        $layers = $this->layers($rows, $criticalInputs, $legend);

        $harvestTimeline = $rows
            ->flatMap(fn (array $row): array => $row['harvest_timeline'])
            ->sortBy('timestamp')
            ->values();
        $harvestStateCounts = $rows->countBy(fn (array $row): string => $row['harvest_state']['key']);
        $harvestStateDefinitions = $this->harvestStateDefinitions();
        $harvestStates = collect($harvestStateDefinitions)
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => $definition['label'],
                'color' => $definition['color'],
                'description' => $definition['description'],
                'count' => (int) ($harvestStateCounts[$key] ?? 0),
            ])
            ->values()
            ->all();
        $nextPlannedHarvest = $harvestTimeline->first(
            fn (array $event): bool => $event['kind'] === 'planned' && $event['timestamp'] >= $now->timestamp,
        );

        return [
            'units' => $rows->all(),
            'legend' => $legend,
            'layers' => $layers,
            'harvest' => [
                'states' => $harvestStates,
                'filters' => $this->harvestFilters($harvestStateCounts, $rows->count()),
                'timeline' => $harvestTimeline->all(),
                'recorded_count' => (int) $rows->sum(fn (array $row): int => $row['harvest_count']),
                'planned_count' => $harvestTimeline->where('kind', 'planned')->count(),
                'next_planned_date' => $nextPlannedHarvest['date'] ?? null,
                'next_planned_label' => $nextPlannedHarvest['campaign'] ?? null,
                'today' => $now->format('d/m/Y'),
                'today_timestamp' => $now->timestamp,
            ],
            'stats' => [
                'units' => $rows->count(),
                'area_m2' => round((float) $units->sum(fn (ProductionUnit $unit): float => (float) $unit->area_m2), 2),
                'active_campaigns' => (int) $rows->sum(fn (array $row): int => count($row['campaigns'])),
                'overdue_activities' => $overdue,
                'blocked_harvests' => $blocked,
                'critical_inputs' => count($criticalInputs),
                'with_boundary' => $mapped->count(),
                'without_boundary' => $withoutBoundary->count(),
                'without_coordinates' => $withoutCoordinates->count(),
                'mapped_area_m2' => round((float) $mapped->sum(fn (array $row): float => (float) $row['boundary_area_m2']), 2),
                'alerts' => $overdue + $blocked + count($criticalInputs),
            ],
            'geometry' => [
                'farm' => [
                    'name' => $farm->name,
                    'latitude' => $farm->latitude !== null ? (float) $farm->latitude : null,
                    'longitude' => $farm->longitude !== null ? (float) $farm->longitude : null,
                ],
                'default_mode' => $mapped->isNotEmpty() ? '2d' : 'plan',
                'layers' => $layers,
                'terrain' => [
                    'has_elevation' => false,
                    'source' => null,
                    'label' => 'Extrusion des contours',
                    'note' => 'Aucune source d’altitude fiable n’est configurée : aucun relief réel n’est affiché.',
                ],
                'units' => $rows->map(fn (array $row): array => [
                    'id' => $row['unit']->id,
                    'code' => $row['unit']->code,
                    'name' => $row['unit']->name,
                    'status' => $row['unit']->status->value,
                    'status_label' => $row['unit']->status->label(),
                    'color' => $row['color'],
                    'color_dark' => $row['color_dark'],
                    'has_boundary' => $row['has_boundary'],
                    'boundary' => $row['boundary'],
                    'points' => $row['boundary_points'],
                    'centroid' => $row['centroid'],
                    'point' => $row['map_point'],
                    'area_m2' => $row['boundary_area_m2'],
                    'perimeter_m' => $row['perimeter_m'],
                    'harvest_state' => $row['harvest_state'],
                    'detail_target' => 'plot-detail-'.$row['unit']->id,
                    'edit_url' => $row['can_edit'] ? $row['boundary_url'] : null,
                    'layers' => $row['layers'],
                ])->values()->all(),
            ],
            'critical_inputs' => $criticalInputs,
            'generated_at' => $now->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unitRow(ProductionUnit $unit, CarbonInterface $now): array
    {
        $allCampaigns = $unit->campaigns;
        $activeStatuses = [
            CampaignStatus::Planned,
            CampaignStatus::Ongoing,
            CampaignStatus::Suspended,
        ];
        $campaigns = $allCampaigns
            ->filter(fn (Campaign $campaign): bool => in_array($campaign->status, $activeStatuses, true))
            ->values();

        $primary = $campaigns->first(fn (Campaign $campaign): bool => $campaign->status === CampaignStatus::Ongoing)
            ?? $campaigns->first();

        $color = $primary !== null
            ? $this->cropColor($primary->crop?->name ?? $primary->name)
            : $this->statusColor($unit->status);

        $legendLabel = $primary !== null
            ? ($primary->crop?->name ?? $primary->name)
            : $unit->status->label();

        $campaignRows = $campaigns
            ->map(fn (Campaign $campaign): array => $this->campaignRow($campaign, $now))
            ->values();

        $overdueActivities = $campaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->activities
                ->filter(fn (Activity $activity): bool => $activity->isLate($now))
                ->map(fn (Activity $activity): array => $this->activityRow($activity, $campaign)))
            ->sortBy('timestamp')
            ->values();

        $upcomingActivities = $campaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->activities
                ->reject(fn (Activity $activity): bool => $activity->isLate($now))
                ->map(fn (Activity $activity): array => $this->activityRow($activity, $campaign)))
            ->sortBy('timestamp')
            ->values();

        $harvestRows = $allCampaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->harvests
                ->map(fn ($harvest): array => [
                    'id' => $harvest->id,
                    'date' => $harvest->harvested_at->format('d/m/Y'),
                    'day_label' => $harvest->harvested_at->format('d/m'),
                    'month_key' => $harvest->harvested_at->format('Y-m'),
                    'month_label' => $harvest->harvested_at->translatedFormat('F Y'),
                    'timestamp' => $harvest->harvested_at->timestamp,
                    'quantity' => (float) $harvest->quantity,
                    'unit' => $harvest->unit,
                    'campaign_id' => $campaign->id,
                    'campaign' => $campaign->name,
                    'campaign_status' => $campaign->status->value,
                    'crop' => $campaign->crop?->name ?? $campaign->name,
                    'unit_code' => $unit->code,
                    'unit_name' => $unit->name,
                    'url' => route('harvests.show', $harvest),
                ]))
            ->sortByDesc('timestamp')
            ->values();

        $plannedHarvestRows = $campaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->activities
                ->filter(fn (Activity $activity): bool => $activity->type === ActivityType::Harvest
                    && $activity->status === ActivityStatus::Planned
                    && $activity->planned_date !== null
                    && $activity->planned_date->timestamp >= $now->copy()->startOfDay()->timestamp)
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'date' => $activity->planned_date->format('d/m/Y'),
                    'day_label' => $activity->planned_date->format('d/m'),
                    'month_key' => $activity->planned_date->format('Y-m'),
                    'month_label' => $activity->planned_date->translatedFormat('F Y'),
                    'timestamp' => $activity->planned_date->timestamp,
                    'campaign_id' => $campaign->id,
                    'campaign' => $campaign->name,
                    'campaign_status' => $campaign->status->value,
                    'crop' => $campaign->crop?->name ?? $campaign->name,
                    'unit_code' => $unit->code,
                    'unit_name' => $unit->name,
                    'url' => route('activities.show', $activity),
                ]))
            ->sortBy('timestamp')
            ->values();

        $blockedUntil = $campaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->treatments)
            ->map(fn ($treatment) => $treatment->minimum_harvest_at)
            ->filter(fn ($date) => $date !== null && $date->gt($now))
            ->sortDesc()
            ->first();

        $harvestState = $this->harvestState(
            activeCampaigns: $campaigns,
            allCampaigns: $allCampaigns,
            harvestRows: $harvestRows,
            nextPlannedHarvest: $plannedHarvestRows->first(),
            blockedUntil: $blockedUntil,
        );

        $harvestTimeline = $harvestRows
            ->map(fn (array $harvest): array => [
                'key' => 'harvest-'.$harvest['id'],
                'kind' => 'recorded',
                'state_key' => $harvestState['key'],
                'state_label' => $harvestState['label'],
                'state_color' => $harvestState['color'],
                'date' => $harvest['date'],
                'day_label' => $harvest['day_label'],
                'month_key' => $harvest['month_key'],
                'month_label' => $harvest['month_label'],
                'timestamp' => $harvest['timestamp'],
                'is_future' => false,
                'label' => 'Récolte enregistrée',
                'quantity' => $harvest['quantity'],
                'unit' => $harvest['unit'],
                'campaign_id' => $harvest['campaign_id'],
                'campaign' => $harvest['campaign'],
                'crop' => $harvest['crop'],
                'unit_id' => $unit->id,
                'unit_code' => $unit->code,
                'unit_name' => $unit->name,
                'url' => $harvest['url'],
            ])
            ->merge($plannedHarvestRows->map(fn (array $harvest): array => [
                'key' => 'activity-'.$harvest['id'],
                'kind' => 'planned',
                'state_key' => $harvestState['key'],
                'state_label' => $harvestState['label'],
                'state_color' => $harvestState['color'],
                'date' => $harvest['date'],
                'day_label' => $harvest['day_label'],
                'month_key' => $harvest['month_key'],
                'month_label' => $harvest['month_label'],
                'timestamp' => $harvest['timestamp'],
                'is_future' => $harvest['timestamp'] >= $now->timestamp,
                'label' => 'Récolte planifiée',
                'quantity' => null,
                'unit' => null,
                'campaign_id' => $harvest['campaign_id'],
                'campaign' => $harvest['campaign'],
                'crop' => $harvest['crop'],
                'unit_id' => $unit->id,
                'unit_code' => $unit->code,
                'unit_name' => $unit->name,
                'url' => $harvest['url'],
            ]))
            ->sortBy('timestamp')
            ->values();

        $alerts = [];

        if ($overdueActivities->isNotEmpty()) {
            $alerts[] = [
                'type' => 'overdue',
                'label' => $overdueActivities->count().' activité(s) en retard',
            ];
        }

        if ($blockedUntil !== null) {
            $alerts[] = [
                'type' => 'blocked',
                'label' => 'Récolte bloquée jusqu’au '.$blockedUntil->format('d/m/Y'),
            ];
        }

        $photos = $unit->photos->map(fn (ProductionUnitPhoto $photo): array => [
            'id' => $photo->id,
            'model' => $photo,
            'url' => asset('storage/'.$photo->photo_path),
            'caption' => $photo->caption,
            'taken_at' => $photo->taken_at?->format('d/m/Y'),
            'campaign' => $photo->campaign?->name,
            'activity' => $photo->activity?->title,
            'uploaded_by' => $photo->uploadedBy?->name,
        ])->values()->all();

        $boundary = ParcelBoundary::normalize($unit->boundary);
        $boundaryPoints = ParcelBoundary::points($boundary);
        $hasBoundary = $boundaryPoints !== [];
        $centroid = $hasBoundary ? ParcelBoundary::centroid($boundaryPoints) : null;
        $mapPoint = $centroid;

        if ($mapPoint === null && $unit->latitude !== null && $unit->longitude !== null) {
            $mapPoint = [
                'lat' => (float) $unit->latitude,
                'lng' => (float) $unit->longitude,
            ];
        }

        return [
            'unit' => $unit,
            'color' => $color,
            'color_dark' => $this->shade($color, 0.68),
            'legend_label' => $legendLabel,
            'legend_kind' => $primary !== null ? 'crop' : 'status',
            'primary_campaign' => $primary !== null ? $this->campaignRow($primary, $now) : null,
            'campaigns' => $campaignRows->all(),
            'overdue_activities' => $overdueActivities->take(3)->all(),
            'overdue_count' => $overdueActivities->count(),
            'next_activities' => $upcomingActivities->take(4)->all(),
            'upcoming_count' => $upcomingActivities->count(),
            'harvests' => $harvestRows->take(4)->all(),
            'harvest_count' => $harvestRows->count(),
            'harvest_state' => $harvestState,
            'harvest_timeline' => $harvestTimeline->all(),
            'harvest_totals_by_unit' => $harvestRows
                ->groupBy('unit')
                ->map(fn (Collection $group): float => round((float) $group->sum('quantity'), 3))
                ->all(),
            'blocked_until' => $blockedUntil?->format('d/m/Y'),
            'alerts' => $alerts,
            'alert_count' => count($alerts),
            'photos' => $photos,
            'photo_count' => count($photos),
            'boundary' => $boundary,
            'boundary_points' => $boundaryPoints,
            'has_boundary' => $hasBoundary,
            'boundary_area_m2' => $hasBoundary ? round(ParcelBoundary::areaM2($boundaryPoints), 2) : null,
            'perimeter_m' => $hasBoundary ? round(ParcelBoundary::perimeterM($boundaryPoints), 2) : null,
            'centroid' => $centroid,
            'map_point' => $mapPoint,
            'can_edit' => Gate::allows('update', $unit),
            'boundary_url' => route('production-units.boundary.edit', $unit),
            'layers' => $this->unitLayers(
                $unit,
                $campaigns,
                $campaignRows,
                $alerts,
                $overdueActivities,
                $upcomingActivities,
                $harvestRows,
                $harvestState,
            ),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campaignRows
     * @param  list<array<string, mixed>>  $alerts
     * @param  Collection<int, array<string, mixed>>  $overdueActivities
     * @param  Collection<int, array<string, mixed>>  $upcomingActivities
     * @param  Collection<int, array<string, mixed>>  $harvestRows
     * @param  array<string, mixed>  $harvestState
     * @return array<string, mixed>
     */
    private function unitLayers(
        ProductionUnit $unit,
        Collection $campaigns,
        Collection $campaignRows,
        array $alerts,
        Collection $overdueActivities,
        Collection $upcomingActivities,
        Collection $harvestRows,
        array $harvestState,
    ): array {
        $cropLabels = $campaignRows
            ->map(fn (array $campaign): string => $campaign['crop'] ?? $campaign['name'])
            ->filter()
            ->unique()
            ->values();

        $campaignItems = $campaignRows->map(fn (array $campaign): array => [
            'id' => $campaign['id'],
            'name' => $campaign['name'],
            'crop' => $campaign['crop'],
            'variety' => $campaign['variety'],
            'status' => $campaign['status'],
            'status_label' => $campaign['status_label'],
            'progress' => $campaign['progress'],
            'start_date' => $campaign['start_date'],
            'expected_end_date' => $campaign['expected_end_date'],
            'url' => $campaign['url'],
        ])->values();

        return [
            'crops' => [
                'available' => $campaignRows->isNotEmpty(),
                'count' => $campaignRows->count(),
                'primary_label' => $cropLabels->first(),
                'labels' => $cropLabels->all(),
                'items' => $campaignItems->all(),
            ],
            'campaigns' => [
                'available' => $campaignRows->isNotEmpty(),
                'count' => $campaignRows->count(),
                'labels' => $campaignRows->pluck('name')->all(),
                'color' => $campaignRows->isNotEmpty()
                    ? $this->campaignStatusColor($campaignRows->first()['status'])
                    : '#0284c7',
                'items' => $campaignItems->all(),
            ],
            'upcoming_activities' => [
                'available' => $upcomingActivities->isNotEmpty(),
                'count' => $upcomingActivities->count(),
                'labels' => $upcomingActivities->pluck('title')->all(),
                'items' => $upcomingActivities->take(3)->values()->all(),
            ],
            'late_activities' => [
                'available' => $overdueActivities->isNotEmpty(),
                'count' => $overdueActivities->count(),
                'labels' => $overdueActivities->pluck('title')->all(),
                'items' => $overdueActivities->take(3)->values()->all(),
            ],
            'harvests' => [
                'available' => $harvestRows->isNotEmpty(),
                'count' => $harvestRows->count(),
                'labels' => $harvestRows
                    ->map(fn (array $harvest): string => $harvest['date'].' · '
                        .number_format($harvest['quantity'], 1, ',', ' ').' '.$harvest['unit'])
                    ->all(),
                'items' => $harvestRows->take(3)->values()->all(),
            ],
            'harvest_state' => [
                'available' => true,
                'key' => $harvestState['key'],
                'label' => $harvestState['label'],
                'color' => $harvestState['color'],
                'description' => $harvestState['description'],
                'reason' => $harvestState['reason'],
                'count' => 1,
                'labels' => [$harvestState['label']],
                'items' => [$harvestState],
                'campaign' => $harvestState['campaign'],
                'harvest_count' => $harvestState['harvest_count'],
                'active_harvest_count' => $harvestState['active_harvest_count'],
                'last_harvested_at' => $harvestState['last_harvested_at'],
                'next_planned_date' => $harvestState['next_planned_date'],
            ],
            'alerts' => [
                'available' => $alerts !== [],
                'count' => count($alerts),
                'labels' => array_column($alerts, 'label'),
                'items' => $alerts,
            ],
            'irrigation' => $this->irrigationLayer($campaigns),
            'status' => [
                'available' => true,
                'value' => $unit->status->value,
                'label' => $unit->status->label(),
                'color' => $this->statusColor($unit->status),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function irrigationLayer(Collection $campaigns): array
    {
        $events = $campaigns->flatMap(fn (Campaign $campaign) => $campaign->relationLoaded('irrigationEvents')
            ? $campaign->irrigationEvents
            : collect());

        $usedM3 = (float) $events->sum(fn ($event): float => (float) $event->volume_m3);
        $quotaM3 = $campaigns->contains(fn (Campaign $campaign): bool => $campaign->water_quota_m3 !== null)
            ? (float) $campaigns->sum(fn (Campaign $campaign): float => (float) ($campaign->water_quota_m3 ?? 0))
            : null;

        $exceeded = false;
        $approaching = false;

        $campaigns->each(function (Campaign $campaign) use (&$exceeded, &$approaching): void {
            if ($campaign->water_quota_m3 === null) {
                return;
            }

            $quota = (float) $campaign->water_quota_m3;

            if ($quota <= 0) {
                return;
            }

            $used = (float) ($campaign->relationLoaded('irrigationEvents')
                ? $campaign->irrigationEvents->sum(fn ($event): float => (float) $event->volume_m3)
                : 0);
            $threshold = $campaign->water_alert_threshold_percent !== null
                ? (float) $campaign->water_alert_threshold_percent
                : 80.0;

            if ($used >= $quota) {
                $exceeded = true;
            } elseif ($used >= $quota * $threshold / 100.0) {
                $approaching = true;
            }
        });

        $status = $exceeded
            ? 'exceeded'
            : ($approaching
                ? 'approaching'
                : ($quotaM3 !== null ? 'ok' : ($events->isNotEmpty() ? 'unquoted' : 'unavailable')));

        $last = $events
            ->sortByDesc(fn ($event): int => (($event->irrigated_at?->timestamp ?? 0) * 100000) + (int) $event->id)
            ->first();

        return [
            'available' => $events->isNotEmpty() || $quotaM3 !== null,
            'events_count' => $events->count(),
            'used_m3' => round($usedM3, 3),
            'quota_m3' => $quotaM3 !== null ? round($quotaM3, 3) : null,
            'usage_percent' => $quotaM3 !== null && $quotaM3 > 0 ? round($usedM3 / $quotaM3 * 100, 1) : null,
            'status' => $status,
            'status_label' => $this->irrigationStatusLabel($status),
            'last_irrigated_at' => $last?->irrigated_at?->format('d/m/Y'),
            'last_volume_m3' => $last !== null ? round((float) $last->volume_m3, 3) : null,
            'method' => $last?->method,
            'source' => $last?->source,
        ];
    }

    private function irrigationStatusLabel(string $status): string
    {
        return match ($status) {
            'ok' => 'Sous le seuil',
            'approaching' => 'Seuil d’alerte atteint',
            'exceeded' => 'Quota dépassé',
            'unquoted' => 'Volume relevé sans quota',
            default => 'Aucune irrigation enregistrée',
        };
    }

    /**
     * @param  Collection<int, Campaign>  $activeCampaigns
     * @param  Collection<int, Campaign>  $allCampaigns
     * @param  Collection<int, array<string, mixed>>  $harvestRows
     * @param  array<string, mixed>|null  $nextPlannedHarvest
     * @return array<string, mixed>
     */
    private function harvestState(
        Collection $activeCampaigns,
        Collection $allCampaigns,
        Collection $harvestRows,
        ?array $nextPlannedHarvest,
        ?CarbonInterface $blockedUntil,
    ): array {
        $definitions = $this->harvestStateDefinitions();
        $activeIds = $activeCampaigns->pluck('id')->all();
        $activeHarvests = $harvestRows
            ->filter(fn (array $harvest): bool => in_array($harvest['campaign_id'], $activeIds, true))
            ->values();
        $lastHarvest = $harvestRows->first();
        $latestCampaign = $allCampaigns
            ->sortByDesc(fn (Campaign $campaign): int => $campaign->actual_end_date?->timestamp
                ?? $campaign->start_date?->timestamp
                ?? 0)
            ->first();
        $latestCampaignHarvests = $latestCampaign !== null
            ? $harvestRows->filter(fn (array $harvest): bool => $harvest['campaign_id'] === $latestCampaign->id)
            : collect();
        $primaryCampaign = $activeCampaigns
            ->first(fn (Campaign $campaign): bool => $campaign->status === CampaignStatus::Ongoing)
            ?? $activeCampaigns->first();

        $stateCampaign = null;
        $reason = '';

        if ($blockedUntil !== null) {
            $key = 'blocked';
            $stateCampaign = $primaryCampaign;
            $reason = 'Un traitement phytosanitaire interdit la récolte jusqu’au '
                .$blockedUntil->format('d/m/Y').'.';
        } elseif ($activeCampaigns->isNotEmpty() && $activeHarvests->isNotEmpty()) {
            $key = 'harvesting';
            $stateCampaign = $activeCampaigns->firstWhere('id', $activeHarvests->first()['campaign_id'])
                ?? $primaryCampaign;
            $reason = 'Au moins une récolte est enregistrée sur une campagne active'
                .($nextPlannedHarvest !== null
                    ? ' ; prochaine récolte planifiée le '.$nextPlannedHarvest['date'].'.'
                    : '.');
        } elseif ($activeCampaigns->isNotEmpty() && $nextPlannedHarvest !== null) {
            $key = 'upcoming';
            $stateCampaign = $activeCampaigns->firstWhere('id', $nextPlannedHarvest['campaign_id'])
                ?? $primaryCampaign;
            $reason = 'Une récolte est planifiée le '.$nextPlannedHarvest['date'].' pour '
                .$nextPlannedHarvest['campaign'].'.';
        } elseif ($activeCampaigns->isNotEmpty()) {
            $key = 'growing';
            $stateCampaign = $primaryCampaign;
            $reason = 'La campagne est active, mais aucune récolte ni date de récolte n’est enregistrée.';
        } elseif ($latestCampaign !== null) {
            $stateCampaign = $latestCampaign;
            $key = match ($latestCampaign->status) {
                CampaignStatus::Completed => $latestCampaignHarvests->isNotEmpty()
                    ? 'completed'
                    : 'completed_without_harvest',
                CampaignStatus::Suspended => 'suspended',
                CampaignStatus::Cancelled => 'cancelled',
                default => 'not_set',
            };

            $reason = match ($key) {
                'completed' => 'La dernière campagne est terminée et une récolte est enregistrée.',
                'completed_without_harvest' => 'La dernière campagne est terminée sans récolte enregistrée.',
                'suspended' => 'La dernière campagne est suspendue ; sa récolte n’est pas considérée comme terminée.',
                'cancelled' => 'La dernière campagne est annulée.',
                default => $definitions[$key]['description'],
            };
        } else {
            $key = 'not_set';
            $reason = $definitions[$key]['description'];
        }

        $definition = $definitions[$key];

        return [
            'key' => $key,
            'label' => $definition['label'],
            'color' => $definition['color'],
            'description' => $definition['description'],
            'reason' => $reason,
            'campaign' => $stateCampaign !== null ? [
                'id' => $stateCampaign->id,
                'name' => $stateCampaign->name,
                'crop' => $stateCampaign->crop?->name ?? $stateCampaign->name,
                'status' => $stateCampaign->status->value,
                'status_label' => $stateCampaign->status->label(),
                'url' => route('campaigns.show', $stateCampaign),
            ] : null,
            'harvest_count' => $harvestRows->count(),
            'active_harvest_count' => $activeHarvests->count(),
            'last_harvested_at' => $lastHarvest['date'] ?? null,
            'last_harvest_quantity' => $lastHarvest['quantity'] ?? null,
            'last_harvest_unit' => $lastHarvest['unit'] ?? null,
            'next_planned_date' => $nextPlannedHarvest['date'] ?? null,
            'next_planned_campaign' => $nextPlannedHarvest['campaign'] ?? null,
        ];
    }

    /**
     * @return array<string, array{label: string, color: string, description: string}>
     */
    private function harvestStateDefinitions(): array
    {
        return [
            'blocked' => [
                'label' => 'Récolte bloquée',
                'color' => '#dc2626',
                'description' => 'Un traitement phytosanitaire interdit temporairement la récolte.',
            ],
            'harvesting' => [
                'label' => 'Récolte en cours',
                'color' => '#d97706',
                'description' => 'Une campagne active possède au moins une récolte enregistrée.',
            ],
            'upcoming' => [
                'label' => 'Récolte à venir',
                'color' => '#0284c7',
                'description' => 'Une récolte est planifiée sur une campagne active.',
            ],
            'growing' => [
                'label' => 'En culture',
                'color' => '#059669',
                'description' => 'La campagne est active, sans récolte ni date de récolte enregistrée.',
            ],
            'completed' => [
                'label' => 'Récolte terminée',
                'color' => '#65a30d',
                'description' => 'La dernière campagne est terminée avec une récolte enregistrée.',
            ],
            'completed_without_harvest' => [
                'label' => 'Terminée sans récolte',
                'color' => '#b45309',
                'description' => 'La dernière campagne est terminée sans récolte enregistrée.',
            ],
            'suspended' => [
                'label' => 'Campagne suspendue',
                'color' => '#ea580c',
                'description' => 'La campagne est suspendue ; la récolte n’est pas considérée comme terminée.',
            ],
            'cancelled' => [
                'label' => 'Campagne annulée',
                'color' => '#64748b',
                'description' => 'La dernière campagne est annulée.',
            ],
            'not_set' => [
                'label' => 'Non renseigné',
                'color' => '#94a3b8',
                'description' => 'Aucune campagne n’est rattachée à cette parcelle.',
            ],
        ];
    }

    /**
     * @param  Collection<string, int>  $counts
     * @return list<array{key: string, label: string, states: list<string>, count: int}>
     */
    private function harvestFilters(Collection $counts, int $units): array
    {
        $groups = [
            ['key' => 'all', 'label' => 'Toutes', 'states' => []],
            ['key' => 'to_follow', 'label' => 'Récolte à suivre', 'states' => ['blocked', 'harvesting', 'upcoming', 'growing']],
            ['key' => 'completed', 'label' => 'Terminées', 'states' => ['completed', 'completed_without_harvest']],
            ['key' => 'incomplete', 'label' => 'À compléter', 'states' => ['suspended', 'cancelled', 'not_set']],
        ];

        return collect($groups)
            ->map(function (array $group) use ($counts, $units): array {
                $count = $group['states'] === []
                    ? $units
                    : (int) collect($group['states'])->sum(fn (string $state): int => (int) ($counts[$state] ?? 0));

                return $group + ['count' => $count];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignRow(Campaign $campaign, CarbonInterface $now): array
    {
        $progress = null;

        if ($campaign->start_date !== null && $campaign->expected_end_date !== null) {
            $start = $campaign->start_date->timestamp;
            $end = $campaign->expected_end_date->timestamp;
            $duration = max(1, $end - $start);

            $progress = (int) round(max(0, min(1, ($now->timestamp - $start) / $duration)) * 100);
        }

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'crop' => $campaign->crop?->name,
            'variety' => $campaign->crop?->variety,
            'cycle_days' => $campaign->crop?->estimated_cycle_days,
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'start_date' => $campaign->start_date?->format('d/m/Y'),
            'expected_end_date' => $campaign->expected_end_date?->format('d/m/Y'),
            'progress' => $progress,
            'url' => route('campaigns.show', $campaign),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityRow(Activity $activity, Campaign $campaign): array
    {
        return [
            'id' => $activity->id,
            'title' => $activity->title,
            'date' => $activity->planned_date->format('d/m/Y'),
            'timestamp' => $activity->planned_date->timestamp,
            'type' => $activity->type->value,
            'type_label' => $activity->type->label(),
            'campaign' => $campaign->name,
            'url' => route('activities.show', $activity),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array{left: float, top: float, width: float, height: float}>
     */
    private function layout(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $items = $rows
            ->map(fn (array $row): array => [
                'id' => $row['unit']->id,
                'area' => max(1.0, (float) $row['unit']->area_m2),
            ])
            ->sortByDesc('area')
            ->values()
            ->all();

        $rectangles = [];
        $this->partition($items, 0.0, 0.0, self::MAP_WIDTH, self::MAP_HEIGHT, $rectangles);

        return collect($rectangles)
            ->map(fn (array $rectangle): array => [
                'left' => round($rectangle['x'], 3),
                'top' => round($rectangle['y'] / self::MAP_HEIGHT * 100, 3),
                'width' => round($rectangle['width'], 3),
                'height' => round($rectangle['height'] / self::MAP_HEIGHT * 100, 3),
            ])
            ->all();
    }

    /**
     * Découpe récursive d'un rectangle selon les surfaces. La plus grande
     * parcelle reste lisible et les proportions restent indicatives.
     *
     * @param  list<array{id: int, area: float}>  $items
     * @param  array<int, array{x: float, y: float, width: float, height: float}>  $rectangles
     */
    private function partition(array $items, float $x, float $y, float $width, float $height, array &$rectangles): void
    {
        $count = count($items);

        if ($count === 0) {
            return;
        }

        if ($count === 1) {
            $rectangles[$items[0]['id']] = [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
            ];

            return;
        }

        $total = max(1.0, array_sum(array_column($items, 'area')));
        $half = $total / 2;
        $accumulated = 0.0;
        $split = 1;

        foreach ($items as $index => $item) {
            $accumulated += $item['area'];

            if ($accumulated >= $half && $index < $count - 1) {
                $split = $index + 1;
                break;
            }
        }

        $first = array_slice($items, 0, $split);
        $second = array_slice($items, $split);
        $firstTotal = array_sum(array_column($first, 'area'));
        $share = $firstTotal / $total;

        if ($width >= $height) {
            $this->partition($first, $x, $y, $width * $share, $height, $rectangles);
            $this->partition($second, $x + $width * $share, $y, $width * (1 - $share), $height, $rectangles);

            return;
        }

        $this->partition($first, $x, $y, $width, $height * $share, $rectangles);
        $this->partition($second, $x, $y + $height * $share, $width, $height * (1 - $share), $rectangles);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<array{name: string, quantity: float, minimum_stock: float, unit: string}>  $criticalInputs
     * @param  list<array{label: string, color: string, kind: string}>  $legend
     * @return list<array<string, mixed>>
     */
    private function layers(Collection $rows, array $criticalInputs, array $legend): array
    {
        $cropRows = $rows->filter(fn (array $row): bool => $row['layers']['crops']['available']);
        $campaignRows = $rows->filter(fn (array $row): bool => $row['layers']['campaigns']['available']);
        $upcomingRows = $rows->filter(fn (array $row): bool => $row['layers']['upcoming_activities']['available']);
        $lateRows = $rows->filter(fn (array $row): bool => $row['layers']['late_activities']['available']);
        $harvestRows = $rows->filter(fn (array $row): bool => $row['layers']['harvests']['available']);
        $harvestStateRows = $rows->filter(fn (array $row): bool => $row['layers']['harvest_state']['available']);
        $alertRows = $rows->filter(fn (array $row): bool => $row['layers']['alerts']['available']);
        $irrigationRows = $rows->filter(fn (array $row): bool => $row['layers']['irrigation']['available']);
        $cropLegend = array_values(array_filter($legend, fn (array $entry): bool => $entry['kind'] === 'crop'));
        $harvestStateLegend = $harvestStateRows
            ->map(fn (array $row): array => [
                'label' => $row['harvest_state']['label'],
                'color' => $row['harvest_state']['color'],
                'kind' => 'harvest-state',
            ])
            ->unique('label')
            ->values()
            ->all();
        $campaignLegend = $campaignRows
            ->flatMap(fn (array $row): array => $row['campaigns'])
            ->map(fn (array $campaign): array => [
                'label' => $campaign['status_label'],
                'color' => $this->campaignStatusColor($campaign['status']),
                'kind' => 'campaign',
            ])
            ->unique('label')
            ->values()
            ->all();
        $statusLegend = $rows
            ->map(fn (array $row): array => [
                'label' => $row['unit']->status->label(),
                'color' => $this->statusColor($row['unit']->status),
                'kind' => 'status',
            ])
            ->unique('label')
            ->values()
            ->all();

        $alertReason = 'Aucune alerte de parcelle enregistrée.';
        if ($alertRows->isEmpty() && $criticalInputs !== []) {
            $alertReason = 'Aucune alerte rattachée à une parcelle ; les stocks critiques restent listés dans la vue d’ensemble.';
        }

        return [
            [
                'key' => 'crops',
                'label' => 'Cultures en cours',
                'description' => 'Culture principale et cultures suivies pour chaque parcelle.',
                'available' => $cropRows->isNotEmpty(),
                'reason' => $cropRows->isEmpty() ? 'Aucune campagne planifiée, en cours ou suspendue pour ces parcelles.' : null,
                'default' => true,
                'count' => $cropRows->count(),
                'legend' => $cropLegend,
            ],
            [
                'key' => 'upcoming_activities',
                'label' => 'Activités à venir',
                'description' => 'Activités prévues dont la date n’est pas dépassée, avec leur parcelle.',
                'available' => $upcomingRows->isNotEmpty(),
                'reason' => $upcomingRows->isEmpty() ? 'Aucune activité planifiée à venir pour les campagnes affichées.' : null,
                'default' => true,
                'count' => (int) $upcomingRows->sum(fn (array $row): int => $row['layers']['upcoming_activities']['count']),
                'legend' => [
                    ['label' => 'Activité planifiée', 'color' => '#0284c7', 'kind' => 'upcoming-activity'],
                ],
            ],
            [
                'key' => 'late_activities',
                'label' => 'Activités en retard',
                'description' => 'Activités encore prévues dont la date est dépassée.',
                'available' => $lateRows->isNotEmpty(),
                'reason' => $lateRows->isEmpty() ? 'Aucune activité en retard pour les campagnes affichées.' : null,
                'default' => true,
                'count' => (int) $lateRows->sum(fn (array $row): int => $row['layers']['late_activities']['count']),
                'legend' => [
                    ['label' => 'Activité en retard', 'color' => '#dc2626', 'kind' => 'late-activity'],
                ],
            ],
            [
                'key' => 'irrigation',
                'label' => 'Irrigation',
                'description' => 'Volumes relevés et quotas d’eau des campagnes affichées.',
                'available' => $irrigationRows->isNotEmpty(),
                'reason' => $irrigationRows->isEmpty() ? 'Aucun quota ni arrosage enregistré pour les campagnes affichées.' : null,
                'default' => true,
                'count' => $irrigationRows->count(),
                'legend' => [
                    ['label' => 'Sous le seuil', 'color' => '#0ea5e9', 'kind' => 'irrigation-ok'],
                    ['label' => 'Seuil d’alerte atteint', 'color' => '#f59e0b', 'kind' => 'irrigation-approaching'],
                    ['label' => 'Quota dépassé', 'color' => '#dc2626', 'kind' => 'irrigation-exceeded'],
                    ['label' => 'Volume relevé sans quota', 'color' => '#64748b', 'kind' => 'irrigation-neutral'],
                ],
            ],
            [
                'key' => 'alerts',
                'label' => 'Alertes',
                'description' => 'Synthèse des activités en retard et des récoltes bloquées par un traitement.',
                'available' => $alertRows->isNotEmpty(),
                'reason' => $alertRows->isEmpty() ? $alertReason : null,
                'default' => true,
                'count' => (int) $alertRows->sum(fn (array $row): int => $row['layers']['alerts']['count']),
                'legend' => [
                    ['label' => 'Alerte de parcelle', 'color' => '#dc2626', 'kind' => 'alert'],
                ],
            ],
            [
                'key' => 'campaigns',
                'label' => 'Campagnes',
                'description' => 'Campagnes planifiées, en cours ou suspendues rattachées aux parcelles.',
                'available' => $campaignRows->isNotEmpty(),
                'reason' => $campaignRows->isEmpty() ? 'Aucune campagne planifiée, en cours ou suspendue pour ces parcelles.' : null,
                'default' => false,
                'count' => (int) $campaignRows->sum(fn (array $row): int => $row['layers']['campaigns']['count']),
                'legend' => $campaignLegend,
            ],
            [
                'key' => 'harvest_state',
                'label' => 'États de récolte',
                'description' => 'État calculé à partir des campagnes, récoltes enregistrées et récoltes planifiées.',
                'available' => $harvestStateRows->isNotEmpty(),
                'reason' => $harvestStateRows->isEmpty() ? 'Aucune parcelle à qualifier pour la récolte.' : null,
                'default' => true,
                'count' => $harvestStateRows->count(),
                'legend' => $harvestStateLegend,
            ],
            [
                'key' => 'harvests',
                'label' => 'Récoltes',
                'description' => 'Récoltes enregistrées pour toutes les campagnes rattachées, avec leur unité d’origine.',
                'available' => $harvestRows->isNotEmpty(),
                'reason' => $harvestRows->isEmpty() ? 'Aucune récolte enregistrée pour ces parcelles.' : null,
                'default' => false,
                'count' => (int) $harvestRows->sum(fn (array $row): int => $row['layers']['harvests']['count']),
                'legend' => [
                    ['label' => 'Récolte enregistrée', 'color' => '#65a30d', 'kind' => 'harvest'],
                ],
            ],
            [
                'key' => 'status',
                'label' => 'État des parcelles',
                'description' => 'Disponibilité, occupation, repos ou inactivité enregistrés.',
                'available' => $rows->isNotEmpty(),
                'reason' => $rows->isEmpty() ? 'Aucune parcelle enregistrée pour afficher un état.' : null,
                'default' => false,
                'count' => $rows->count(),
                'legend' => $statusLegend,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, color: string, kind: string}>
     */
    private function legend(Collection $rows): array
    {
        return $rows
            ->map(fn (array $row): array => [
                'label' => $row['legend_label'],
                'color' => $row['color'],
                'kind' => $row['legend_kind'],
            ])
            ->unique('label')
            ->values()
            ->all();
    }

    private function cropColor(string $crop): string
    {
        $index = abs(crc32(mb_strtolower($crop))) % count(self::CROP_COLORS);

        return self::CROP_COLORS[$index];
    }

    private function statusColor(ProductionUnitStatus $status): string
    {
        return match ($status) {
            ProductionUnitStatus::Available => '#0f766e',
            ProductionUnitStatus::Occupied => '#047857',
            ProductionUnitStatus::Resting => '#b45309',
            ProductionUnitStatus::Inactive => '#64748b',
        };
    }

    private function campaignStatusColor(string $status): string
    {
        return match ($status) {
            CampaignStatus::Planned->value => '#0284c7',
            CampaignStatus::Ongoing->value => '#059669',
            CampaignStatus::Suspended->value => '#d97706',
            CampaignStatus::Completed->value => '#475569',
            CampaignStatus::Cancelled->value => '#64748b',
            default => '#0284c7',
        };
    }

    private function shade(string $hex, float $factor): string
    {
        $hex = ltrim($hex, '#');
        $channels = array_map('hexdec', str_split($hex, 2));

        $channels = array_map(
            fn (float|int $channel): int => (int) max(0, min(255, round($channel * $factor))),
            $channels,
        );

        return sprintf('#%02x%02x%02x', ...$channels);
    }
}
