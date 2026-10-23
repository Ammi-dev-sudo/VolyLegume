@extends('layouts.app')

@section('title', 'Agenda')

@php
    $eventStyles = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100',
        'red' => 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100',
        'slate' => 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100',
        'lime' => 'border-lime-200 bg-lime-50 text-lime-800 hover:bg-lime-100',
        'violet' => 'border-violet-200 bg-violet-50 text-violet-800 hover:bg-violet-100',
        'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-800 hover:bg-indigo-100',
        'purple' => 'border-purple-200 bg-purple-50 text-purple-800 hover:bg-purple-100',
    ];

    $dotStyles = [
        'emerald' => 'bg-emerald-500',
        'sky' => 'bg-sky-500',
        'red' => 'bg-red-500',
        'slate' => 'bg-slate-400',
        'amber' => 'bg-amber-500',
        'rose' => 'bg-rose-500',
        'lime' => 'bg-lime-500',
        'violet' => 'bg-violet-500',
        'indigo' => 'bg-indigo-500',
        'purple' => 'bg-purple-500',
    ];

    $categories = [
        'all' => 'Tout',
        'activities' => 'Activités',
        'harvests' => 'Récoltes',
        'treatments' => 'Traitements',
        'campaigns' => 'Campagnes',
    ];

    $baseQuery = array_filter([
        'month' => $monthStart->format('Y-m'),
        'campaign_id' => $campaignId,
    ]);

    $monthLink = fn (string $month) => route('calendar.index', array_filter([
        'month' => $month,
        'show' => $show !== 'all' ? $show : null,
        'campaign_id' => $campaignId,
    ]));

    $legend = [
        ['label' => 'Activité prévue', 'color' => 'sky'],
        ['label' => 'Activité réalisée', 'color' => 'emerald'],
        ['label' => 'Activité en retard', 'color' => 'red'],
        ['label' => 'Activité annulée', 'color' => 'slate'],
        ['label' => 'Récolte', 'color' => 'amber'],
        ['label' => 'Traitement', 'color' => 'rose'],
        ['label' => 'Fin du délai avant récolte', 'color' => 'lime'],
        ['label' => 'Campagne', 'color' => 'violet'],
    ];
@endphp

