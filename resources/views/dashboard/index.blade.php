@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-700 px-5 py-6 text-white shadow-xl shadow-emerald-950/20 animate-fade-in-down sm:px-8 sm:py-8">
        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-emerald-400/25 blur-3xl animate-blob"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-20 left-1/4 h-52 w-52 rounded-full bg-amber-300/20 blur-3xl animate-blob" style="animation-delay: -7s"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid opacity-25"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-6">
            <div class="min-w-0">
                <p class="animate-fade-in-up text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200/80">
                    Bonjour {{ auth()->user()->name }}
                </p>
                <h1 class="mt-2 animate-fade-in-up text-2xl font-bold leading-tight sm:text-3xl" style="animation-delay: 70ms">
                    Votre exploitation, <span class="gradient-text">en un coup d’œil.</span>
                </h1>
                <p class="mt-2 max-w-xl animate-fade-in-up text-sm text-emerald-50/80" style="animation-delay: 140ms">
                    {{ $currentFarm->name }} · {{ $currentRole?->label() ?? 'Membre' }} —
                    suivez vos cultures, vos stocks et vos résultats en temps réel.
                </p>
                <div class="mt-4 flex animate-fade-in-up flex-wrap gap-2" style="animation-delay: 210ms">
                    <a href="{{ route('activities.create') }}"
                       class="btn-shine inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                        </svg>
                        Nouvelle activité
                    </a>
                    <a href="{{ route('calendar.index') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:-translate-y-0.5 hover:bg-white/15">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2" />
                            <path d="M8 3v4M16 3v4M3 10h18" stroke-linecap="round" />
                        </svg>
                        Agenda
                    </a>
                    <a href="{{ route('resources.index') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:-translate-y-0.5 hover:bg-white/15">
                        Ressources &amp; tutoriels
                    </a>
                </div>
            </div>

            <div class="relative hidden w-40 sm:block">
                <div class="absolute inset-4 rounded-full bg-emerald-300/25 blur-2xl animate-pulse-soft"></div>
                <img src="{{ asset('images/logo-sm.png') }}" alt="Logo VolyLegume" class="float-slow relative w-full drop-shadow-2xl">
            </div>
        </div>
    </section>

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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-reveal-group="60">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Dépenses</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800"
               data-count="{{ (float) $metrics['expenses'] }}" data-count-decimals="0" data-count-suffix=" Ar">
                {{ number_format((float) $metrics['expenses'], 0, ',', ' ') }} Ar
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Recettes</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800"
               data-count="{{ (float) $metrics['revenues'] }}" data-count-decimals="0" data-count-suffix=" Ar">
                {{ number_format((float) $metrics['revenues'], 0, ',', ' ') }} Ar
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Marge simplifiée</p>
            <p class="mt-1 text-2xl font-semibold {{ $metrics['margin'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}"
               data-count="{{ (float) $metrics['margin'] }}" data-count-decimals="0" data-count-suffix=" Ar">
                {{ number_format((float) $metrics['margin'], 0, ',', ' ') }} Ar
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Campagnes en cours</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800" data-count="{{ (int) $metrics['active_campaigns'] }}">
                {{ $metrics['active_campaigns'] }}
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Parcelles</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800" data-count="{{ (int) $metrics['parcels'] }}">
                {{ $metrics['parcels'] }}
            </p>
            <p class="text-xs text-slate-500">{{ number_format((float) $metrics['area_m2'], 0, ',', ' ') }} m²</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Réalisé / prévu</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800"
               @if ($metrics['completion_rate'] !== null)
                   data-count="{{ (float) $metrics['completion_rate'] }}" data-count-decimals="1" data-count-suffix=" %"
               @endif>
                {{ $metrics['completion_rate'] !== null ? number_format($metrics['completion_rate'], 1, ',', ' ') . ' %' : '—' }}
            </p>
            <p class="text-xs text-slate-500">{{ $metrics['late_activities'] }} activité(s) en retard</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Récolte</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800"
               data-count="{{ (float) $metrics['harvested_quantity'] }}" data-count-decimals="1">
                {{ number_format((float) $metrics['harvested_quantity'], 1, ',', ' ') }}
            </p>
            <p class="text-xs text-slate-500">
                {{ $metrics['yield_kg_per_ha'] !== null ? number_format($metrics['yield_kg_per_ha'], 1, ',', ' ') . ' / ha' : 'rendement indisponible' }}
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Stock récolté disponible</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800"
               data-count="{{ (float) $metrics['produce_available'] }}" data-count-decimals="1">
                {{ number_format((float) $metrics['produce_available'], 1, ',', ' ') }}
            </p>
            <p class="text-xs text-slate-500">{{ $metrics['produce_lots_available'] }} lot(s) disponible(s)</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Ventes de la période</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800"
               data-count="{{ (float) $metrics['sales_amount'] }}" data-count-decimals="0" data-count-suffix=" Ar">
                {{ number_format((float) $metrics['sales_amount'], 0, ',', ' ') }} Ar
            </p>
            <p class="text-xs text-slate-500">
                {{ $metrics['sales_count'] }} vente(s) ·
                {{ number_format((float) $metrics['sales_outstanding'], 0, ',', ' ') }} Ar à encaisser
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Stocks critiques</p>
            <p class="mt-1 text-2xl font-semibold {{ count($metrics['critical_inputs']) === 0 ? 'text-emerald-700' : 'text-red-600' }}"
               data-count="{{ count($metrics['critical_inputs']) }}">
                {{ count($metrics['critical_inputs']) }}
            </p>
            @if (count($metrics['critical_inputs']) > 0)
                <ul class="mt-1 list-inside list-disc text-xs text-red-700">
                    @foreach (array_slice($metrics['critical_inputs'], 0, 4) as $critical)
                        <li>{{ $critical['name'] }} ({{ $critical['quantity'] }} / {{ $critical['minimum_stock'] }})</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card class="mt-6" title="Activités récentes">
        @if (count($metrics['recent_activities']) === 0)
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
                                <a href="{{ route('activities.show', $activity['id']) }}" class="text-emerald-700 hover:underline">
                                    {{ $activity['title'] }}
                                </a>
                            </td>
                            <td class="py-2 pr-4">{{ $activity['campaign_name'] ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $activity['planned_date'] }}</td>
                            <td class="py-2">
                                <span class="rounded px-2 py-0.5 text-xs
                                    {{ $activity['status_value'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($activity['is_late'] ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $activity['status_label'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <section class="mt-8">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">À découvrir</h2>
                <p class="text-sm text-slate-500">Tutoriels vidéo, guides pratiques et fonctionnement des rôles.</p>
            </div>
            <a href="{{ route('resources.index') }}" class="text-sm font-semibold text-emerald-700 transition hover:text-emerald-800">
                Tout voir &rarr;
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-3" data-reveal-group="70">
            <a href="{{ route('resources.index') }}#videos"
               class="resource-card group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-reveal data-tilt>
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white shadow-md">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M8 5.5v13l11-6.5-11-6.5Z" />
                    </svg>
                </span>
                <h3 class="mt-3 font-semibold text-slate-800 transition group-hover:text-emerald-800">Tutoriels vidéo</h3>
                <p class="mt-1 text-sm text-slate-500">Des vidéos ciblées pour chaque étape du maraîchage.</p>
            </a>

            <a href="{{ route('resources.index') }}#guides"
               class="resource-card group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-reveal data-tilt>
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-lime-500 to-emerald-700 text-white shadow-md">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5V5.5Z" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M8 7h8M8 11h6" stroke-linecap="round" />
                    </svg>
                </span>
                <h3 class="mt-3 font-semibold text-slate-800 transition group-hover:text-emerald-800">Guides pas à pas</h3>
                <p class="mt-1 text-sm text-slate-500">Accédez directement aux écrans essentiels de l’application.</p>
            </a>

            <a href="{{ route('resources.index') }}#roles"
               class="resource-card group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-reveal data-tilt>
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-600 text-white shadow-md">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <circle cx="9" cy="8" r="3" />
                        <path d="M3 20a6 6 0 0 1 12 0" stroke-linecap="round" />
                        <path d="M16 4.5a3 3 0 0 1 0 7M18 20a5.5 5.5 0 0 0-3-4.9" stroke-linecap="round" />
                    </svg>
                </span>
                <h3 class="mt-3 font-semibold text-slate-800 transition group-hover:text-emerald-800">Rôles &amp; permissions</h3>
                <p class="mt-1 text-sm text-slate-500">Comprenez ce que chaque rôle peut voir et modifier.</p>
            </a>
        </div>
    </section>
@endsection
