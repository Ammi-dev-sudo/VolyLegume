<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\CropIssue;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\Input;
use App\Models\ProduceLot;
use App\Models\ProductionUnit;
use App\Models\Revenue;
use App\Models\Sale;
use App\Support\DashboardCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Agrégats du tableau de bord, mis en cache par exploitation et par filtre.
 *
 * Indicateurs calculés : marge simplifiée, rendement, taux de réalisation,
 * retards, stock critique, séries mensuelles et répartitions par culture.
 * Aucune information n'est lue hors de l'exploitation transmise en paramètre.
 *
 * Le cache ne contient que des valeurs scalaires : les objets Eloquent ne sont
 * pas sérialisés, ce qui évite de dépendre de la liste blanche de classes
 * désérialisables du cache (`cache.serializable_classes`).
 */
class DashboardService
{
    /**
     * @param  array{period?: ?string, from?: ?string, to?: ?string, campaign_id?: ?int}  $filters
     * @return array<string, mixed>
     */
    public function forFarm(Farm $farm, array $filters = [], bool $includeFinances = true): array
    {
        $cachePayload = $filters + ['include_finances' => $includeFinances];

        $key = sprintf(
            'dashboard.farm.%d.v%d.%s',
            $farm->id,
            DashboardCache::version($farm),
            md5((string) json_encode($cachePayload)),
        );

        return Cache::remember(
            $key,
            now()->addMinutes(10),
            fn () => $this->compute($farm, $filters, $includeFinances),
        );
    }

    /**
     * @param  array{period?: ?string, from?: ?string, to?: ?string, campaign_id?: ?int}  $filters
     * @return array<string, mixed>
     */
    private function compute(Farm $farm, array $filters, bool $includeFinances): array
    {
        $from = $filters['from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $campaignId = $filters['campaign_id'] ?? null;

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();
        $chartStart = $toDate->copy()->subMonths(11)->startOfMonth();
        $months = collect(range(0, 11))
            ->map(fn (int $offset): Carbon => $chartStart->copy()->addMonthsNoOverflow($offset));

        $expenses = $includeFinances
            ? (float) Expense::query()->forFarm($farm->id)
                ->whereBetween('expense_date', [$from, $to])
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->sum('amount')
            : 0.0;

        $revenues = $includeFinances
            ? (float) Revenue::query()->forFarm($farm->id)
                ->whereBetween('revenue_date', [$from, $to])
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->sum('amount')
            : 0.0;

        $activeCampaignsQuery = Campaign::query()->forFarm($farm->id)
            ->active()
            ->when($campaignId, fn ($query) => $query->whereKey($campaignId));

        $activeCampaigns = (clone $activeCampaignsQuery)->count();
        $activeCrops = (clone $activeCampaignsQuery)->distinct()->count('crop_id');
        $cultivatedAreaM2 = (float) (clone $activeCampaignsQuery)->sum('cultivated_area_m2');

        $plannedHarvests = (clone $activeCampaignsQuery)
            ->whereBetween('expected_end_date', [$from, $to])
            ->count();

        $upcomingHarvests = (clone $activeCampaignsQuery)
            ->whereBetween('expected_end_date', [today()->toDateString(), today()->addDays(30)->toDateString()])
            ->with(['crop:id,name', 'productionUnit:id,name'])
            ->orderBy('expected_end_date')
            ->limit(6)
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'crop' => $campaign->crop?->name,
                'parcel' => $campaign->productionUnit?->name,
                'expected_date' => $campaign->expected_end_date?->format('d/m/Y'),
                'days_left' => $campaign->expected_end_date !== null
                    ? max(0, (int) today()->diffInDays($campaign->expected_end_date, false))
                    : null,
                'area_m2' => (float) $campaign->cultivated_area_m2,
            ])
            ->all();

