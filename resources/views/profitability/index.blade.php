@extends('layouts.app')

@section('title', 'Rentabilité')

@php
    $money = fn ($value): string => number_format((float) $value, 0, ',', ' ').' Ar';
    $number = fn ($value, int $decimals = 1): string => $value === null
        ? '—'
        : number_format((float) $value, $decimals, ',', ' ');
    $marginClass = fn ($value): string => (float) $value >= 0 ? 'text-emerald-700' : 'text-red-600';
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Rentabilité par culture et par parcelle</h1>
            <p class="text-sm text-slate-500">
                Comparez rendement, dépenses, recettes et consommation d’eau.
                @if ($data['period']['from'] || $data['period']['to'])
                    Période : {{ $data['period']['from'] ? \Illuminate\Support\Carbon::parse($data['period']['from'])->format('d/m/Y') : 'début' }}
                    → {{ $data['period']['to'] ? \Illuminate\Support\Carbon::parse($data['period']['to'])->format('d/m/Y') : 'aujourd’hui' }}.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('profitability.export', request()->query()) }}"
               class="rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Exporter en CSV
            </a>
            <a href="{{ route('profitability.print', request()->query()) }}" target="_blank"
               class="rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Version PDF / impression
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('profitability.index') }}" class="mb-5 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="f-campaign" class="block text-xs font-medium text-slate-600">Campagne</label>
            <select id="f-campaign" name="campaign_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Toutes</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected($filters['campaign_id'] === $campaign->id)>
                        {{ $campaign->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-from" class="block text-xs font-medium text-slate-600">Du</label>
            <input type="date" id="f-from" name="from" value="{{ $filters['from'] }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="f-to" class="block text-xs font-medium text-slate-600">Au</label>
            <input type="date" id="f-to" name="to" value="{{ $filters['to'] }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('profitability.index') }}" class="rounded border border-slate-300 px-3 py-2 text-slate-600 hover:bg-slate-50">Réinitialiser</a>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Marge totale</p>
            <p class="mt-1 text-2xl font-semibold {{ $marginClass($data['totals']['margin']) }}">{{ $money($data['totals']['margin']) }}</p>
            <p class="text-xs text-slate-500">{{ $data['totals']['campaigns'] }} campagne(s)</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Recettes</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $money($data['totals']['revenues']) }}</p>
            <p class="text-xs text-slate-500">Dépenses {{ $money($data['totals']['expenses']) }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Marge par m²</p>
            <p class="mt-1 text-2xl font-semibold {{ $marginClass($data['totals']['margin']) }}">{{ $money($data['totals']['margin_per_m2'] ?? 0) }}</p>
            <p class="text-xs text-slate-500">{{ $number($data['totals']['area_m2'], 0) }} m² cultivés</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Eau par kg récolté</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
                {{ $data['totals']['water_per_kg'] !== null ? $number($data['totals']['water_per_kg'], 3).' m³' : '—' }}
            </p>
            <p class="text-xs text-slate-500">{{ $number($data['totals']['water_m3'], 1) }} m³ · {{ $number($data['totals']['harvest_kg'], 1) }} kg</p>
        </x-card>
    </div>

    <div class="mt-6 space-y-6">
        <x-card title="Par culture" subtitle="Cultures triées par marge décroissante. La surface est la somme des surfaces des campagnes.">
            @if (count($data['crops']) === 0)
                <p class="text-sm text-slate-500">Aucune donnée de culture pour ces filtres.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Culture</th>
                                <th class="py-2 pr-4 text-right">Campagnes</th>
                                <th class="py-2 pr-4 text-right">Surface</th>
                                <th class="py-2 pr-4 text-right">Dépenses</th>
                                <th class="py-2 pr-4 text-right">Recettes</th>
                                <th class="py-2 pr-4 text-right">Marge</th>
                                <th class="py-2 pr-4 text-right">Marge / m²</th>
                                <th class="py-2 pr-4 text-right">Récolte</th>
                                <th class="py-2 pr-4 text-right">Rendement</th>
                                <th class="py-2 text-right">Eau / kg</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($data['crops'] as $row)
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-slate-800">{{ $row['label'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $row['campaigns'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $number($row['area_m2'], 0) }} m²</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['expenses']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['revenues']) }}</td>
                                    <td class="py-2 pr-4 text-right font-semibold {{ $marginClass($row['margin']) }}">{{ $money($row['margin']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['margin_per_m2'] ?? 0) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $number($row['harvest_kg']) }} kg</td>
                                    <td class="py-2 pr-4 text-right">{{ $row['yield_kg_per_m2'] !== null ? $number($row['yield_kg_per_m2'], 2).' kg/m²' : '—' }}</td>
                                    <td class="py-2 text-right">{{ $row['water_per_kg'] !== null ? $number($row['water_per_kg'], 3).' m³' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Par parcelle" subtitle="Cumul des campagnes rattachées à chaque parcelle.">
            @if (count($data['parcels']) === 0)
                <p class="text-sm text-slate-500">Aucune donnée de parcelle pour ces filtres.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Parcelle</th>
                                <th class="py-2 pr-4 text-right">Campagnes</th>
                                <th class="py-2 pr-4 text-right">Surface</th>
                                <th class="py-2 pr-4 text-right">Dépenses</th>
                                <th class="py-2 pr-4 text-right">Recettes</th>
                                <th class="py-2 pr-4 text-right">Marge</th>
                                <th class="py-2 pr-4 text-right">Marge / m²</th>
                                <th class="py-2 pr-4 text-right">Récolte</th>
                                <th class="py-2 pr-4 text-right">Rendement</th>
                                <th class="py-2 text-right">Eau / kg</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($data['parcels'] as $row)
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-slate-800">{{ $row['label'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $row['campaigns'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $number($row['area_m2'], 0) }} m²</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['expenses']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['revenues']) }}</td>
                                    <td class="py-2 pr-4 text-right font-semibold {{ $marginClass($row['margin']) }}">{{ $money($row['margin']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['margin_per_m2'] ?? 0) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $number($row['harvest_kg']) }} kg</td>
                                    <td class="py-2 pr-4 text-right">{{ $row['yield_kg_per_m2'] !== null ? $number($row['yield_kg_per_m2'], 2).' kg/m²' : '—' }}</td>
                                    <td class="py-2 text-right">{{ $row['water_per_kg'] !== null ? $number($row['water_per_kg'], 3).' m³' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Détail par campagne" subtitle="Les campagnes annulées sont exclues des agrégats.">
            @if (count($data['campaigns']) === 0)
                <p class="text-sm text-slate-500">Aucune campagne pour ces filtres.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Campagne</th>
                                <th class="py-2 pr-4">Culture</th>
                                <th class="py-2 pr-4">Parcelle</th>
                                <th class="py-2 pr-4">Début</th>
                                <th class="py-2 pr-4 text-right">Dépenses</th>
                                <th class="py-2 pr-4 text-right">Recettes</th>
                                <th class="py-2 pr-4 text-right">Marge</th>
                                <th class="py-2 pr-4 text-right">Récolte</th>
                                <th class="py-2 pr-4 text-right">Eau</th>
                                <th class="py-2 text-right">Budget consommé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($data['campaigns'] as $row)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <a href="{{ route('campaigns.show', $row['id']) }}" class="font-medium text-emerald-700 hover:underline">{{ $row['name'] }}</a>
                                        <span class="block text-xs text-slate-400">{{ $row['status'] }}</span>
                                    </td>
                                    <td class="py-2 pr-4">{{ $row['crop'] }}</td>
                                    <td class="py-2 pr-4">{{ $row['parcel'] }}</td>
                                    <td class="py-2 pr-4 whitespace-nowrap">{{ $row['start_date'] ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['expenses']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $money($row['revenues']) }}</td>
                                    <td class="py-2 pr-4 text-right font-semibold {{ $marginClass($row['margin']) }}">{{ $money($row['margin']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $number($row['harvest_kg']) }} kg</td>
                                    <td class="py-2 pr-4 text-right">{{ $number($row['water_m3'], 1) }} m³</td>
                                    <td class="py-2 text-right">{{ $row['budget_usage_percent'] !== null ? $number($row['budget_usage_percent'], 1).' %' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