@section('content')
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-700 px-5 py-6 text-white shadow-xl shadow-emerald-950/20 animate-fade-in-down sm:px-8">
        <div aria-hidden="true" class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-emerald-400/25 blur-3xl animate-blob"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-20 left-1/4 h-48 w-48 rounded-full bg-amber-300/20 blur-3xl animate-blob" style="animation-delay: -7s"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid opacity-25"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-5">
            <div>
                <span class="inline-flex animate-fade-in-up items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse-soft"></span>
                    Vue mensuelle
                </span>
                <h1 class="mt-3 animate-fade-in-up text-2xl font-bold sm:text-3xl" style="animation-delay: 70ms">
                    Agenda de <span class="gradient-text">{{ $currentFarm->name }}</span>
                </h1>
                <p class="mt-2 max-w-xl animate-fade-in-up text-sm text-emerald-50/80" style="animation-delay: 140ms">
                    Toutes les échéances au même endroit : activités, récoltes, traitements et campagnes.
                </p>
            </div>

            <div class="flex animate-fade-in-up flex-wrap items-center gap-2" style="animation-delay: 200ms">
                <a href="{{ $monthLink($previousMonth) }}" aria-label="Mois précédent"
                   class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20 transition hover:-translate-x-0.5 hover:bg-white/20">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
                <span class="min-w-[9.5rem] rounded-full bg-white/10 px-4 py-2 text-center text-sm font-semibold capitalize ring-1 ring-white/20">
                    {{ $monthLabel }}
                </span>
                <a href="{{ $monthLink($nextMonth) }}" aria-label="Mois suivant"
                   class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/20 transition hover:translate-x-0.5 hover:bg-white/20">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
                <a href="{{ $monthLink($currentMonth) }}"
                   class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow transition hover:-translate-y-0.5 hover:shadow-lg">
                    Aujourd’hui
                </a>
            </div>
        </div>
    </section>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-reveal-group="60">
        <a href="{{ route('activities.index', ['from' => $monthStart->toDateString(), 'to' => $monthEnd->toDateString()]) }}"
           class="resource-card flex animate-fade-in-up items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" data-reveal>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 ring-1 ring-sky-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <span>
                <span class="block text-2xl font-semibold text-slate-800">{{ $summary['activities'] }}</span>
                <span class="block text-xs text-slate-500">Activités planifiées</span>
                @if ($summary['late'] > 0)
                    <span class="mt-0.5 inline-block rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700">
                        {{ $summary['late'] }} en retard
                    </span>
                @endif
            </span>
        </a>

        <a href="{{ route('harvests.index') }}"
           class="resource-card flex animate-fade-in-up items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" style="animation-delay: 60ms" data-reveal>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-amber-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path d="M12 3v18M7 8c0 4 2.5 6 5 6s5-2 5-6M7 16h10" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <span>
                <span class="block text-2xl font-semibold text-slate-800">{{ $summary['harvests'] }}</span>
                <span class="block text-xs text-slate-500">Récoltes enregistrées</span>
            </span>
        </a>

        <a href="{{ route('treatments.index') }}"
           class="resource-card flex animate-fade-in-up items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" style="animation-delay: 120ms" data-reveal>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <path d="M9 3h6M12 3v5M6 21l12-12M15 6l3 3M4 19l1 1 2-2-1-1-2 2Z" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <span>
                <span class="block text-2xl font-semibold text-slate-800">{{ $summary['treatments'] }}</span>
                <span class="block text-xs text-slate-500">Traitements appliqués</span>
            </span>
        </a>

        <a href="{{ route('campaigns.index') }}"
           class="resource-card flex animate-fade-in-up items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" style="animation-delay: 180ms" data-reveal>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2" />
                    <path d="M8 3v4M16 3v4M3 10h18" stroke-linecap="round" />
                </svg>
            </span>
            <span>
                <span class="block text-2xl font-semibold text-slate-800">{{ $summary['campaigns'] }}</span>
                <span class="block text-xs text-slate-500">Campagnes sur la période</span>
            </span>
        </a>
    </div>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-wrap gap-1.5">
            @foreach ($categories as $key => $label)
                @php
                    $isActive = $show === $key;
                    $url = route('calendar.index', array_filter([
                        'month' => $monthStart->format('Y-m'),
                        'campaign_id' => $campaignId,
                        'show' => $key !== 'all' ? $key : null,
                    ]));
                @endphp
                <a href="{{ $url }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $isActive ? 'bg-emerald-700 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-800' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('calendar.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
            <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
            @if ($show !== 'all')
                <input type="hidden" name="show" value="{{ $show }}">
            @endif
            <label for="campaign_id" class="font-medium text-slate-500">Campagne</label>
            <select id="campaign_id" name="campaign_id"
                    class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                <option value="">Toutes</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected($campaignId === $campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-slate-700 px-3 py-1.5 font-semibold text-white transition hover:bg-slate-800">
                Filtrer
            </button>
        </form>
    </div>

    @if (count($events) === 0)
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <p>Aucun événement pour cette période. Planifiez une activité ou générez un calendrier de campagne.</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('activities.create') }}" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">Nouvelle activité</a>
                <a href="{{ route('campaigns.index') }}" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-white">Voir les campagnes</a>
            </div>
        </div>
    @endif

    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm animate-fade-in-up">
        <div class="overflow-x-auto">
            <div class="min-w-[46rem]">
                <div class="grid grid-cols-7 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-emerald-50/60 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $weekday)
                        <div class="px-2 py-2.5 text-center">{{ $weekday }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @foreach ($days as $day)
                        @php
                            $key = $day->toDateString();
                            $dayEvents = $events[$key] ?? [];
                            $isCurrentMonth = $day->month === $monthStart->month && $day->year === $monthStart->year;
                            $isToday = $day->isToday();
                            $isWeekend = $day->isWeekend();
                        @endphp
                        <div class="group relative min-h-[8.5rem] border-b border-r border-slate-100 p-1.5 transition duration-200 hover:bg-slate-50/80 {{ $isCurrentMonth ? 'bg-white' : 'bg-slate-50/60' }} {{ $isToday ? 'ring-2 ring-inset ring-emerald-400' : '' }}">
                            <div class="flex items-center justify-between gap-1">
                                <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs font-semibold {{ $isToday ? 'bg-emerald-600 text-white shadow' : ($isCurrentMonth ? ($isWeekend ? 'text-slate-400' : 'text-slate-700') : 'text-slate-300') }}">
                                    {{ $day->day }}
                                </span>
                                @if (count($dayEvents) > 0)
                                    <span class="rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold text-slate-500">{{ count($dayEvents) }}</span>
                                @endif
                            </div>

                            <div class="mt-1 max-h-[7rem] space-y-1 overflow-y-auto pr-0.5 [scrollbar-width:thin]">
                                @foreach ($dayEvents as $event)
                                    <a href="{{ $event['url'] }}"
                                       title="{{ $event['label'] }} — {{ $event['meta'] }}"
                                       class="flex items-start gap-1.5 rounded-md border px-1.5 py-1 text-[11px] leading-tight transition hover:-translate-y-0.5 {{ $eventStyles[$event['color']] ?? $eventStyles['slate'] }}">
                                        <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $dotStyles[$event['color']] ?? $dotStyles['slate'] }}"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-semibold">{{ $event['label'] }}</span>
                                            <span class="block truncate text-[10px] opacity-70">
                                                @if ($event['time'])
                                                    {{ $event['time'] }} ·
                                                @endif
                                                {{ $event['meta'] }}
                                            </span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-2xl border border-slate-200 bg-white p-4 text-xs text-slate-600 shadow-sm">
        <span class="font-semibold text-slate-700">Légende :</span>
        @foreach ($legend as $item)
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full {{ $dotStyles[$item['color']] }}"></span>
                {{ $item['label'] }}
            </span>
        @endforeach
        <span class="ml-auto text-slate-400 sm:hidden">Faites glisser pour voir toute la semaine.</span>
    </div>
@endsection