        $activitiesQuery = Activity::query()->forFarm($farm->id)
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId));

        $totalActivities = (clone $activitiesQuery)
            ->where('status', '<>', ActivityStatus::Cancelled->value)
            ->count();

        $completedActivities = (clone $activitiesQuery)
            ->where('status', ActivityStatus::Completed->value)
            ->count();

        $lateActivities = (clone $activitiesQuery)->overdue()->count();

        $criticalActivities = (clone $activitiesQuery)
            ->where('status', ActivityStatus::Planned->value)
            ->whereDate('planned_date', '<', today()->subDays(3))
            ->count();

        $harvestsQuery = Harvest::query()->forFarm($farm->id)
            ->whereBetween('harvested_at', [$fromDate, $toDate])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId));

        $harvestedQuantity = (float) (clone $harvestsQuery)->sum('quantity');
        $harvestedCount = (clone $harvestsQuery)->count();

        $harvestUnits = (clone $harvestsQuery)
            ->selectRaw('unit, SUM(quantity) as total')
            ->groupBy('unit')
            ->pluck('total', 'unit')
            ->map(fn ($total): float => round((float) $total, 2))
            ->all();

        $productionByCrop = (clone $harvestsQuery)
            ->join('campaigns', 'campaigns.id', '=', 'harvests.campaign_id')
            ->join('crops', 'crops.id', '=', 'campaigns.crop_id')
            ->selectRaw('crops.name as crop, harvests.unit as unit, SUM(harvests.quantity) as total')
            ->groupBy('crops.name', 'harvests.unit')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'crop' => (string) $row->crop,
                'unit' => (string) $row->unit,
                'quantity' => round((float) $row->total, 2),
            ])
            ->all();

        $harvestedAreaM2 = (float) Campaign::query()->forFarm($farm->id)
            ->when($campaignId, fn ($query) => $query->whereKey($campaignId))
            ->whereIn('id', (clone $harvestsQuery)->select('campaign_id'))
            ->sum('cultivated_area_m2');

        $cropDistribution = Campaign::query()->forFarm($farm->id)
            ->active()
            ->when($campaignId, fn ($query) => $query->whereKey($campaignId))
            ->join('crops', 'crops.id', '=', 'campaigns.crop_id')
            ->selectRaw('crops.name as crop, SUM(campaigns.cultivated_area_m2) as total')
            ->groupBy('crops.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'crop' => (string) $row->crop,
                'area_m2' => round((float) $row->total, 2),
            ])
            ->all();

        $salesQuery = Sale::query()->forFarm($farm->id)
            ->confirmed()
            ->whereBetween('sale_date', [$from, $to])
            ->when($campaignId, fn ($query) => $query->whereHas(
                'items.produceLot',
                fn ($query) => $query->where('campaign_id', $campaignId),
            ));

        $salesAmount = $includeFinances ? (float) (clone $salesQuery)->sum('total_amount') : 0.0;
        $salesPaid = $includeFinances ? (float) (clone $salesQuery)->sum('amount_paid') : 0.0;

        $criticalInputs = Input::query()->forFarm($farm->id)
            ->whereColumn('quantity', '<', 'minimum_stock')
            ->orderBy('name')
            ->get(['name', 'quantity', 'minimum_stock'])
            ->map(fn (Input $input): array => [
                'name' => $input->name,
                'quantity' => (float) $input->quantity,
                'minimum_stock' => (float) $input->minimum_stock,
            ])
            ->all();

        $issuesQuery = CropIssue::query()->forFarm($farm->id)
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId));

        $openIssues = (clone $issuesQuery)->where('status', '<>', IssueStatus::Resolved->value);
        $openIssuesCount = (clone $openIssues)->count();
        $criticalIssues = (clone $openIssues)->where('severity', IssueSeverity::Critical->value)->count();

        $recentIssues = (clone $issuesQuery)
            ->with(['productionUnit:id,code,name', 'campaign:id,name'])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (CropIssue $issue): array => [
                'id' => $issue->id,
                'reference' => $issue->reference,
                'title' => $issue->title,
                'type' => $issue->type->label(),
                'severity_value' => $issue->severity->value,
                'severity_label' => $issue->severity->label(),
                'status_value' => $issue->status->value,
                'status_label' => $issue->status->label(),
                'observed_at' => $issue->observed_at?->format('d/m/Y'),
                'parcel' => $issue->productionUnit?->code,
                'campaign' => $issue->campaign?->name,
            ])
            ->all();

        $expensesByMonth = [];
        $revenuesByMonth = [];
        $harvestsByMonth = [];
        $activitiesByMonth = [];
        $completedByMonth = [];

        if ($includeFinances) {
            $expenseRows = Expense::query()->forFarm($farm->id)
                ->whereBetween('expense_date', [$chartStart->toDateString(), $toDate->toDateString()])
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->get(['expense_date', 'amount']);

            foreach ($expenseRows as $row) {
                $key = Carbon::parse($row->expense_date)->format('Y-m');
                $expensesByMonth[$key] = ($expensesByMonth[$key] ?? 0) + (float) $row->amount;
            }

            $revenueRows = Revenue::query()->forFarm($farm->id)
                ->whereBetween('revenue_date', [$chartStart->toDateString(), $toDate->toDateString()])
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->get(['revenue_date', 'amount']);

            foreach ($revenueRows as $row) {
                $key = Carbon::parse($row->revenue_date)->format('Y-m');
                $revenuesByMonth[$key] = ($revenuesByMonth[$key] ?? 0) + (float) $row->amount;
            }
        }

        $harvestRows = Harvest::query()->forFarm($farm->id)
            ->whereBetween('harvested_at', [$chartStart, $toDate])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->get(['harvested_at', 'quantity']);

        foreach ($harvestRows as $row) {
            $key = Carbon::parse($row->harvested_at)->format('Y-m');
            $harvestsByMonth[$key] = ($harvestsByMonth[$key] ?? 0) + (float) $row->quantity;
        }

        $activityRows = Activity::query()->forFarm($farm->id)
            ->where('status', '<>', ActivityStatus::Cancelled->value)
            ->whereBetween('planned_date', [$chartStart->toDateString(), $toDate->toDateString()])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->get(['planned_date', 'status']);

        foreach ($activityRows as $row) {
            $key = Carbon::parse($row->planned_date)->format('Y-m');
            $activitiesByMonth[$key] = ($activitiesByMonth[$key] ?? 0) + 1;

            if ($row->status === ActivityStatus::Completed) {
                $completedByMonth[$key] = ($completedByMonth[$key] ?? 0) + 1;
            }
        }

        $monthlyFinance = $includeFinances
            ? $months->map(fn (Carbon $month): array => [
                'label' => $month->format('m/y'),
                'full_label' => $month->format('m/Y'),
                'key' => $month->format('Y-m'),
                'expenses' => round($expensesByMonth[$month->format('Y-m')] ?? 0, 2),
                'revenues' => round($revenuesByMonth[$month->format('Y-m')] ?? 0, 2),
            ])->all()
            : [];

        $harvestTrend = $months->map(fn (Carbon $month): array => [
            'label' => $month->format('m/y'),
            'full_label' => $month->format('m/Y'),
            'value' => round($harvestsByMonth[$month->format('Y-m')] ?? 0, 2),
        ])->all();

        $activityTrend = $months->map(fn (Carbon $month): array => [
            'label' => $month->format('m/y'),
            'full_label' => $month->format('m/Y'),
            'planned' => (int) ($activitiesByMonth[$month->format('Y-m')] ?? 0),
            'completed' => (int) ($completedByMonth[$month->format('Y-m')] ?? 0),
        ])->all();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'finance_visible' => $includeFinances,
            'expenses' => $expenses,
            'revenues' => $revenues,
            'margin' => round($revenues - $expenses, 2),
            'active_campaigns' => $activeCampaigns,
            'active_crops' => $activeCrops,
            'cultivated_area_m2' => round($cultivatedAreaM2, 2),
            'planned_harvests' => $plannedHarvests,
            'upcoming_harvests' => $upcomingHarvests,
            'parcels' => ProductionUnit::query()->forFarm($farm->id)->count(),
            'area_m2' => (float) ProductionUnit::query()->forFarm($farm->id)->sum('area_m2'),
            'critical_inputs' => $criticalInputs,
            'low_stock_count' => count($criticalInputs),
            'open_issues' => $openIssuesCount,
            'critical_issues' => $criticalIssues,
            'recent_issues' => $recentIssues,
            'inputs_total' => Input::query()->forFarm($farm->id)->count(),
            'late_activities' => $lateActivities,
            'critical_activities' => $criticalActivities,
            'completion_rate' => $totalActivities > 0
                ? round($completedActivities / $totalActivities * 100, 1)
                : null,
            'harvested_quantity' => $harvestedQuantity,
            'harvested_count' => $harvestedCount,
            'harvest_units' => $harvestUnits,
            'production_by_crop' => $productionByCrop,
            'crop_distribution' => $cropDistribution,
            'monthly_finance' => $monthlyFinance,
            'harvest_trend' => $harvestTrend,
            'activity_trend' => $activityTrend,
            'yield_kg_per_ha' => $harvestedAreaM2 > 0
                ? round($harvestedQuantity / ($harvestedAreaM2 / 10000), 1)
                : null,
            'produce_available' => (float) ProduceLot::query()->forFarm($farm->id)
                ->sum('available_quantity'),
            'produce_lots_available' => ProduceLot::query()->forFarm($farm->id)
                ->available()
                ->count(),
            'sales_amount' => $salesAmount,
            'sales_count' => $includeFinances ? (clone $salesQuery)->count() : 0,
            'sales_outstanding' => $includeFinances ? round($salesAmount - $salesPaid, 2) : 0.0,
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
                    'is_critical' => $activity->isCriticallyLate(),
                    'days_late' => $activity->daysLate(),
                ])
                ->all(),
        ];
    }
}
