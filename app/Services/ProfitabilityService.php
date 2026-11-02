<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\IrrigationEvent;
use App\Models\Revenue;
use Illuminate\Support\Collection;

/**
 * Indicateurs de rentabilité par campagne, par culture et par parcelle.
 *
 * Les montants proviennent des dépenses et recettes de la campagne et les
 * volumes des récoltes et des irrigations. Toutes les requêtes sont bornées à
 * l'exploitation transmise : aucune donnée d'une autre exploitation ne peut
 * entrer dans les agrégats.
 *
 * Les surfaces sont des surfaces cultivées cumulées : une parcelle qui accueille
 * deux campagnes successives apparaît avec la somme de leurs surfaces.
 */
class ProfitabilityService
{
    /**
     * @param  array{from?: ?string, to?: ?string, campaign_id?: ?int}  $filters
     * @return array{
     *     period: array{from: ?string, to: ?string},
     *     campaigns: list<array<string, mixed>>,
     *     crops: list<array<string, mixed>>,
     *     parcels: list<array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function forFarm(Farm $farm, array $filters = []): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $campaignId = $filters['campaign_id'] ?? null;

        $campaigns = Campaign::query()
            ->forFarm($farm->id)
            ->where('status', '<>', CampaignStatus::Cancelled->value)
            ->with(['crop:id,name', 'productionUnit:id,code,name,area_m2'])
            ->when($campaignId, fn ($query) => $query->whereKey($campaignId))
            ->when($from, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('expected_end_date')
                ->orWhereDate('expected_end_date', '>=', $from)))
            ->when($to, fn ($query) => $query->whereDate('start_date', '<=', $to))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $expenses = $this->sumByCampaign(
            Expense::query()->forFarm($farm->id)->whereNotNull('campaign_id'),
            'expense_date',
            'amount',
            $from,
            $to,
            $campaignId,
        );

        $revenues = $this->sumByCampaign(
            Revenue::query()->forFarm($farm->id)->whereNotNull('campaign_id'),
            'revenue_date',
            'amount',
            $from,
            $to,
            $campaignId,
        );

        $harvests = $this->sumByCampaign(
            Harvest::query()->forFarm($farm->id),
            'harvested_at',
            'quantity',
            $from,
            $to,
            $campaignId,
        );

        $water = $this->sumByCampaign(
            IrrigationEvent::query()->forFarm($farm->id)->whereNotNull('campaign_id'),
            'irrigated_at',
            'volume_m3',
            $from,
            $to,
            $campaignId,
        );

        $rows = $campaigns->map(function (Campaign $campaign) use ($expenses, $revenues, $harvests, $water): array {
            $area = (float) $campaign->cultivated_area_m2;
            $expenseTotal = $expenses->get($campaign->id, 0.0);
            $revenueTotal = $revenues->get($campaign->id, 0.0);
            $harvested = $harvests->get($campaign->id, 0.0);
            $waterVolume = $water->get($campaign->id, 0.0);
            $margin = $revenueTotal - $expenseTotal;
            $budget = $campaign->planned_budget !== null ? (float) $campaign->planned_budget : null;

            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'crop' => $campaign->crop?->name ?? 'Culture non renseignée',
                'parcel' => $campaign->productionUnit?->name ?? 'Parcelle non renseignée',
                'parcel_code' => $campaign->productionUnit?->code,
                'area_m2' => $area,
                'start_date' => $campaign->start_date?->format('d/m/Y'),
                'status' => $campaign->status->label(),
                'expenses' => round($expenseTotal, 2),
                'revenues' => round($revenueTotal, 2),
                'margin' => round($margin, 2),
                'margin_per_m2' => $area > 0 ? round($margin / $area, 2) : null,
                'harvest_kg' => round($harvested, 3),
                'yield_kg_per_m2' => $area > 0 ? round($harvested / $area, 3) : null,
                'yield_kg_per_ha' => $area > 0 ? round($harvested / ($area / 10000), 1) : null,
                'water_m3' => round($waterVolume, 3),
                'water_per_kg' => $harvested > 0 ? round($waterVolume / $harvested, 3) : null,
                'budget' => $budget,
                'budget_usage_percent' => $budget !== null && $budget > 0
                    ? round($expenseTotal / $budget * 100, 1)
                    : null,
            ];
        })->values()->all();

        $crops = $this->aggregate($rows, 'crop');
        $parcels = $this->aggregate($rows, 'parcel');

        return [
            'period' => ['from' => $from, 'to' => $to],
            'campaigns' => $rows,
            'crops' => $crops,
            'parcels' => $parcels,
            'totals' => $this->totals($rows),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @return Collection<int, float>
     */
    private function sumByCampaign($query, string $dateColumn, string $sumColumn, ?string $from, ?string $to, ?int $campaignId): Collection
    {
        return $query
            ->when($from, fn ($query) => $query->whereDate($dateColumn, '>=', $from))
            ->when($to, fn ($query) => $query->whereDate($dateColumn, '<=', $to))
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->selectRaw('campaign_id, SUM('.$sumColumn.') as aggregate')
            ->groupBy('campaign_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->campaign_id => (float) $row->aggregate]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function aggregate(array $rows, string $groupKey): array
    {
        return collect($rows)
            ->groupBy($groupKey)
            ->map(function (Collection $group, string $label): array {
                $area = (float) $group->sum('area_m2');
                $expenses = (float) $group->sum('expenses');
                $revenues = (float) $group->sum('revenues');
                $harvest = (float) $group->sum('harvest_kg');
                $water = (float) $group->sum('water_m3');
                $margin = $revenues - $expenses;

                return [
                    'label' => $label,
                    'campaigns' => $group->count(),
                    'area_m2' => round($area, 2),
                    'expenses' => round($expenses, 2),
                    'revenues' => round($revenues, 2),
                    'margin' => round($margin, 2),
                    'margin_per_m2' => $area > 0 ? round($margin / $area, 2) : null,
                    'harvest_kg' => round($harvest, 3),
                    'yield_kg_per_m2' => $area > 0 ? round($harvest / $area, 3) : null,
                    'yield_kg_per_ha' => $area > 0 ? round($harvest / ($area / 10000), 1) : null,
                    'water_m3' => round($water, 3),
                    'water_per_kg' => $harvest > 0 ? round($water / $harvest, 3) : null,
                ];
            })
            ->sortByDesc('margin')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows): array
    {
        $area = (float) collect($rows)->sum('area_m2');
        $expenses = (float) collect($rows)->sum('expenses');
        $revenues = (float) collect($rows)->sum('revenues');
        $harvest = (float) collect($rows)->sum('harvest_kg');
        $water = (float) collect($rows)->sum('water_m3');
        $margin = $revenues - $expenses;

        return [
            'campaigns' => count($rows),
            'area_m2' => round($area, 2),
            'expenses' => round($expenses, 2),
            'revenues' => round($revenues, 2),
            'margin' => round($margin, 2),
            'margin_per_m2' => $area > 0 ? round($margin / $area, 2) : null,
            'harvest_kg' => round($harvest, 3),
            'yield_kg_per_m2' => $area > 0 ? round($harvest / $area, 3) : null,
            'yield_kg_per_ha' => $area > 0 ? round($harvest / ($area / 10000), 1) : null,
            'water_m3' => round($water, 3),
            'water_per_kg' => $harvest > 0 ? round($water / $harvest, 3) : null,
        ];
    }
}
