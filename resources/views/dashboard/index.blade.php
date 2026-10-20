@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Tableau de bord</h1>
            <p class="text-sm text-slate-500">
                Période du {{ \Illuminate\Support\Carbon::parse($metrics['period']['from'])->format('d/m/Y') }}
                au {{ \Illuminate\Support\Carbon::parse($metrics['period']['to'])->format('d/m/Y') }}
            </p>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="from" class="block text-xs font-medium text-slate-600">Du</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"
                       class="mt-1 rounded border border-slate-300 px-2 py-1.5">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-slate-600">Au</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"
                       class="mt-1 rounded border border-slate-300 px-2 py-1.5">
            </div>
            <div>
                <label for="campaign_id" class="block text-xs font-medium text-slate-600">Campagne</label>
                <select id="campaign_id" name="campaign_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                    <option value="">Toutes</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected($filters['campaign_id'] === $campaign->id)>
                            {{ $campaign->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
            <a href="{{ route('dashboard') }}" class="rounded border border-slate-300 px-3 py-2 text-slate-600 hover:bg-slate-50">Réinitialiser</a>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Dépenses</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format((float) $metrics['expenses'], 0, ',', ' ') }} Ar</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Recettes</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format((float) $metrics['revenues'], 0, ',', ' ') }} Ar</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Marge simplifiée</p>
            <p class="mt-1 text-2xl font-semibold {{ $metrics['margin'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                {{ number_format((float) $metrics['margin'], 0, ',', ' ') }} Ar
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Campagnes en cours</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $metrics['active_campaigns'] }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Parcelles</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $metrics['parcels'] }}</p>
            <p class="text-xs text-slate-500">{{ number_format((float) $metrics['area_m2'], 0, ',', ' ') }} m²</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Réalisé / prévu</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
                {{ $metrics['completion_rate'] !== null ? number_format($metrics['completion_rate'], 1, ',', ' ') . ' %' : '—' }}
            </p>
            <p class="text-xs text-slate-500">{{ $metrics['late_activities'] }} activité(s) en retard</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Récolte</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format((float) $metrics['harvested_quantity'], 1, ',', ' ') }}</p>
            <p class="text-xs text-slate-500">
                {{ $metrics['yield_kg_per_ha'] !== null ? number_format($metrics['yield_kg_per_ha'], 1, ',', ' ') . ' / ha' : 'rendement indisponible' }}
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Stocks critiques</p>
            <p class="mt-1 text-2xl font-semibold {{ $metrics['critical_inputs']->isEmpty() ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $metrics['critical_inputs']->count() }}
            </p>
            @if (! $metrics['critical_inputs']->isEmpty())
                <ul class="mt-1 list-inside list-disc text-xs text-red-700">
                    @foreach ($metrics['critical_inputs']->take(4) as $critical)
                        <li>{{ $critical->name }} ({{ $critical->quantity }} / {{ $critical->minimum_stock }})</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card class="mt-6" title="Activités récentes">
        @if ($metrics['recent_activities']->isEmpty())
            <p class="text-sm text-slate-500">Aucune activité enregistrée.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Activité</th>
                        <th class="py-2 pr-4">Campagne</th>
                        <th class="py-2 pr-4">Date prévue</th>
                        <th class="py-2">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($metrics['recent_activities'] as $activity)
                        <tr>
                            <td class="py-2 pr-4">
                                <a href="{{ route('activities.show', $activity) }}" class="text-emerald-700 hover:underline">
                                    {{ $activity->title }}
                                </a>
                            </td>
                            <td class="py-2 pr-4">{{ $activity->campaign?->name ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $activity->planned_date->format('d/m/Y') }}</td>
                            <td class="py-2">
                                <span class="rounded px-2 py-0.5 text-xs
                                    {{ $activity->status->value === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($activity->isLate() ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $activity->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
@endsection
