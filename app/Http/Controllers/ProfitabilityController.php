<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Expense;
use App\Services\ProfitabilityService;
use App\Support\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rentabilité par campagne, par culture et par parcelle.
 *
 * L'écran est lisible par tous les membres de l'exploitation ; il ne modifie
 * aucune donnée. Les exports CSV et la version imprimable reprennent les mêmes
 * filtres que l'écran.
 */
class ProfitabilityController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request, ProfitabilityService $service): View
    {
        Gate::authorize('viewAny', Expense::class);

        $filters = $this->filters($request);

        return view('profitability.index', [
            'data' => $service->forFarm($this->farm(), $filters),
            'campaigns' => $this->campaigns(),
            'filters' => $filters,
        ]);
    }

    public function print(Request $request, ProfitabilityService $service): View
    {
        Gate::authorize('viewAny', Expense::class);

        return view('profitability.print', [
            'data' => $service->forFarm($this->farm(), $this->filters($request)),
            'farm' => $this->farm(),
        ]);
    }

    public function export(Request $request, ProfitabilityService $service, CsvExporter $csv): StreamedResponse
    {
        Gate::authorize('viewAny', Expense::class);

        $data = $service->forFarm($this->farm(), $this->filters($request));

        return $csv->download(
            'rentabilite-'.now()->format('Ymd-His').'.csv',
            [
                'Campagne',
                'Culture',
                'Parcelle',
                'Surface (m²)',
                'Début',
                'Statut',
                'Dépenses (Ar)',
                'Recettes (Ar)',
                'Marge (Ar)',
                'Marge / m² (Ar)',
                'Récolte (kg)',
                'Rendement (kg/m²)',
                'Rendement (kg/ha)',
                'Eau (m³)',
                'Eau / kg (m³)',
                'Budget (Ar)',
                'Consommation budget (%)',
            ],
            array_map(fn (array $row): array => [
                $row['name'],
                $row['crop'],
                $row['parcel'],
                $row['area_m2'],
                $row['start_date'],
                $row['status'],
                $row['expenses'],
                $row['revenues'],
                $row['margin'],
                $row['margin_per_m2'],
                $row['harvest_kg'],
                $row['yield_kg_per_m2'],
                $row['yield_kg_per_ha'],
                $row['water_m3'],
                $row['water_per_kg'],
                $row['budget'],
                $row['budget_usage_percent'],
            ], $data['campaigns']),
        );
    }

    /**
     * @return array{from?: ?string, to?: ?string, campaign_id?: ?int}
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'campaign_id' => $request->integer('campaign_id') ?: null,
        ];
    }

    private function campaigns()
    {
        return Campaign::query()
            ->forFarm($this->farmId())
            ->with('crop')
            ->orderByDesc('start_date')
            ->get();
    }
}
