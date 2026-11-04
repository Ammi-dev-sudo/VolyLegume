<?php

namespace App\Services;

use App\Enums\ActivityStatus;
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
                    ->whereIn('status', [
                        CampaignStatus::Planned->value,
                        CampaignStatus::Ongoing->value,
                        CampaignStatus::Suspended->value,
                    ])
                    ->with([
                        'crop:id,name,variety,estimated_cycle_days',
                        'activities' => fn ($activities) => $activities
                            ->where('status', ActivityStatus::Planned->value)
                            ->orderBy('planned_date'),
                        'harvests' => fn ($harvests) => $harvests->orderByDesc('harvested_at'),
                        'treatments' => fn ($treatments) => $treatments
                            ->where('applied_at', '<=', $now)
                            ->orderByDesc('minimum_harvest_at'),
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

        return [
            'units' => $rows->all(),
            'legend' => $this->legend($rows),
            'stats' => [
                'units' => $rows->count(),
                'area_m2' => round((float) $units->sum(fn (ProductionUnit $unit): float => (float) $unit->area_m2), 2),
                'active_campaigns' => (int) $rows->sum(fn (array $row): int => count($row['campaigns'])),
                'overdue_activities' => $overdue,
                'blocked_harvests' => $blocked,
                'critical_inputs' => count($criticalInputs),
                'with_boundary' => $mapped->count(),
                'without_boundary' => $rows->count() - $mapped->count(),
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
                    'detail_target' => 'plot-detail-'.$row['unit']->id,
                    'edit_url' => $row['can_edit'] ? $row['boundary_url'] : null,
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
        $campaigns = $unit->campaigns;

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

        $nextActivities = $campaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->activities
                ->reject(fn (Activity $activity): bool => $activity->isLate($now))
                ->map(fn (Activity $activity): array => $this->activityRow($activity, $campaign)))
            ->sortBy('timestamp')
            ->take(4)
            ->values();

        $harvests = $campaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->harvests
                ->map(fn ($harvest): array => [
                    'id' => $harvest->id,
                    'date' => $harvest->harvested_at->format('d/m/Y'),
                    'timestamp' => $harvest->harvested_at->timestamp,
                    'quantity' => (float) $harvest->quantity,
                    'unit' => $harvest->unit,
                    'campaign' => $campaign->name,
                ]))
            ->sortByDesc('timestamp')
            ->take(4)
            ->values();

        $blockedUntil = $campaigns
            ->flatMap(fn (Campaign $campaign) => $campaign->treatments)
            ->map(fn ($treatment) => $treatment->minimum_harvest_at)
            ->filter(fn ($date) => $date !== null && $date->gt($now))
            ->sortDesc()
            ->first();

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
            'next_activities' => $nextActivities->all(),
            'harvests' => $harvests->all(),
            'harvest_total' => round((float) $campaigns
                ->flatMap(fn (Campaign $campaign) => $campaign->harvests)
                ->sum(fn ($harvest): float => (float) $harvest->quantity), 3),
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
        ];
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
