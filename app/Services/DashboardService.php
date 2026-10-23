<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\CampaignStatus;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\Input;
use App\Models\ProduceLot;
use App\Models\ProductionUnit;
use App\Models\Revenue;
use App\Models\Sale;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\Cache;

/**
 * Agrégats du tableau de bord, mis en cache par exploitation et par filtre.
 *
 * Indicateurs calculés : marge simplifiée, rendement, taux de réalisation,
 * retards, stock critique. Aucune information n'est lue hors de l'exploitation
 * transmise en paramètre.
 *
 * Le cache ne contient que des valeurs scalaires : les objets Eloquent ne sont
 * pas sérialisés, ce qui évite de dépendre de la liste blanche de classes
 * désérialisables du cache (`cache.serializable_classes`).
 */
class DashboardService
{
    /**
     * @param  array{from?: ?string, to?: ?string, campaign_id?: ?int}  $filters
     * @return array<string, mixed>
     */
    public function forFarm(Farm $farm, array $filters = []): array
    {
        $key = sprintf(
            'dashboard.farm.%d.v%d.%s',
            $farm->id,
            DashboardCache::version($farm),
            md5(json_encode($filters)),
        );

        return Cache::remember($key, now()->addMinutes(10), fn () => $this->compute($farm, $filters));
    }

    /**
     * @param  array{from?: ?string, to?: ?string, campaign_id?: ?int}  $filters
     * @return array<string, mixed>
     */
    private function compute(Farm $farm, array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $campaignId = $filters['campaign_id'] ?? null;

        $expenses = (float) Expense::query()->forFarm($farm->id)
            ->whereBetween('expense_date', [$from, $to])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->sum('amount');

        $revenues = (float) Revenue::query()->forFarm($farm->id)
            ->whereBetween('revenue_date', [$from, $to])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->sum('amount');

        $activeCampaigns = Campaign::query()->forFarm($farm->id)
            ->whereIn('status', [CampaignStatus::Planned->value, CampaignStatus::Ongoing->value])
            ->count();

        $activitiesQuery = Activity::query()->forFarm($farm->id)
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId));

        $totalActivities = (clone $activitiesQuery)
            ->where('status', '<>', ActivityStatus::Cancelled->value)
            ->count();

        $completedActivities = (clone $activitiesQuery)
            ->where('status', ActivityStatus::Completed->value)
            ->count();

        $lateActivities = (clone $activitiesQuery)->overdue()->count();

        $harvestsQuery = Harvest::query()->forFarm($farm->id)
            ->whereBetween('harvested_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId));

        $harvestedQuantity = (float) (clone $harvestsQuery)->sum('quantity');

        $harvestedAreaM2 = (float) Campaign::query()->forFarm($farm->id)
            ->when($campaignId, fn ($query) => $query->whereKey($campaignId))
            ->whereIn('id', (clone $harvestsQuery)->select('campaign_id'))
            ->sum('cultivated_area_m2');

        $salesQuery = Sale::query()->forFarm($farm->id)
            ->confirmed()
            ->whereBetween('sale_date', [$from, $to])
            ->when($campaignId, fn ($query) => $query->whereHas(
                'items.produceLot',
                fn ($query) => $query->where('campaign_id', $campaignId),
            ));

        $salesAmount = (float) (clone $salesQuery)->sum('total_amount');
        $salesPaid = (float) (clone $salesQuery)->sum('amount_paid');

        return [
            'period' => ['from' => $from, 'to' => $to],
            'expenses' => $expenses,
            'revenues' => $revenues,
            'margin' => round($revenues - $expenses, 2),
            'active_campaigns' => $activeCampaigns,
            'parcels' => ProductionUnit::query()->forFarm($farm->id)->count(),
            'area_m2' => (float) ProductionUnit::query()->forFarm($farm->id)->sum('area_m2'),
            'critical_inputs' => Input::query()->forFarm($farm->id)
                ->whereColumn('quantity', '<', 'minimum_stock')
                ->orderBy('name')
                ->get(['name', 'quantity', 'minimum_stock'])
                ->map(fn (Input $input): array => [
                    'name' => $input->name,
                    'quantity' => (float) $input->quantity,
                    'minimum_stock' => (float) $input->minimum_stock,
                ])
                ->all(),
            'late_activities' => $lateActivities,
            'completion_rate' => $totalActivities > 0
                ? round($completedActivities / $totalActivities * 100, 1)
                : null,
            'harvested_quantity' => $harvestedQuantity,
            'yield_kg_per_ha' => $harvestedAreaM2 > 0
                ? round($harvestedQuantity / ($harvestedAreaM2 / 10000), 1)
                : null,
            'produce_available' => (float) ProduceLot::query()->forFarm($farm->id)
                ->sum('available_quantity'),
            'produce_lots_available' => ProduceLot::query()->forFarm($farm->id)
                ->available()
                ->count(),
            'sales_amount' => $salesAmount,
            'sales_count' => (clone $salesQuery)->count(),
            'sales_outstanding' => round($salesAmount - $salesPaid, 2),
            'recent_activities' => Activity::query()->forFarm($farm->id)
                ->with('campaign:id,name')
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->orderByDesc('planned_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'campaign_name' => $activity->campaign?->name,
                    'planned_date' => $activity->planned_date->format('d/m/Y'),
                    'status_value' => $activity->status->value,
                    'status_label' => $activity->status->label(),
                    'is_late' => $activity->isLate(),
                ])
                ->all(),
        ];
    }
}
