@extends('layouts.app')

@section('title', 'Irrigation')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Irrigation & eau</h1>
            <p class="text-sm text-slate-500">Suivi des arrosages par parcelle et par campagne.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('irrigation.comparison') }}" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Comparaison entre campagnes</a>
            <a href="{{ route('irrigation.create') }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Nouvel arrosage</a>
        </div>
    </div>

    <form method="GET" action="{{ route('irrigation.index') }}" class="mb-4 rounded border border-slate-200 bg-white p-3">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="campaign_id" class="block text-xs font-medium text-slate-600">Campagne</label>
                <select id="campaign_id" name="campaign_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                    <option value="">Toutes</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected($selectedCampaignId == $campaign->id)>{{ $campaign->name }} ({{ $campaign->productionUnit?->code }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded bg-slate-700 px-3 py-1.5 text-white hover:bg-slate-800">Filtrer</button>
            <a href="{{ route('irrigation.index') }}" class="text-sm text-slate-500 underline">Réinitialiser</a>
        </div>
    </form>

    <x-card>
        @if ($events->isEmpty())
            <p class="text-sm text-slate-600">Aucun arrosage enregistré.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Campagne</th>
                            <th class="py-2 pr-4">Parcelle</th>
                            <th class="py-2 pr-4">Volume (m³)</th>
                            <th class="py-2 pr-4">Durée (h)</th>
                            <th class="py-2 pr-4">Méthode</th>
                            <th class="py-2 pr-4">Source</th>
                            <th class="py-2">Enregistré par</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($events as $event)
                            <tr>
                                <td class="py-2 pr-4">{{ $event->irrigated_at->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ $event->campaign?->name }}</td>
                                <td class="py-2 pr-4">{{ $event->productionUnit?->code }}</td>
                                <td class="py-2 pr-4 font-semibold">{{ number_format((float) $event->volume_m3, 3, ',', ' ') }}</td>
                                <td class="py-2 pr-4">{{ $event->duration_hours !== null ? number_format((float) $event->duration_hours, 2, ',', ' ') : '—' }}</td>
                                <td class="py-2 pr-4">{{ $event->method ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $event->source ?? '—' }}</td>
                                <td class="py-2 text-xs">{{ $event->recordedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
