@extends('layouts.app')

@section('title', 'Irrigation')

@section('content')
    @php
        $totalVolume = (float) $events->sum(fn ($event) => (float) $event->volume_m3);
        $totalDuration = (float) $events->sum(fn ($event) => (float) $event->duration_hours);
        $activeCampaignCount = $events->pluck('campaign_id')->unique()->count();
        $dailyVolumes = collect(range(6, 0))->map(function ($daysAgo) use ($events) {
            $date = today()->subDays($daysAgo);

            return [
                'date' => $date,
                'volume' => (float) $events->filter(fn ($event) => $event->irrigated_at->isSameDay($date))->sum(fn ($event) => (float) $event->volume_m3),
            ];
        });
        $peakDailyVolume = max(0.001, (float) $dailyVolumes->max('volume'));
    @endphp

    <section class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-cyan-950 to-emerald-800 px-5 py-6 text-white shadow-xl shadow-emerald-950/20 animate-fade-in-down sm:px-8 sm:py-8">
        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-cyan-300/20 blur-3xl animate-blob"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-20 left-1/3 h-52 w-52 rounded-full bg-emerald-300/20 blur-3xl animate-blob" style="animation-delay: -8s"></div>
        <div class="relative flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="animate-fade-in-up text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100/75">Suivi des ressources</p>
                <h1 class="mt-2 animate-fade-in-up text-2xl font-bold sm:text-3xl" style="animation-delay: 70ms">Irrigation &amp; eau</h1>
                <p class="mt-2 animate-fade-in-up text-sm text-emerald-50/80" style="animation-delay: 140ms">Les volumes et tendances calculés depuis les arrosages enregistrés.</p>
            </div>
            <div class="flex flex-wrap gap-2 animate-fade-in-up" style="animation-delay: 210ms">
                <a href="{{ route('irrigation.comparison') }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:-translate-y-0.5 hover:bg-white/15">Comparer les campagnes</a>
                <a href="{{ route('irrigation.create') }}" class="btn-shine rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">+ Nouvel arrosage</a>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3" data-reveal-group="65">
            <div data-reveal class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur-sm">
                <p class="text-xs font-medium text-cyan-100/75">Volume enregistré</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($totalVolume, 2, ',', ' ') }} <span class="text-sm font-semibold">m³</span></p>
            </div>
            <div data-reveal class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur-sm">
                <p class="text-xs font-medium text-cyan-100/75">Durée totale renseignée</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($totalDuration, 1, ',', ' ') }} <span class="text-sm font-semibold">h</span></p>
            </div>
            <div data-reveal class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur-sm">
                <p class="text-xs font-medium text-cyan-100/75">Campagnes suivies</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $activeCampaignCount }}</p>
            </div>
        </div>
    </section>

    <section class="mb-5 grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(16rem,0.8fr)]">
        <x-card class="overflow-hidden" data-reveal>
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-slate-800">Rythme des 7 derniers jours</h2>
                    <p class="mt-1 text-xs text-slate-500">Volume journalier issu des saisies réelles.</p>
                </div>
                <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-800">m³ / jour</span>
            </div>
            <div class="grid h-36 grid-cols-7 items-end gap-2 sm:gap-4" role="img" aria-label="Volumes d’irrigation enregistrés sur les sept derniers jours">
                @foreach ($dailyVolumes as $day)
                    @php $barHeight = $day['volume'] > 0 ? max(8, (int) round(($day['volume'] / $peakDailyVolume) * 100)) : 2; @endphp
                    <div class="flex h-full flex-col items-center justify-end gap-2">
                        <span class="text-[10px] font-semibold tabular-nums text-slate-500">{{ $day['volume'] > 0 ? number_format($day['volume'], 1, ',', ' ') : '—' }}</span>
                        <div class="flex h-24 w-full items-end overflow-hidden rounded-lg bg-slate-100">
                            <div class="water-bar w-full rounded-lg bg-gradient-to-t from-cyan-700 via-cyan-500 to-emerald-300 shadow-sm" style="--water-height: {{ $barHeight }}%; animation-delay: {{ $loop->index * 70 }}ms" title="{{ $day['date']->format('d/m') }} : {{ number_format($day['volume'], 2, ',', ' ') }} m³"></div>
                        </div>
                        <span class="text-[10px] font-medium text-slate-500">{{ $day['date']->translatedFormat('D') }}</span>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card class="flex flex-col justify-between" data-reveal>
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse-soft"></span>
                    Données de l’exploitation
                </span>
                <h2 class="mt-3 text-lg font-semibold text-slate-800">Pilotez chaque apport</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Comparez les campagnes pour repérer les quotas approchés et adapter le suivi de l’eau.</p>
            </div>
            <a href="{{ route('irrigation.comparison') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 transition hover:gap-3 hover:text-emerald-900">Voir les quotas <span aria-hidden="true">→</span></a>
        </x-card>
    </section>

    <form method="GET" action="{{ route('irrigation.index') }}" data-reveal class="mb-4 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
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

    <x-card data-reveal>
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
                            <tr data-reveal class="transition-colors duration-200 hover:bg-cyan-50/60">
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
