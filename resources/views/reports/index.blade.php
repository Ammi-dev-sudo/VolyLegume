@extends('layouts.app')

@section('title', 'Rapports')

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Rapports d’exploitation</h1>
            <p class="mt-1 text-sm text-slate-500">
                Synthèse consolidée de {{ $report['farm']->name }}
                du {{ $report['period']['from']->format('d/m/Y') }} au {{ $report['period']['to']->format('d/m/Y') }}.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.pdf', request()->query()) }}"
               class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Télécharger le PDF
            </a>
            <a href="{{ route('reports.excel', request()->query()) }}"
               class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">
                Exporter Excel
            </a>
        </div>
    </div>

    <x-card class="mb-5" title="Période et périmètre">
        <form method="GET" action="{{ route('reports.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="from" class="block text-sm font-medium text-slate-700">Du</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] }}"
                       class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-slate-700">Au</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] }}"
                       class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="campaign_id" class="block text-sm font-medium text-slate-700">Campagne</label>
                <select id="campaign_id" name="campaign_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Toutes les campagnes</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected($filters['campaign_id'] === $campaign->id)>{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Actualiser
                </button>
                <a href="{{ route('reports.index') }}" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                    Réinitialiser
                </a>
            </div>
        </form>
        @unless ($canViewFinances)
            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Votre rôle ne donne pas accès aux montants financiers : le rapport présente uniquement les données de production et de suivi.
            </p>
        @endunless
    </x-card>

    <div class="dashboard-grid mb-5">
        <x-stat-card label="Campagnes" :value="$report['summary']['campaigns']" :count="$report['summary']['campaigns']"
                     :hint="$report['summary']['active_campaigns'].' active(s)'" />
        <x-stat-card label="Surface cultivée" :value="number_format((float) $report['summary']['cultivated_area_m2'], 0, ',', ' ').' m²'"
                     :count="$report['summary']['cultivated_area_m2']" :decimals="0" suffix=" m²" tone="gold" />
        <x-stat-card label="Récoltes" :value="number_format((float) $report['summary']['harvested_quantity'], 1, ',', ' ')"
                     :count="$report['summary']['harvested_quantity']" :decimals="1"
                     :hint="$report['summary']['harvest_count'].' saisie(s)'" />
        <x-stat-card label="Problèmes" :value="$report['summary']['issues']" :count="$report['summary']['issues']"
                     :hint="$report['summary']['open_issues'].' non résolu(s) · '.$report['summary']['critical_issues'].' critique(s)'"
                     :tone="$report['summary']['critical_issues'] > 0 ? 'danger' : 'default'" />
    </div>

    @if ($canViewFinances)
        <div class="dashboard-grid mb-5">
            <x-stat-card label="Dépenses" :value="number_format((float) $report['summary']['expenses'], 0, ',', ' ').' Ar'"
                         :count="$report['summary']['expenses']" :decimals="0" suffix=" Ar" tone="danger" />
            <x-stat-card label="Recettes" :value="number_format((float) $report['summary']['revenues'], 0, ',', ' ').' Ar'"
                         :count="$report['summary']['revenues']" :decimals="0" suffix=" Ar" />
            <x-stat-card label="Marge simplifiée" :value="number_format((float) $report['summary']['margin'], 0, ',', ' ').' Ar'"
                         :count="$report['summary']['margin']" :decimals="0" suffix=" Ar"
                         :tone="$report['summary']['margin'] >= 0 ? 'default' : 'danger'" />
            <x-stat-card label="Reste à encaisser" :value="number_format((float) $report['summary']['sales_outstanding'], 0, ',', ' ').' Ar'"
                         :count="$report['summary']['sales_outstanding']" :decimals="0" suffix=" Ar" tone="gold"
                         :hint="number_format((float) $report['summary']['sales'], 0, ',', ' ').' Ar de ventes'" />
        </div>
    @endif

    <div class="space-y-5">
        <x-card title="Campagnes de la période" subtitle="Surfaces, statuts et volumes d’activité">
            @if ($report['campaigns']->isEmpty())
                <p class="text-sm text-slate-500">Aucune campagne sur la période.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-3">Campagne</th>
                                <th class="py-2 pr-3">Culture</th>
                                <th class="py-2 pr-3">Parcelle / zone</th>
                                <th class="py-2 pr-3">Période</th>
                                <th class="py-2 pr-3">Surface</th>
                                <th class="py-2 pr-3">Statut</th>
                                <th class="py-2">Suivi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['campaigns'] as $campaign)
                                <tr>
                                    <td class="py-2 pr-3">
                                        <a href="{{ route('campaigns.show', $campaign) }}" class="font-medium text-emerald-700 hover:underline">
                                            {{ $campaign->name }}
                                        </a>
                                    </td>
                                    <td class="py-2 pr-3">{{ $campaign->crop?->name ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-xs">
                                        {{ $campaign->productionUnit?->code ?? '—' }}
                                        @if ($campaign->zone) · {{ $campaign->zone->code }} @endif
                                    </td>
                                    <td class="py-2 pr-3 text-xs">
                                        {{ $campaign->start_date?->format('d/m/Y') }}
                                        @if ($campaign->expected_end_date) → {{ $campaign->expected_end_date->format('d/m/Y') }} @endif
                                    </td>
                                    <td class="py-2 pr-3">{{ number_format((float) $campaign->cultivated_area_m2, 0, ',', ' ') }} m²</td>
                                    <td class="py-2 pr-3">
                                        <span class="rounded px-2 py-0.5 text-xs {{ $campaign->status->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $campaign->status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-xs text-slate-500">
                                        {{ $campaign->activities_count }} act. · {{ $campaign->treatments_count }} tr. · {{ $campaign->harvests_count }} réc. · {{ $campaign->issues_count }} pb.
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Problèmes agricoles" subtitle="Constats de la période, par gravité">
            @if ($report['issues']->isEmpty())
                <p class="text-sm text-slate-500">Aucun problème signalé sur la période.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-3">Référence</th>
                                <th class="py-2 pr-3">Date</th>
                                <th class="py-2 pr-3">Problème</th>
                                <th class="py-2 pr-3">Type</th>
                                <th class="py-2 pr-3">Gravité</th>
                                <th class="py-2 pr-3">Statut</th>
                                <th class="py-2">Parcelle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['issues'] as $issue)
                                <tr>
                                    <td class="py-2 pr-3 font-mono text-xs">{{ $issue->reference }}</td>
                                    <td class="py-2 pr-3 text-xs">{{ $issue->observed_at?->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-3">
                                        <a href="{{ route('issues.show', $issue) }}" class="font-medium text-emerald-700 hover:underline">
                                            {{ $issue->title }}
                                        </a>
                                    </td>
                                    <td class="py-2 pr-3 text-xs">{{ $issue->type->label() }}</td>
                                    <td class="py-2 pr-3 text-xs">{{ $issue->severity->label() }}</td>
                                    <td class="py-2 pr-3 text-xs">{{ $issue->status->label() }}</td>
                                    <td class="py-2 text-xs">{{ $issue->productionUnit?->code ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Récoltes et traitements" subtitle="Traçabilité des interventions et des volumes">
            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-slate-700">Récoltes par unité</h3>
                    @if (count($report['harvests_by_unit']) === 0)
                        <p class="text-sm text-slate-500">Aucune récolte sur la période.</p>
                    @else
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($report['harvests_by_unit'] as $row)
                                <li class="flex items-center justify-between py-2">
                                    <span>{{ $row['count'] }} saisie(s)</span>
                                    <strong>{{ number_format((float) $row['quantity'], 2, ',', ' ') }} {{ $row['unit'] }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-slate-700">Traitements phytosanitaires</h3>
                    @if ($report['treatments']->isEmpty())
                        <p class="text-sm text-slate-500">Aucun traitement sur la période.</p>
                    @else
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($report['treatments']->take(8) as $treatment)
                                <li class="py-2">
                                    <p class="font-medium text-slate-700">{{ $treatment->input?->name ?? 'Produit' }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $treatment->applied_at?->format('d/m/Y') }}
                                        · {{ $treatment->campaign?->name }}
                                        · récolte ≥ {{ $treatment->minimum_harvest_at?->format('d/m/Y') }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </x-card>

        @if ($canViewFinances)
            <x-card title="Journal financier" subtitle="Dépenses et recettes de la période">
                @if ($report['expenses']->isEmpty() && $report['revenues']->isEmpty())
                    <p class="text-sm text-slate-500">Aucune écriture financière sur la période.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="py-2 pr-3">Nature</th>
                                    <th class="py-2 pr-3">Date</th>
                                    <th class="py-2 pr-3">Catégorie</th>
                                    <th class="py-2 pr-3">Libellé</th>
                                    <th class="py-2 text-right">Montant</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($report['expenses'] as $expense)
                                    <tr>
                                        <td class="py-2 pr-3 text-red-700">Dépense</td>
                                        <td class="py-2 pr-3 text-xs">{{ $expense->expense_date?->format('d/m/Y') }}</td>
                                        <td class="py-2 pr-3 text-xs">{{ $expense->category?->label() }}</td>
                                        <td class="py-2 pr-3">{{ $expense->description }}</td>
                                        <td class="py-2 text-right text-red-700">-{{ number_format((float) $expense->amount, 0, ',', ' ') }} Ar</td>
                                    </tr>
                                @endforeach
                                @foreach ($report['revenues'] as $revenue)
                                    <tr>
                                        <td class="py-2 pr-3 text-emerald-700">Recette</td>
                                        <td class="py-2 pr-3 text-xs">{{ $revenue->revenue_date?->format('d/m/Y') }}</td>
                                        <td class="py-2 pr-3 text-xs">{{ $revenue->category?->label() }}</td>
                                        <td class="py-2 pr-3">{{ $revenue->description }}</td>
                                        <td class="py-2 text-right text-emerald-700">{{ number_format((float) $revenue->amount, 0, ',', ' ') }} Ar</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endif
    </div>
@endsection
