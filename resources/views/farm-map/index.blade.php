@extends('layouts.app')

@section('title', 'Plan de l’exploitation')

@section('content')
    <div data-farm-map>
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-700 px-5 py-6 text-white shadow-xl shadow-emerald-950/20 animate-fade-in-down sm:px-8 sm:py-8">
            <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-emerald-400/20 blur-3xl animate-blob"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-amber-300/20 blur-3xl animate-blob" style="animation-delay: -8s"></div>
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid opacity-25"></div>

            <div class="relative">
                <span class="inline-flex animate-fade-in-up items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse-soft"></span>
                    Vue de gestion
                </span>
                <h1 class="mt-4 animate-fade-in-up text-3xl font-bold leading-tight sm:text-4xl" style="animation-delay: 80ms">
                    Le plan de <span class="gradient-text">{{ $farm->name }}</span>
                </h1>
                <p class="mt-3 max-w-2xl animate-fade-in-up text-sm leading-relaxed text-emerald-50/80" style="animation-delay: 150ms">
                    Repérez les parcelles d’un coup d’œil, ouvrez leur campagne et repérez les activités en retard,
                    les récoltes bloquées ou les stocks critiques.
                </p>

                <div class="mt-5 grid animate-fade-in-up grid-cols-2 gap-3 sm:grid-cols-4" style="animation-delay: 220ms">
                    <div class="rounded-2xl bg-white/10 p-3 ring-1 ring-white/15">
                        <p class="text-[11px] uppercase tracking-wide text-emerald-100/70">Parcelles</p>
                        <p class="mt-1 text-2xl font-semibold" data-count="{{ $map['stats']['units'] }}">{{ $map['stats']['units'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-3 ring-1 ring-white/15">
                        <p class="text-[11px] uppercase tracking-wide text-emerald-100/70">Surface</p>
                        <p class="mt-1 text-2xl font-semibold" data-count="{{ $map['stats']['area_m2'] }}" data-count-decimals="0" data-count-suffix=" m²">
                            {{ number_format($map['stats']['area_m2'], 0, ',', ' ') }} m²
                        </p>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-3 ring-1 ring-white/15">
                        <p class="text-[11px] uppercase tracking-wide text-emerald-100/70">Campagnes actives</p>
                        <p class="mt-1 text-2xl font-semibold" data-count="{{ $map['stats']['active_campaigns'] }}">{{ $map['stats']['active_campaigns'] }}</p>
                    </div>
                    <div class="rounded-2xl {{ $map['stats']['alerts'] > 0 ? 'bg-red-500/20 ring-red-200/40' : 'bg-white/10 ring-white/15' }} p-3 ring-1">
                        <p class="text-[11px] uppercase tracking-wide text-emerald-100/70">Alertes</p>
                        <p class="mt-1 text-2xl font-semibold {{ $map['stats']['alerts'] > 0 ? 'text-red-100' : '' }}" data-count="{{ $map['stats']['alerts'] }}">{{ $map['stats']['alerts'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        @if (count($map['units']) === 0)
            <x-card class="mt-6">
                <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 ring-1 ring-amber-100">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 6.5 9 4l6 2.5L20 4v13.5L15 20l-6-2.5L4 20V6.5Z" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9 4v13.5M15 6.5V20" stroke-linecap="round" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">Aucune parcelle à afficher</h2>
                        <p class="mt-1 text-sm text-slate-500">Créez une première parcelle pour construire le plan de l’exploitation.</p>
                    </div>
                    @can('create', App\Models\ProductionUnit::class)
                        <a href="{{ route('production-units.create') }}"
                           class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">
                            Créer une parcelle
                        </a>
                    @endcan
                </div>
            </x-card>
        @else
            <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(20rem,1fr)]" data-reveal-group="60">
                @php
                    $defaultMode = $map['geometry']['default_mode'] ?? 'plan';
                @endphp

                <x-card class="overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">Contours des parcelles</h2>
                            <p class="mt-0.5 text-sm text-slate-500">
                                Contours enregistrés en GeoJSON. La 3D extrude ces mêmes formes, sans données d’altitude.
                            </p>
                        </div>
                        <div role="group" aria-label="Mode d’affichage du plan" class="inline-flex flex-wrap rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                            <button type="button" data-map-mode="2d" aria-pressed="{{ $defaultMode === '2d' ? 'true' : 'false' }}" class="map-mode-button {{ $defaultMode === '2d' ? 'is-active' : '' }} rounded-lg px-3 py-1.5">
                                Contours 2D
                            </button>
                            <button type="button" data-map-mode="3d" aria-pressed="false" class="map-mode-button rounded-lg px-3 py-1.5">
                                Extrusion 3D
                            </button>
                            <button type="button" data-map-mode="plan" aria-pressed="{{ $defaultMode === 'plan' ? 'true' : 'false' }}" class="map-mode-button {{ $defaultMode === 'plan' ? 'is-active' : '' }} rounded-lg px-3 py-1.5">
                                Plan indicatif
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3" data-map-shell>
                        @if ($map['stats']['without_boundary'] > 0)
                            <div class="farm-map-notice">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">!</span>
                                <p>
                                    <strong>{{ $map['stats']['without_boundary'] }}</strong> parcelle(s) sans contour enregistré.
                                    Elles restent visibles dans le <button type="button" data-map-mode="plan" class="font-semibold underline underline-offset-2">plan indicatif</button>
                                    et ne peuvent pas être extrudées en 3D.
                                </p>
                            </div>
                        @endif

                        <div data-map-panel="2d" @if ($defaultMode !== '2d') hidden @endif class="relative overflow-hidden rounded-2xl border border-emerald-200/70 bg-emerald-50 p-1.5 shadow-inner">
                            <div data-map-2d class="farm-map-canvas" role="application" aria-label="Carte 2D des contours de parcelles"></div>
                            <span class="farm-map-caption">Fond © OpenStreetMap · contours enregistrés</span>
                        </div>

                        <div data-map-panel="3d" hidden class="relative overflow-hidden rounded-2xl border border-emerald-200/70 bg-emerald-50 p-1.5 shadow-inner">
                            <div data-map-3d class="farm-map-canvas farm-map-canvas--3d" role="application" aria-label="Vue 3D extrudée des parcelles"></div>
                            <div data-map-empty="3d" hidden class="farm-map-empty">
                                <p class="text-base font-semibold text-slate-800">Aucun contour à extruder</p>
                                <p class="mt-1 text-sm text-slate-600">Dessinez au moins un contour de parcelle pour alimenter la vue 3D.</p>
                                @can('create', App\Models\ProductionUnit::class)
                                    <a href="{{ route('production-units.create') }}" class="mt-3 inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">
                                        Créer une parcelle
                                    </a>
                                @endcan
                            </div>
                            <span class="farm-map-caption">Extrusion symbolique · aucune donnée d’altitude</span>
                        </div>

                        <div data-map-panel="plan" @if ($defaultMode !== 'plan') hidden @endif class="rounded-2xl border border-emerald-200/70 bg-emerald-50 p-1.5 shadow-inner">
                            <p class="px-2 pb-1.5 pt-1 text-xs text-slate-500">
                                Plan schématique de secours : les positions suivent les surfaces déclarées, sans géolocalisation.
                            </p>
                            <div data-map-stage
                                 class="farm-map-stage relative aspect-[16/10] w-full overflow-hidden rounded-xl"
                                 role="group"
                                 aria-label="Plan indicatif des parcelles">
                                <span class="farm-map-compass" aria-hidden="true">N</span>
                                <span class="farm-map-caption">Plan indicatif · cliquez sur une parcelle</span>

                                @foreach ($map['units'] as $row)
                                    @php
                                        $unit = $row['unit'];
                                        $layout = $row['layout'];
                                        $campaign = $row['primary_campaign'];
                                    @endphp
                                    <button type="button"
                                            data-plot-target="plot-detail-{{ $unit->id }}"
                                            class="farm-map-plot group absolute"
                                            style="left: {{ $layout['left'] }}%; top: {{ $layout['top'] }}%; width: {{ $layout['width'] }}%; height: {{ $layout['height'] }}%; --plot-color: {{ $row['color'] }}; --plot-dark: {{ $row['color_dark'] }};"
                                            aria-label="Afficher la parcelle {{ $unit->name }} ({{ $unit->code }})">
                                        <span class="farm-map-plot-face">
                                            <span class="relative z-10 flex h-full flex-col justify-between gap-1">
                                                <span class="flex items-start justify-between gap-1">
                                                    <span class="rounded bg-black/20 px-1.5 py-0.5 font-mono text-[10px] font-bold tracking-wide text-white">
                                                        {{ $unit->code }}
                                                    </span>
                                                    @if ($row['alert_count'] > 0)
                                                        <span class="farm-map-alert" title="{{ $row['alert_count'] }} alerte(s)">{{ $row['alert_count'] }}</span>
                                                    @endif
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-xs font-semibold leading-tight">{{ $unit->name }}</span>
                                                    <span class="mt-0.5 block truncate text-[10px] text-white/85">
                                                        {{ $row['legend_label'] }}
                                                        @if ($campaign)
                                                            · {{ $campaign['status_label'] }}
                                                        @endif
                                                    </span>
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Sélection rapide</span>
                        @foreach ($map['units'] as $row)
                            @php
                                $unit = $row['unit'];
                            @endphp
                            <button type="button"
                                    data-map-select="{{ $unit->id }}"
                                    class="map-unit-chip {{ $row['has_boundary'] ? '' : 'map-unit-chip--missing' }}"
                                    aria-pressed="false">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                <span class="font-mono">{{ $unit->code }}</span>
                                @if (! $row['has_boundary'])
                                    <span class="text-[10px] font-medium text-amber-700">contour à dessiner</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600">
                        @foreach ($map['legend'] as $entry)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full ring-2 ring-white" style="background-color: {{ $entry['color'] }}"></span>
                                {{ $entry['label'] }}
                            </span>
                        @endforeach
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white"></span>
                            Alerte
                        </span>
                        <span class="ml-auto text-slate-400">Mise à jour : {{ $map['generated_at'] }}</span>
                    </div>
                </x-card>

                <aside class="min-w-0">
                    <div data-map-overview class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-800">Vue d’ensemble</h2>
                                <p class="mt-0.5 text-sm text-slate-500">Sélectionnez une parcelle pour afficher sa campagne et ses prochaines actions.</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                {{ $map['stats']['units'] }} parcelle(s)
                            </span>
                        </div>

                        <div class="mt-4 space-y-2 text-sm">
                            @if ($map['stats']['overdue_activities'] > 0)
                                <a href="{{ route('activities.index') }}"
                                   class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-red-900 transition hover:border-red-300 hover:bg-red-100">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white">!</span>
                                    <span>
                                        <span class="block font-semibold">{{ $map['stats']['overdue_activities'] }} activité(s) en retard</span>
                                        <span class="block text-xs text-red-700">Ouvrez les activités pour replanifier ou terminer.</span>
                                    </span>
                                </a>
                            @endif

                            @if ($map['stats']['blocked_harvests'] > 0)
                                <a href="{{ route('harvests.index') }}"
                                   class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-amber-900 transition hover:border-amber-300 hover:bg-amber-100">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">!</span>
                                    <span>
                                        <span class="block font-semibold">{{ $map['stats']['blocked_harvests'] }} récolte(s) bloquée(s)</span>
                                        <span class="block text-xs text-amber-700">Le délai avant récolte d’un traitement n’est pas écoulé.</span>
                                    </span>
                                </a>
                            @endif

                            @if ($map['stats']['critical_inputs'] > 0)
                                <a href="{{ route('inputs.index') }}"
                                   class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-amber-900 transition hover:border-amber-300 hover:bg-amber-100">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">!</span>
                                    <span>
                                        <span class="block font-semibold">{{ $map['stats']['critical_inputs'] }} stock(s) critique(s)</span>
                                        <span class="block text-xs text-amber-700">
                                            {{ collect($map['critical_inputs'])->take(3)->pluck('name')->join(', ') }}
                                        </span>
                                    </span>
                                </a>
                            @endif

                            @if ($map['stats']['alerts'] === 0)
                                <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-emerald-900">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">✓</span>
                                    <span>
                                        <span class="block font-semibold">Aucune alerte en cours</span>
                                        <span class="block text-xs text-emerald-700">Les activités, les récoltes et les stocks sont à jour.</span>
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="font-semibold text-slate-700">Contours renseignés</p>
                                <p class="mt-0.5 text-slate-500">
                                    {{ $map['stats']['with_boundary'] }} / {{ $map['stats']['units'] }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="font-semibold text-slate-700">Surface dessinée</p>
                                <p class="mt-0.5 text-slate-500">
                                    {{ number_format($map['stats']['mapped_area_m2'], 0, ',', ' ') }} m²
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">
                            Les contours affichés sont les polygones enregistrés. La vue 3D est une
                            <strong class="font-semibold text-slate-700">extrusion symbolique</strong> de ces formes :
                            elle n’utilise aucune donnée d’altitude et ne constitue pas un modèle topographique.
                        </div>

                        @php
                            $missing = collect($map['units'])->reject(fn (array $row): bool => $row['has_boundary']);
                        @endphp
                        @if ($missing->isNotEmpty())
                            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Contours à renseigner</p>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($missing as $row)
                                        <li class="flex items-center justify-between gap-2 text-xs text-amber-900">
                                            <span class="min-w-0 truncate">
                                                <span class="font-mono font-semibold">{{ $row['unit']->code }}</span>
                                                · {{ $row['unit']->name }}
                                            </span>
                                            @can('update', $row['unit'])
                                                <a href="{{ route('production-units.boundary.edit', $row['unit']) }}"
                                                   class="shrink-0 font-semibold text-amber-900 underline underline-offset-2 hover:text-amber-950">
                                                    Dessiner
                                                </a>
                                            @endcan
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    @foreach ($map['units'] as $row)
                        @php
                            $unit = $row['unit'];
                        @endphp
                        <article id="plot-detail-{{ $unit->id }}"
                                 data-map-detail="plot-detail-{{ $unit->id }}"
                                 class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                                 hidden>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-xs font-semibold text-emerald-700">{{ $unit->code }}</p>
                                    <h2 class="truncate text-lg font-semibold text-slate-800">{{ $unit->name }}</h2>
                                    <p class="mt-0.5 text-sm text-slate-500">{{ $unit->location }}</p>
                                </div>
                                <button type="button" data-map-close
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                                        aria-label="Revenir à la vue d’ensemble">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ number_format((float) $unit->area_m2, 0, ',', ' ') }} m²</span>
                                @if ($unit->length_m !== null && $unit->width_m !== null)
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">
                                        {{ rtrim(rtrim(number_format((float) $unit->length_m, 2, ',', ' '), '0'), ',') }}
                                        ×
                                        {{ rtrim(rtrim(number_format((float) $unit->width_m, 2, ',', ' '), '0'), ',') }} m
                                    </span>
                                @endif
                                <span class="rounded-full px-2.5 py-1 {{ $unit->status->value === 'inactive' ? 'bg-slate-200 text-slate-600' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' }}">
                                    {{ $unit->status->label() }}
                                </span>
                                @if ($row['has_boundary'])
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700 ring-1 ring-emerald-100">
                                        Contour · {{ number_format((float) $row['boundary_area_m2'], 0, ',', ' ') }} m²
                                    </span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700 ring-1 ring-amber-200">
                                        Contour à dessiner
                                    </span>
                                @endif
                            </div>

                            @if (! $row['has_boundary'])
                                <div class="mt-3 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                    Aucun contour n’est enregistré : cette parcelle apparaît dans le plan indicatif,
                                    mais pas dans les vues 2D et 3D.
                                    @can('update', $unit)
                                        <a href="{{ route('production-units.boundary.edit', $unit) }}" class="font-semibold underline underline-offset-2">
                                            Dessiner le contour
                                        </a>
                                    @endcan
                                </div>
                            @endif

                            @if ($row['alert_count'] > 0)
                                <ul class="mt-3 space-y-1.5">
                                    @foreach ($row['alerts'] as $alert)
                                        <li class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-2.5 py-2 text-xs text-red-800">
                                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white">!</span>
                                            {{ $alert['label'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($row['unit']->photo_path || $row['photo_count'] > 0)
                                <div class="mt-4">
                                    <h3 class="text-sm font-semibold text-slate-800">Photos de la parcelle</h3>
                                    @if ($row['unit']->photo_path)
                                        <div class="mt-2">
                                            <img src="{{ asset('storage/'.$row['unit']->photo_path) }}" alt="Photo principale de {{ $row['unit']->name }}" class="h-40 w-full rounded-xl object-cover">
                                        </div>
                                    @endif
                                    @if ($row['photo_count'] > 0)
                                        <div class="mt-2 grid grid-cols-2 gap-2">
                                            @foreach ($row['photos'] as $photo)
                                                <figure class="overflow-hidden rounded-xl border border-slate-200">
                                                    <img src="{{ $photo['url'] }}" alt="{{ $photo['caption'] ?? 'Photo de parcelle' }}" class="h-28 w-full object-cover">
                                                    @if ($photo['caption'] || $photo['taken_at'])
                                                        <figcaption class="px-2 py-1 text-[10px] text-slate-500">
                                                            {{ $photo['caption'] ?? 'Photo' }}@if($photo['taken_at']) · {{ $photo['taken_at'] }}@endif
                                                        </figcaption>
                                                    @endif
                                                    @can('delete', $photo['model'])
                                                        <form method="POST" action="{{ route('production-units.photos.destroy', $photo['model']) }}" class="px-2 pb-1"
                                                              onsubmit="return confirm('Supprimer cette photo ?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-[10px] font-semibold text-red-600 hover:underline">Supprimer</button>
                                                        </form>
                                                    @endcan
                                                </figure>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @can('create', \App\Models\ProductionUnitPhoto::class)
                                <form method="POST" action="{{ route('production-units.photos.store', $row['unit']) }}"
                                      enctype="multipart/form-data" class="mt-3 space-y-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
                                    @csrf
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ajouter une photo</p>
                                    <input type="file" name="photo" accept="image/*" required
                                           class="w-full rounded border border-slate-300 bg-white px-2 py-1 text-xs">
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <input type="text" name="caption" maxlength="255" placeholder="Légende (facultatif)"
                                               class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                        <input type="date" name="taken_at" value="{{ now()->toDateString() }}"
                                               class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                    </div>
                                    @if (count($row['campaigns']) > 0)
                                        <select name="campaign_id" class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                            <option value="">Sans campagne</option>
                                            @foreach ($row['campaigns'] as $campaign)
                                                <option value="{{ $campaign['id'] }}">{{ $campaign['name'] }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="submit" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                                        Téléverser
                                    </button>
                                </form>
                            @endcan

                            @if (count($row['campaigns']) === 0)
                                <p class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-sm text-slate-500">
                                    Aucune campagne active sur cette parcelle.
                                </p>
                            @else
                                <div class="mt-4 space-y-2">
                                    @foreach ($row['campaigns'] as $campaign)
                                        <div class="rounded-xl border border-slate-200 p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-slate-800">
                                                        {{ $campaign['crop'] ?? $campaign['name'] }}
                                                        @if ($campaign['variety'])
                                                            <span class="font-normal text-slate-500">· {{ $campaign['variety'] }}</span>
                                                        @endif
                                                    </p>
                                                    <p class="truncate text-xs text-slate-500">{{ $campaign['name'] }}</p>
                                                </div>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold
                                                    {{ $campaign['status'] === 'ongoing' ? 'bg-emerald-100 text-emerald-800' : ($campaign['status'] === 'planned' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800') }}">
                                                    {{ $campaign['status_label'] }}
                                                </span>
                                            </div>

                                            @if ($campaign['progress'] !== null)
                                                <div class="mt-2">
                                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $campaign['progress'] }}%"></div>
                                                    </div>
                                                    <div class="mt-1 flex justify-between text-[10px] text-slate-500">
                                                        <span>{{ $campaign['start_date'] ?? '—' }}</span>
                                                        <span>{{ $campaign['progress'] }} %</span>
                                                        <span>{{ $campaign['expected_end_date'] ?? '—' }}</span>
                                                    </div>
                                                </div>
                                            @endif

                                            <a href="{{ $campaign['url'] }}" class="mt-2 inline-flex text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                                Ouvrir la campagne &rarr;
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if (count($row['next_activities']) > 0)
                                <div class="mt-4">
                                    <h3 class="text-sm font-semibold text-slate-800">Prochaines activités</h3>
                                    <ul class="mt-2 space-y-1.5">
                                        @foreach ($row['next_activities'] as $activity)
                                            <li>
                                                <a href="{{ $activity['url'] }}"
                                                   class="flex items-start justify-between gap-3 rounded-lg px-2 py-1.5 text-sm transition hover:bg-emerald-50">
                                                    <span class="min-w-0">
                                                        <span class="block truncate font-medium text-slate-700">{{ $activity['title'] }}</span>
                                                        <span class="block truncate text-[11px] text-slate-500">{{ $activity['campaign'] }}</span>
                                                    </span>
                                                    <span class="shrink-0 text-xs font-semibold text-emerald-700">{{ $activity['date'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($row['overdue_count'] > 0)
                                <div class="mt-4">
                                    <h3 class="text-sm font-semibold text-red-800">Activités en retard</h3>
                                    <ul class="mt-2 space-y-1.5">
                                        @foreach ($row['overdue_activities'] as $activity)
                                            <li>
                                                <a href="{{ $activity['url'] }}"
                                                   class="flex items-start justify-between gap-3 rounded-lg bg-red-50 px-2 py-1.5 text-sm transition hover:bg-red-100">
                                                    <span class="min-w-0">
                                                        <span class="block truncate font-medium text-red-800">{{ $activity['title'] }}</span>
                                                        <span class="block truncate text-[11px] text-red-600">{{ $activity['campaign'] }}</span>
                                                    </span>
                                                    <span class="shrink-0 text-xs font-semibold text-red-700">{{ $activity['date'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (count($row['harvests']) > 0)
                                <div class="mt-4">
                                    <h3 class="text-sm font-semibold text-slate-800">Récoltes récentes</h3>
                                    <ul class="mt-2 space-y-1 text-sm">
                                        @foreach ($row['harvests'] as $harvest)
                                            <li class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-2 py-1.5">
                                                <span class="min-w-0 truncate text-slate-600">{{ $harvest['campaign'] }}</span>
                                                <span class="shrink-0 text-xs font-semibold text-slate-800">
                                                    {{ number_format($harvest['quantity'], 1, ',', ' ') }} {{ $harvest['unit'] }}
                                                    <span class="font-normal text-slate-400">· {{ $harvest['date'] }}</span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                                @if ($row['primary_campaign'])
                                    <a href="{{ $row['primary_campaign']['url'] }}"
                                       class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-800">
                                        Voir la campagne
                                    </a>
                                @endif
                                @can('update', $unit)
                                    <a href="{{ route('production-units.boundary.edit', $unit) }}"
                                       class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-50">
                                        {{ $row['has_boundary'] ? 'Modifier le contour' : 'Dessiner le contour' }}
                                    </a>
                                    <a href="{{ route('production-units.edit', $unit) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                                        Modifier la parcelle
                                    </a>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </aside>
            </div>
        @endif
    </div>

    <script type="application/json" data-farm-map-config>{!! json_encode($map['geometry'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
