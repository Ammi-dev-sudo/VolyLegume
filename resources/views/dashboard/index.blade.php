@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    @php
        $periodLabels = [
            'today' => 'Aujourd’hui',
            'week' => 'Cette semaine',
            'month' => 'Ce mois',
            'year' => 'Cette année',
            'custom' => 'Personnalisée',
        ];
        $periodFrom = \Illuminate\Support\Carbon::parse($metrics['period']['from']);
        $periodTo = \Illuminate\Support\Carbon::parse($metrics['period']['to']);
        $financeLabels = collect($metrics['monthly_finance'])->pluck('label')->all();
        $financeSeries = [
            [
                'name' => 'Dépenses',
                'color' => 'danger',
                'values' => collect($metrics['monthly_finance'])->pluck('expenses')->all(),
            ],
            [
                'name' => 'Recettes',
                'color' => 'accent',
                'values' => collect($metrics['monthly_finance'])->pluck('revenues')->all(),
            ],
        ];
        $activityLabels = collect($metrics['activity_trend'])->pluck('label')->all();
        $activitySeries = [
            [
                'name' => 'Planifiées',
                'color' => 'info',
                'values' => collect($metrics['activity_trend'])->pluck('planned')->all(),
            ],
            [
                'name' => 'Réalisées',
                'color' => 'accent',
                'values' => collect($metrics['activity_trend'])->pluck('completed')->all(),
            ],
        ];
        $productionRows = collect($metrics['production_by_crop'])->take(6);
        $productionMax = max(1, (float) ($productionRows->max('quantity') ?? 0));
        $harvestUnitSummary = collect($metrics['harvest_units'])
            ->map(fn ($quantity, $unit) => number_format((float) $quantity, 1, ',', ' ').' '.$unit)
            ->implode(' · ');
    @endphp

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
                    du {{ $periodFrom->format('d/m/Y') }} au {{ $periodTo->format('d/m/Y') }}.
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
                    <a href="{{ route('farm-map.index') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:-translate-y-0.5 hover:bg-white/15">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M4 6.5 9 4l6 2.5L20 4v13.5L15 20l-6-2.5L4 20V6.5Z" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9 4v13.5M15 6.5V20" stroke-linecap="round" />
                        </svg>
                        Plan des parcelles
                    </a>
                </div>
            </div>

            <div class="relative hidden w-40 sm:block">
                <div class="absolute inset-4 rounded-full bg-emerald-300/25 blur-2xl animate-pulse-soft"></div>
                <img src="{{ asset('images/logo-sm.png') }}" alt="Logo VolyLegume" class="float-slow relative w-full drop-shadow-2xl">
            </div>
        </div>
    </section>

    <div class="period-toolbar mb-5">
        <nav class="period-pills" aria-label="Période du tableau de bord">
            @foreach ($periodLabels as $key => $label)
                <a href="{{ route('dashboard', array_filter(['period' => $key, 'campaign_id' => $filters['campaign_id']])) }}"
                   class="period-pill {{ $filters['period'] === $key ? 'is-active' : '' }}"
                   @if ($filters['period'] === $key) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('dashboard') }}" class="period-form">
            <input type="hidden" name="period" value="custom">
            <label>
                <span class="block font-medium">Du</span>
                <input type="date" name="from" value="{{ $filters['from'] }}" aria-label="Date de début">
            </label>
            <label>
                <span class="block font-medium">Au</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" aria-label="Date de fin">
            </label>
            <label>
                <span class="block font-medium">Campagne</span>
                <select name="campaign_id" aria-label="Filtrer par campagne">
                    <option value="">Toutes</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected($filters['campaign_id'] === $campaign->id)>
                            {{ $campaign->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-800">
                Appliquer
            </button>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                Réinitialiser
            </a>
        </form>
    </div>

    @unless ($canViewFinances)
        <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Votre rôle ne donne pas accès aux montants financiers. Les indicateurs de production et d’alertes restent disponibles.
        </p>
    @endunless

    <section class="dashboard-section" aria-labelledby="overview-title">
        <div class="dashboard-section__head">
            <div>
                <h2 id="overview-title" class="dashboard-section__title">Vue d’ensemble</h2>
                <p class="dashboard-section__hint">Surfaces, cultures et campagnes suivies sur la période.</p>
            </div>
        </div>

        <div class="dashboard-grid" data-reveal-group="50">
            <x-stat-card label="Parcelles" :value="$metrics['parcels']" :count="$metrics['parcels']"
                         :hint="number_format((float) $metrics['area_m2'], 0, ',', ' ').' m² cadastrés'" />
            <x-stat-card label="Cultures actives" :value="$metrics['active_crops']" :count="$metrics['active_crops']"
                         hint="Espèces cultivées en campagne active" tone="info" />
            <x-stat-card label="Campagnes actives" :value="$metrics['active_campaigns']" :count="$metrics['active_campaigns']"
                         hint="Planifiées ou en cours" />
            <x-stat-card label="Surface cultivée" :value="number_format((float) $metrics['cultivated_area_m2'], 0, ',', ' ').' m²'"
                         :count="$metrics['cultivated_area_m2']" :decimals="0" suffix=" m²"
                         hint="Somme des surfaces des campagnes actives" tone="gold" />
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="production-title">
        <div class="dashboard-section__head">
            <div>
                <h2 id="production-title" class="dashboard-section__title">Production</h2>
                <p class="dashboard-section__hint">Récoltes, rendement et stock récolté disponible.</p>
            </div>
        </div>

        <div class="dashboard-grid" data-reveal-group="55">
            <x-stat-card label="Récoltes réalisées" :value="number_format((float) $metrics['harvested_quantity'], 1, ',', ' ').' kg'"
                         :count="$metrics['harvested_quantity']" :decimals="1" suffix=" kg"
                         :hint="$metrics['harvested_count'].' saisie(s)'.($harvestUnitSummary !== '' ? ' · '.$harvestUnitSummary : '')" />
            <x-stat-card label="Récoltes prévues" :value="$metrics['planned_harvests']" :count="$metrics['planned_harvests']"
                         hint="Campagnes dont la fin est prévue sur la période" tone="info" />
            <x-stat-card label="Rendement" :value="$metrics['yield_kg_per_ha'] !== null ? number_format((float) $metrics['yield_kg_per_ha'], 1, ',', ' ').' kg/ha' : '—'"
                         :count="$metrics['yield_kg_per_ha']" :decimals="1" suffix=" kg/ha"
                         hint="Récoltes rapportées à la surface récoltée" tone="gold" />
            <x-stat-card label="Stock récolté" :value="number_format((float) $metrics['produce_available'], 1, ',', ' ')"
                         :count="$metrics['produce_available']" :decimals="1"
                         :hint="$metrics['produce_lots_available'].' lot(s) disponible(s)'" />
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="pilotage-title">
        <div class="dashboard-section__head">
            <div>
                <h2 id="pilotage-title" class="dashboard-section__title">Pilotage &amp; alertes</h2>
                <p class="dashboard-section__hint">Avancement du calendrier cultural et points de vigilance.</p>
            </div>
        </div>

        <div class="dashboard-grid" data-reveal-group="60">
            <x-stat-card label="Taux de réalisation" :value="$metrics['completion_rate'] !== null ? number_format((float) $metrics['completion_rate'], 1, ',', ' ').' %' : '—'"
                         :count="$metrics['completion_rate']" :decimals="1" suffix=" %"
                         hint="Activités réalisées sur les activités actives" />
            <x-stat-card label="Tâches en retard" :value="$metrics['late_activities']" :count="$metrics['late_activities']"
                         hint="Activités planifiées dont la date est dépassée" :tone="$metrics['late_activities'] > 0 ? 'gold' : 'default'" />
            <x-stat-card label="Alertes critiques" :value="$metrics['critical_activities']" :count="$metrics['critical_activities']"
                         hint="Plus de 3 jours de retard" :tone="$metrics['critical_activities'] > 0 ? 'danger' : 'default'" />
            <x-stat-card label="Stocks critiques" :value="$metrics['low_stock_count']" :count="$metrics['low_stock_count']"
                         :hint="$metrics['inputs_total'].' intrant(s) suivi(s)'" :tone="$metrics['low_stock_count'] > 0 ? 'danger' : 'default'" />
            <x-stat-card label="Problèmes ouverts" :value="$metrics['open_issues']" :count="$metrics['open_issues']"
                         hint="Constats non résolus de l’exploitation" :tone="$metrics['open_issues'] > 0 ? 'gold' : 'default'" />
            <x-stat-card label="Problèmes critiques" :value="$metrics['critical_issues']" :count="$metrics['critical_issues']"
                         hint="Sévérité critique à traiter en priorité" :tone="$metrics['critical_issues'] > 0 ? 'danger' : 'default'" />
        </div>
    </section>

    @if ($canViewFinances)
        <section class="dashboard-section" aria-labelledby="finance-title">
            <div class="dashboard-section__head">
                <div>
                    <h2 id="finance-title" class="dashboard-section__title">Finances</h2>
                    <p class="dashboard-section__hint">Dépenses, recettes, marge et encaissements de la période.</p>
                </div>
                <a href="{{ route('profitability.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                    Voir la rentabilité &rarr;
                </a>
            </div>

            <div class="dashboard-grid" data-reveal-group="60">
                <x-stat-card label="Dépenses" :value="number_format((float) $metrics['expenses'], 0, ',', ' ').' Ar'"
                             :count="$metrics['expenses']" :decimals="0" suffix=" Ar" tone="danger" />
                <x-stat-card label="Recettes" :value="number_format((float) $metrics['revenues'], 0, ',', ' ').' Ar'"
                             :count="$metrics['revenues']" :decimals="0" suffix=" Ar" />
                <x-stat-card label="Bénéfice simplifié" :value="number_format((float) $metrics['margin'], 0, ',', ' ').' Ar'"
                             :count="$metrics['margin']" :decimals="0" suffix=" Ar"
                             :tone="$metrics['margin'] >= 0 ? 'default' : 'danger'"
                             hint="Recettes moins dépenses sur la période" />
                <x-stat-card label="Ventes" :value="number_format((float) $metrics['sales_amount'], 0, ',', ' ').' Ar'"
                             :count="$metrics['sales_amount']" :decimals="0" suffix=" Ar" tone="gold"
                             :hint="$metrics['sales_count'].' vente(s) · '.number_format((float) $metrics['sales_outstanding'], 0, ',', ' ').' Ar à encaisser'" />
            </div>
        </section>
    @endif

    <section class="dashboard-section" aria-labelledby="charts-title">
        <div class="dashboard-section__head">
            <div>
                <h2 id="charts-title" class="dashboard-section__title">Tendances sur douze mois</h2>
                <p class="dashboard-section__hint">Les séries se terminent au {{ $periodTo->format('m/Y') }} et restent lisibles même sur une période courte.</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @if ($canViewFinances)
                <x-card title="Recettes et dépenses" subtitle="Montants mensuels en Ariary">
                    <x-chart.bar :labels="$financeLabels" :series="$financeSeries" suffix=" Ar"
                                 aria-label="Recettes et dépenses mensuelles" />
                </x-card>
            @endif

            <x-card title="Évolution des récoltes" subtitle="Quantités récoltées par mois (kg)">
                <x-chart.line :points="$metrics['harvest_trend']" suffix=" kg" :decimals="1"
                              aria-label="Évolution mensuelle des récoltes" />
            </x-card>

            <x-card title="Activités par mois" subtitle="Activités planifiées et réalisées">
                <x-chart.bar :labels="$activityLabels" :series="$activitySeries"
                             aria-label="Activités planifiées et réalisées par mois" />
            </x-card>

            <x-card title="Répartition des surfaces" subtitle="Cultures actives par surface cultivée">
                <x-chart.donut :items="$metrics['crop_distribution']" unit="m²"
                               aria-label="Répartition des surfaces par culture" />
            </x-card>
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="details-title">
        <div class="dashboard-section__head">
            <div>
                <h2 id="details-title" class="dashboard-section__title">À surveiller</h2>
                <p class="dashboard-section__hint">Prochaines récoltes et intrants sous le seuil minimal.</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2" title="Prochaines récoltes" subtitle="Fins de campagne prévues dans les 30 jours">
                @if (count($metrics['upcoming_harvests']) === 0)
                    <p class="text-sm text-slate-500">Aucune récolte prévue dans les 30 prochains jours.</p>
                @else
                    <ul class="upcoming-list">
                        @foreach ($metrics['upcoming_harvests'] as $harvest)
                            <li class="upcoming-item">
                                <div class="min-w-0">
                                    <p class="upcoming-item__title">{{ $harvest['crop'] ?? $harvest['name'] }}</p>
                                    <p class="upcoming-item__meta">
                                        {{ $harvest['name'] }}
                                        @if ($harvest['parcel']) · {{ $harvest['parcel'] }} @endif
                                        · {{ number_format((float) $harvest['area_m2'], 0, ',', ' ') }} m²
                                    </p>
                                </div>
                                <span class="upcoming-item__badge {{ $harvest['days_left'] !== null && $harvest['days_left'] <= 7 ? 'upcoming-item__badge--soon' : '' }}">
                                    {{ $harvest['expected_date'] ?? '—' }}
                                    @if ($harvest['days_left'] !== null)
                                        · J{{ $harvest['days_left'] }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Stocks sous le seuil" subtitle="Intrants à réapprovisionner">
                @if (count($metrics['critical_inputs']) === 0)
                    <p class="text-sm text-slate-500">Tous les stocks sont au-dessus du seuil minimal.</p>
                @else
                    <ul class="alert-list">
                        @foreach (array_slice($metrics['critical_inputs'], 0, 6) as $input)
                            <li class="alert-item">
                                <span>{{ $input['name'] }}</span>
                                <span class="alert-item__value">{{ number_format((float) $input['quantity'], 1, ',', ' ') }} / {{ number_format((float) $input['minimum_stock'], 1, ',', ' ') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('inputs.index') }}" class="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                        Gérer les stocks &rarr;
                    </a>
                @endif
            </x-card>
        </div>
    </section>

    <x-card class="mt-6" title="Problèmes agricoles récents" subtitle="Constats à suivre en priorité">
        @if (count($metrics['recent_issues']) === 0)
            <p class="text-sm text-slate-500">Aucun problème agricole enregistré.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($metrics['recent_issues'] as $issue)
                    <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <a href="{{ route('issues.show', $issue['id']) }}" class="font-medium text-emerald-700 hover:underline">
                                {{ $issue['reference'] }} — {{ $issue['title'] }}
                            </a>
                            <p class="text-xs text-slate-500">
                                {{ $issue['type'] }} · {{ $issue['observed_at'] }}
                                @if ($issue['parcel']) · {{ $issue['parcel'] }} @endif
                                @if ($issue['campaign']) · {{ $issue['campaign'] }} @endif
                            </p>
                        </div>
                        <span class="shrink-0 rounded px-2 py-0.5 text-[11px] font-semibold
                            {{ $issue['severity_value'] === 'critical' ? 'bg-red-100 text-red-800' : ($issue['severity_value'] === 'high' ? 'bg-orange-100 text-orange-800' : 'bg-amber-100 text-amber-800') }}">
                            {{ $issue['severity_label'] }} · {{ $issue['status_label'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('issues.index', ['status' => 'all']) }}" class="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                Tous les problèmes &rarr;
            </a>
        @endif
    </x-card>

    <x-card class="mt-6" title="Activités récentes">
        @if (count($metrics['recent_activities']) === 0)
            <p class="text-sm text-slate-500">Aucune activité enregistrée.</p>
        @else
            <div class="overflow-x-auto">
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
                                        {{ $activity['status_value'] === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($activity['is_critical'] ? 'bg-red-100 text-red-800' : ($activity['is_late'] ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) }}">
                                        {{ $activity['status_label'] }}@if ($activity['is_late']) · {{ $activity['days_late'] }} j @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
