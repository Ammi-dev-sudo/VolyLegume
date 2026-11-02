<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Input;
use App\Models\PhytosanitaryTreatment;
use App\Models\Revenue;
use App\Models\Sale;
use App\Support\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports CSV des principaux états de l'exploitation.
 *
 * Les mêmes filtres que les écrans (campagne, période) sont acceptés. Chaque
 * requête reste bornée à l'exploitation active ; les exports ne contournent
 * donc pas l'isolation multi-exploitations.
 */
class ExportController extends Controller
{
    use UsesFarmContext;

    public function campaigns(Request $request, CsvExporter $csv): StreamedResponse
    {
        Gate::authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()
            ->forFarm($this->farmId())
            ->with(['crop:id,name', 'productionUnit:id,code,name'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->whereKey($request->integer('campaign_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('start_date')
            ->get();

        return $csv->download(
            'campagnes-'.now()->format('Ymd-His').'.csv',
            [
                'Campagne', 'Culture', 'Parcelle', 'Code parcelle', 'Début',
                'Fin prévue', 'Fin réelle', 'Statut', 'Surface (m²)',
                'Rendement prévu (kg)', 'Rendement réel (kg)', 'Budget prévu (Ar)',
                'Quota eau (m³)',
            ],
            $campaigns->map(fn (Campaign $campaign): array => [
                $campaign->name,
                $campaign->crop?->name,
                $campaign->productionUnit?->name,
                $campaign->productionUnit?->code,
                $campaign->start_date?->format('d/m/Y'),
                $campaign->expected_end_date?->format('d/m/Y'),
                $campaign->actual_end_date?->format('d/m/Y'),
                $campaign->status->label(),
                (float) $campaign->cultivated_area_m2,
                $campaign->expected_yield_kg !== null ? (float) $campaign->expected_yield_kg : null,
                $campaign->actual_yield_kg !== null ? (float) $campaign->actual_yield_kg : null,
                $campaign->planned_budget !== null ? (float) $campaign->planned_budget : null,
                $campaign->water_quota_m3 !== null ? (float) $campaign->water_quota_m3 : null,
            ]),
        );
    }

    public function stock(Request $request, CsvExporter $csv): StreamedResponse
    {
        Gate::authorize('viewAny', Input::class);

        $inputs = Input::query()
            ->forFarm($this->farmId())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->get();

        return $csv->download(
            'stocks-'.now()->format('Ymd-His').'.csv',
            [
                'Code', 'Intrant', 'Catégorie', 'Unité', 'Quantité disponible',
                'Seuil minimal', 'Critique', 'Délai avant récolte (jours)', 'Actif',
            ],
            $inputs->map(fn (Input $input): array => [
                $input->code,
                $input->name,
                $input->category->label(),
                $input->base_unit->value,
                (float) $input->quantity,
                (float) $input->minimum_stock,
                $input->isCritical() ? 'Oui' : 'Non',
                $input->default_pre_harvest_days,
                $input->is_active ? 'Oui' : 'Non',
            ]),
        );
    }

    public function treatments(Request $request, CsvExporter $csv): StreamedResponse
    {
        Gate::authorize('viewAny', PhytosanitaryTreatment::class);

        $treatments = PhytosanitaryTreatment::query()
            ->forFarm($this->farmId())
            ->with(['campaign.crop', 'input', 'appliedBy'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('input_id'), fn ($query) => $query->where('input_id', $request->integer('input_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('applied_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('applied_at', '<=', $request->date('to')))
            ->orderByDesc('applied_at')
            ->get();

        return $csv->download(
            'traitements-'.now()->format('Ymd-His').'.csv',
            [
                'Date', 'Campagne', 'Culture', 'Intrant', 'Dose', 'Unité de dose',
                'Délai avant récolte (jours)', 'Récolte possible à partir du',
                'Source du délai', 'Appliqué par', 'Notes',
            ],
            $treatments->map(fn (PhytosanitaryTreatment $treatment): array => [
                $treatment->applied_at?->format('d/m/Y H:i'),
                $treatment->campaign?->name,
                $treatment->campaign?->crop?->name,
                $treatment->input?->name,
                (float) $treatment->dose_value,
                $treatment->dose_unit,
                $treatment->pre_harvest_days,
                $treatment->minimum_harvest_at?->format('d/m/Y H:i'),
                $treatment->delay_source_snapshot,
                $treatment->appliedBy?->name,
                $treatment->notes,
            ]),
        );
    }

    public function sales(Request $request, CsvExporter $csv): StreamedResponse
    {
        Gate::authorize('viewAny', Sale::class);

        $sales = Sale::query()
            ->forFarm($this->farmId())
            ->with(['customer', 'recordedBy'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('sale_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('sale_date', '<=', $request->date('to')))
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get();

        return $csv->download(
            'ventes-'.now()->format('Ymd-His').'.csv',
            [
                'Référence', 'Date', 'Client', 'Statut', 'Paiement', 'Sous-total (Ar)',
                'Remise (Ar)', 'Total (Ar)', 'Encaissé (Ar)', 'Reste à encaisser (Ar)',
                'Enregistrée par', 'Notes',
            ],
            $sales->map(fn (Sale $sale): array => [
                $sale->reference,
                $sale->sale_date?->format('d/m/Y'),
                $sale->customer?->name ?? 'Client de passage',
                $sale->status->label(),
                $sale->payment_status->label(),
                (float) $sale->subtotal,
                (float) $sale->discount_amount,
                (float) $sale->total_amount,
                (float) $sale->amount_paid,
                $sale->balanceDue(),
                $sale->recordedBy?->name,
                $sale->notes,
            ]),
        );
    }

    public function finances(Request $request, CsvExporter $csv): StreamedResponse
    {
        Gate::authorize('viewAny', Expense::class);

        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();
        $campaignId = $request->integer('campaign_id') ?: null;

        $ledger = $this->expenses($from, $to, $campaignId)
            ->concat($this->revenues($from, $to, $campaignId))
            ->sortByDesc(fn (array $row): string => $row[0].' '.$row[1])
            ->values();

        return $csv->download(
            'finances-'.now()->format('Ymd-His').'.csv',
            ['Type', 'Date', 'Campagne', 'Catégorie', 'Description', 'Montant (Ar)', 'Référence'],
            $ledger,
        );
    }

    /**
     * @return Collection<int, array<int, mixed>>
     */
    private function expenses(?string $from, ?string $to, ?int $campaignId)
    {
        return Expense::query()
            ->forFarm($this->farmId())
            ->with('campaign:id,name')
            ->when($from, fn ($query) => $query->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('expense_date', '<=', $to))
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->get()
            ->map(fn (Expense $expense): array => [
                'Dépense',
                $expense->expense_date?->format('Y-m-d'),
                $expense->campaign?->name,
                $expense->category->label(),
                $expense->description,
                (float) $expense->amount,
                $expense->reference,
            ]);
    }

    /**
     * @return Collection<int, array<int, mixed>>
     */
    private function revenues(?string $from, ?string $to, ?int $campaignId)
    {
        return Revenue::query()
            ->forFarm($this->farmId())
            ->with('campaign:id,name')
            ->when($from, fn ($query) => $query->whereDate('revenue_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('revenue_date', '<=', $to))
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->get()
            ->map(fn (Revenue $revenue): array => [
                'Recette',
                $revenue->revenue_date?->format('Y-m-d'),
                $revenue->campaign?->name,
                $revenue->category->label(),
                $revenue->description,
                (float) $revenue->amount,
                $revenue->reference,
            ]);
    }
}
