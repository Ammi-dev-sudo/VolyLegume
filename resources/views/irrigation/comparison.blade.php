@extends('layouts.app')

@section('title', 'Comparaison irrigation')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Comparaison entre campagnes</h1>
            <p class="text-sm text-slate-500">Volumes, densité (m³/m²) et suivi par rapport aux quotas d'eau (paramètres d'exploitation).</p>
        </div>
        <a href="{{ route('irrigation.index') }}" class="text-sm text-slate-500 underline">Retour à l'irrigation</a>
    </div>

    <x-card>
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-600">Aucune campagne.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Campagne</th>
                            <th class="py-2 pr-4">Parcelle / culture</th>
                            <th class="py-2 pr-4">Surface (m²)</th>
                            <th class="py-2 pr-4">Arrosages</th>
                            <th class="py-2 pr-4">Volume total (m³)</th>
                            <th class="py-2 pr-4">m³/m²</th>
                            <th class="py-2 pr-4">Quota (m³)</th>
                            <th class="py-2 pr-4">Seuil alerte</th>
                            <th class="py-2 pr-4">Restant / état</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php $c = $row['campaign']; $s = $row['summary']; @endphp
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $c->name }}</td>
                                <td class="py-2 pr-4 text-xs">{{ $c->productionUnit?->code }} · {{ $c->crop?->name }}</td>
                                <td class="py-2 pr-4">{{ number_format((float) $s['area_m2'], 0, ',', ' ') }}</td>
                                <td class="py-2 pr-4">{{ $s['events_count'] }}</td>
                                <td class="py-2 pr-4 font-semibold">{{ number_format((float) $s['total_volume_m3'], 3, ',', ' ') }}</td>
                                <td class="py-2 pr-4">{{ $s['volume_per_m2'] !== null ? number_format((float) $s['volume_per_m2'], 4, ',', ' ') : '—' }}</td>
                                <td class="py-2 pr-4">{{ $s['quota_m3'] !== null ? number_format((float) $s['quota_m3'], 3, ',', ' ') : '—' }}</td>
                                <td class="py-2 pr-4">{{ $s['quota_m3'] !== null ? $s['threshold_percent'].' % ('.number_format((float) $s['alert_threshold_m3'], 3, ',', ' ').' m³)' : '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($s['quota_m3'] === null)
                                        <span class="text-xs text-slate-500">Sans quota défini</span>
                                    @elseif ($s['status'] === 'exceeded')
                                        <span class="rounded px-2 py-0.5 text-xs bg-red-100 text-red-800">Dépassement (reste {{ number_format((float) $s['remaining_m3'], 3, ',', ' ') }} m³)</span>
                                    @elseif ($s['status'] === 'approaching')
                                        <span class="rounded px-2 py-0.5 text-xs bg-amber-100 text-amber-800">Approche du seuil (reste {{ number_format((float) $s['remaining_m3'], 3, ',', ' ') }} m³)</span>
                                    @else
                                        <span class="rounded px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800">OK (reste {{ number_format((float) $s['remaining_m3'], 3, ',', ' ') }} m³)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
