<?php

namespace App\Services;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Campaign;
use App\Models\CropIssue;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\PhytosanitaryTreatment;
use App\Models\Revenue;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Construit les données consolidées d'un rapport d'exploitation.
 *
 * Toutes les requêtes sont bornées à l'exploitation transmise et à la période
 * demandée. Les montants financiers ne sont renseignés que si l'utilisateur y
 * est autorisé ; aucun rapport ne contourne les permissions de l'application.
 */
class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Farm $farm, Carbon $from, Carbon $to, ?int $campaignId = null, bool $includeFinances = true): array
    {
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();

        $campaigns = Campaign::query()
            ->forFarm($farm->id)
            ->with(['crop:id,name,variety', 'productionUnit:id,code,name', 'zone:id,code,name'])
            ->withCount(['activities', 'treatments', 'harvests', 'issues'])
            ->when($campaignId, fn ($query) => $query->whereKey($campaignId))
            ->whereDate('start_date', '<=', $toDate->toDateString())
            ->where(fn ($query) => $query
                ->whereDate('expected_end_date', '>=', $fromDate->toDateString())
                ->orWhereNull('expected_end_date'))
            ->orderBy('start_date')
            ->get();

        $issues = CropIssue::query()
            ->forFarm($farm->id)
            ->with(['productionUnit:id,code,name', 'campaign:id,name', 'reportedBy:id,name'])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->whereBetween('observed_at', [$fromDate->toDateString(), $toDate->toDateString()])
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();

        $harvests = Harvest::query()
            ->forFarm($farm->id)
            ->with(['campaign:id,name,crop_id', 'campaign.crop:id,name'])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->whereBetween('harvested_at', [$fromDate, $toDate])
            ->orderBy('harvested_at')
            ->get();

        $treatments = PhytosanitaryTreatment::query()
            ->forFarm($farm->id)
            ->with(['campaign:id,name', 'input:id,name'])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->whereBetween('applied_at', [$fromDate, $toDate])
            ->orderBy('applied_at')
            ->get();

        $expenses = $includeFinances
            ? Expense::query()->forFarm($farm->id)
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->whereBetween('expense_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->orderBy('expense_date')
                ->get()
            : collect();

        $revenues = $includeFinances
            ? Revenue::query()->forFarm($farm->id)
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->whereBetween('revenue_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->orderBy('revenue_date')
                ->get()
            : collect();

        $sales = $includeFinances
            ? Sale::query()->forFarm($farm->id)
                ->confirmed()
                ->whereBetween('sale_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->when($campaignId, fn ($query) => $query->whereHas(
                    'items.produceLot',
                    fn ($query) => $query->where('campaign_id', $campaignId),
                ))
                ->orderBy('sale_date')
                ->get()
            : collect();

        $harvestsByUnit = $harvests
            ->groupBy('unit')
            ->map(fn (Collection $rows, string $unit): array => [
                'unit' => $unit,
                'quantity' => round((float) $rows->sum('quantity'), 2),
                'count' => $rows->count(),
            ])
            ->values()
            ->all();

        $expensesTotal = (float) $expenses->sum('amount');
        $revenuesTotal = (float) $revenues->sum('amount');

        return [
            'farm' => $farm,
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'include_finances' => $includeFinances,
            'campaign_id' => $campaignId,
            'campaigns' => $campaigns,
            'issues' => $issues,
            'harvests' => $harvests,
            'harvests_by_unit' => $harvestsByUnit,
            'treatments' => $treatments,
            'expenses' => $expenses,
            'revenues' => $revenues,
            'sales' => $sales,
            'summary' => [
                'campaigns' => $campaigns->count(),
                'active_campaigns' => $campaigns->filter(fn (Campaign $campaign): bool => $campaign->status->isActive())->count(),
                'cultivated_area_m2' => round((float) $campaigns->sum('cultivated_area_m2'), 2),
                'harvested_quantity' => round((float) $harvests->sum('quantity'), 2),
                'harvest_count' => $harvests->count(),
                'treatments' => $treatments->count(),
                'issues' => $issues->count(),
                'open_issues' => $issues->filter(fn (CropIssue $issue): bool => $issue->status !== IssueStatus::Resolved)->count(),
                'critical_issues' => $issues->filter(fn (CropIssue $issue): bool => $issue->severity === IssueSeverity::Critical)->count(),
                'expenses' => round($expensesTotal, 2),
                'revenues' => round($revenuesTotal, 2),
                'margin' => round($revenuesTotal - $expensesTotal, 2),
                'sales' => round((float) $sales->sum('total_amount'), 2),
                'sales_outstanding' => round((float) $sales->sum('total_amount') - (float) $sales->sum('amount_paid'), 2),
            ],
        ];
    }

    /**
     * Lignes tabulaires destinées à l'export Excel.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array<int, array<int, mixed>>>
     */
    public function sheets(array $report): array
    {
        $summary = $report['summary'];

        $summaryRows = [
            ['Rapport d’exploitation', $report['farm']->name],
            ['Période', $report['period']['from']->format('d/m/Y').' au '.$report['period']['to']->format('d/m/Y')],
            ['Campagnes', $summary['campaigns']],
            ['Campagnes actives', $summary['active_campaigns']],
            ['Surface cultivée (m²)', $summary['cultivated_area_m2']],
            ['Quantité récoltée', $summary['harvested_quantity']],
            ['Récoltes saisies', $summary['harvest_count']],
            ['Traitements', $summary['treatments']],
            ['Problèmes signalés', $summary['issues']],
            ['Problèmes non résolus', $summary['open_issues']],
            ['Problèmes critiques', $summary['critical_issues']],
        ];

        if ($report['include_finances']) {
            $summaryRows[] = ['Dépenses (Ar)', $summary['expenses']];
            $summaryRows[] = ['Recettes (Ar)', $summary['revenues']];
            $summaryRows[] = ['Marge simplifiée (Ar)', $summary['margin']];
            $summaryRows[] = ['Ventes (Ar)', $summary['sales']];
            $summaryRows[] = ['Reste à encaisser (Ar)', $summary['sales_outstanding']];
        }

        $campaignRows = $report['campaigns']->map(fn (Campaign $campaign): array => [
            $campaign->name,
            $campaign->crop?->name,
            $campaign->productionUnit?->code,
            $campaign->zone?->code,
            $campaign->start_date?->format('d/m/Y'),
            $campaign->expected_end_date?->format('d/m/Y'),
            $campaign->status->label(),
            (float) $campaign->cultivated_area_m2,
            $campaign->activities_count,
            $campaign->treatments_count,
            $campaign->harvests_count,
            $campaign->issues_count,
        ])->all();

        $issueRows = $report['issues']->map(fn (CropIssue $issue): array => [
            $issue->reference,
            $issue->observed_at?->format('d/m/Y'),
            $issue->title,
            $issue->type->label(),
            $issue->severity->label(),
            $issue->status->label(),
            $issue->productionUnit?->name,
            $issue->campaign?->name,
            $issue->reportedBy?->name,
            (float) $issue->affected_area_m2,
        ])->all();

        $harvestRows = $report['harvests']->map(fn (Harvest $harvest): array => [
            $harvest->harvested_at?->format('d/m/Y H:i'),
            $harvest->campaign?->name,
            $harvest->campaign?->crop?->name,
            (float) $harvest->quantity,
            $harvest->unit,
            $harvest->quality,
        ])->all();

        $financeRows = [];

        if ($report['include_finances']) {
            foreach ($report['expenses'] as $expense) {
                $financeRows[] = [
                    'Dépense',
                    $expense->expense_date?->format('d/m/Y'),
                    $expense->category?->label() ?? '—',
                    $expense->description ?? '—',
                    -(float) $expense->amount,
                ];
            }

            foreach ($report['revenues'] as $revenue) {
                $financeRows[] = [
                    'Recette',
                    $revenue->revenue_date?->format('d/m/Y'),
                    $revenue->category?->label() ?? '—',
                    $revenue->description ?? '—',
                    (float) $revenue->amount,
                ];
            }
        }

        return [
            'Synthèse' => $summaryRows,
            'Campagnes' => array_merge([[
                'Campagne', 'Culture', 'Parcelle', 'Zone', 'Début', 'Fin prévue', 'Statut',
                'Surface m²', 'Activités', 'Traitements', 'Récoltes', 'Problèmes',
            ]], $campaignRows),
            'Problèmes' => array_merge([[
                'Référence', 'Date', 'Titre', 'Type', 'Sévérité', 'Statut',
                'Parcelle', 'Campagne', 'Signalé par', 'Surface affectée m²',
            ]], $issueRows),
            'Récoltes' => array_merge([[
                'Date', 'Campagne', 'Culture', 'Quantité', 'Unité', 'Qualité',
            ]], $harvestRows),
            'Finances' => array_merge([[
                'Nature', 'Date', 'Catégorie', 'Libellé', 'Montant Ar',
            ]], $financeRows),
        ];
    }
}
