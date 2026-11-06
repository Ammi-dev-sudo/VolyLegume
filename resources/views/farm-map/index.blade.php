@extends('layouts.app')

@section('title', 'Plan de l’exploitation')

@section('content')
    <div data-farm-map data-has-boundaries="{{ $map['stats']['with_boundary'] > 0 ? 'true' : 'false' }}">
        <section data-print-hide class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-700 px-5 py-6 text-white shadow-xl shadow-emerald-950/20 animate-fade-in-down sm:px-8 sm:py-8">
            <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-emerald-400/20 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-amber-300/20 blur-3xl" style="animation-delay: -8s"></div>
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid opacity-25"></div>

            <div class="relative">
                <span class="inline-flex animate-fade-in-up items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
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
            <div data-map-layout class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(20rem,1fr)]" data-reveal-group="60">
                @php
                    $defaultMode = $map['geometry']['default_mode'] ?? 'plan';
                    $terrain = $map['geometry']['terrain'] ?? [
                        'has_elevation' => false,
                        'source' => null,
                        'note' => 'Aucune source d’altitude fiable n’est configurée : aucun relief réel n’est affiché.',
                    ];
                @endphp

                <x-card class="overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800">Contours des parcelles</h2>
                            <p class="mt-0.5 text-sm text-slate-500">
                                Contours enregistrés en GeoJSON. La 2D les superpose à une imagerie satellite ou au
                                plan OpenStreetMap ; la 3D extrude ces mêmes formes sur l’imagerie, sans inventer de relief.
                            </p>
                        </div>
                        <div data-print-hide class="flex flex-wrap items-center gap-2">
                            <div role="group" aria-label="Mode d’affichage du plan" class="inline-flex flex-wrap rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                                <button type="button" data-map-mode="2d" aria-pressed="{{ $defaultMode === '2d' ? 'true' : 'false' }}" title="Contours réels sur imagerie satellite ou plan OpenStreetMap" class="map-mode-button map-mode-tab {{ $defaultMode === '2d' ? 'is-active' : '' }} inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M4 6.5 9 4l6 2.5L20 4v13.5L15 20l-6-2.5L4 20V6.5Z" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M9 4v13.5M15 6.5V20" stroke-linecap="round" />
                                    </svg>
                                    Contours 2D
                                </button>
                                <button type="button" data-map-mode="3d" aria-pressed="false" title="Extrusion symbolique des contours, sans relief mesuré" class="map-mode-button map-mode-tab inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M12 12v9M4 7.5l8 4.5 8-4.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Extrusion 3D
                                </button>
                                <button type="button" data-map-mode="plan" aria-pressed="{{ $defaultMode === 'plan' ? 'true' : 'false' }}" title="Plan schématique basé sur les surfaces déclarées" class="map-mode-button map-mode-tab {{ $defaultMode === 'plan' ? 'is-active' : '' }} inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="4" y="4" width="7" height="7" rx="1" />
                                        <rect x="13" y="4" width="7" height="7" rx="1" />
                                        <rect x="4" y="13" width="7" height="7" rx="1" />
                                        <rect x="13" y="13" width="7" height="7" rx="1" />
                                    </svg>
                                    Plan indicatif
                                </button>
                            </div>
                            <button type="button" data-map-reset
                                    class="map-mode-button rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold">
                                Recentrer
                            </button>
                            <button type="button" data-map-print
                                    class="map-mode-button inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold"
                                    title="Imprimer le plan ou l’enregistrer en PDF depuis le navigateur">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M7 14h10v7H7v-7Z" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Imprimer / PDF
                            </button>
                        </div>
                    </div>

                    @php
                        $modeStatusLabels = [
                            '2d' => 'Vue 2D · contours enregistrés sur imagerie satellite ou plan',
                            '3d' => 'Vue 3D · contours extrudés sur imagerie plaquée à plat, sans relief mesuré',
                            'plan' => 'Plan indicatif · surfaces déclarées, sans géolocalisation',
                        ];
                    @endphp
                    <p data-map-mode-status class="farm-map-mode-status mt-3" role="status" aria-live="polite">
                        <span class="farm-map-mode-status__dot" aria-hidden="true"></span>
                        <span data-map-mode-status-text>{{ $modeStatusLabels[$defaultMode] ?? $modeStatusLabels['plan'] }}</span>
                    </p>

                    @php
                        $activeLayerKeys = collect($map['layers'])
                            ->filter(fn (array $layer): bool => $layer['available'] && $layer['default'])
                            ->pluck('key')
                            ->all();
                    @endphp
                    <fieldset class="map-layer-panel mt-4" data-map-layer-panel>
                        <legend class="sr-only">Couches d’information de la carte</legend>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Couches d’information</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Activez ou masquez les données enregistrées. Chaque couche affiche sa propre légende.
                                </p>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                {{ count($map['layers']) }} couches
                            </span>
                        </div>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($map['layers'] as $layer)
                                @php
                                    $key = $layer['key'];
                                    $available = $layer['available'];
                                    $checked = $available && $layer['default'];
                                    $descriptionId = 'map-layer-'.$key.'-description';
                                    $reasonId = 'map-layer-'.$key.'-reason';
                                @endphp
                                <div class="map-layer-option {{ $available ? '' : 'is-unavailable' }}">
                                    <label class="map-layer-option__label">
                                        <input type="checkbox"
                                               id="map-layer-{{ $key }}"
                                               data-map-layer="{{ $key }}"
                                               @checked($checked)
                                               @disabled(! $available)
                                               aria-describedby="{{ $descriptionId }}{{ $available ? '' : ' '.$reasonId }}"
                                               class="map-layer-checkbox">
                                        <span class="map-layer-switch" aria-hidden="true"><span></span></span>
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-1.5">
                                                <span class="text-sm font-semibold text-slate-800">{{ $layer['label'] }}</span>
                                                <span class="map-layer-count">{{ $layer['count'] }}</span>
                                            </span>
                                            <span id="{{ $descriptionId }}" class="mt-0.5 block text-[11px] leading-relaxed text-slate-500">
                                                {{ $layer['description'] }}
                                            </span>
                                            @unless ($available)
                                                <span id="{{ $reasonId }}" class="map-layer-reason">
                                                    {{ $layer['reason'] }}
                                                </span>
                                            @endunless
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 grid gap-2" data-map-layer-legends>
                            @foreach ($map['layers'] as $layer)
                                <div data-layer-legend="{{ $layer['key'] }}"
                                     @if (! in_array($layer['key'], $activeLayerKeys, true)) hidden @endif
                                     class="map-layer-legend">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                        Légende · {{ $layer['label'] }}
                                    </p>
                                    <ul class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-600">
                                        @forelse ($layer['legend'] as $entry)
                                            <li class="inline-flex items-center gap-1.5">
                                                <span class="map-layer-swatch" style="--swatch: {{ $entry['color'] }}" aria-hidden="true"></span>
                                                {{ $entry['label'] }}
                                            </li>
                                        @empty
                                            <li class="text-slate-400">Aucune valeur à légender.</li>
                                        @endforelse
                                    </ul>
                                </div>
                            @endforeach
                        </div>

                        <p class="map-layer-note">
                            Les couches indisponibles restent désactivées : aucune culture, campagne, activité, récolte,
                            alerte, irrigation, position ou quantité attendue n’est inventée. Les états de récolte sont
                            calculés à partir des campagnes, des récoltes planifiées et des récoltes enregistrées. Les
                            statuts « à venir » et « en retard » sont calculés à partir des dates et statuts saisis ; les
                            volumes, surfaces et quantités affichés sont ceux réellement enregistrés. Les quantités de
                            récolte gardent leur unité d’origine et ne sont pas additionnées entre unités différentes.
                            Les stocks critiques non géolocalisés restent dans la vue d’ensemble.
                        </p>
                        <p class="sr-only" data-map-layer-status role="status" aria-live="polite"></p>
                    </fieldset>

                    <div class="mt-4 space-y-3" data-map-shell>
                        @if ($map['stats']['without_boundary'] > 0)
                            <div class="farm-map-notice">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">!</span>
                                <p>
                                    <strong>{{ $map['stats']['without_boundary'] }}</strong> parcelle(s) sans contour enregistré
                                    @if (($map['stats']['without_coordinates'] ?? 0) > 0)
                                        (dont <strong>{{ $map['stats']['without_coordinates'] }}</strong> sans coordonnées)
                                    @endif.
                                    Elles restent visibles dans le <button type="button" data-map-mode="plan" class="font-semibold underline underline-offset-2">plan indicatif</button>.
                                    Sur la carte 2D, celles qui possèdent déjà des coordonnées sont signalées par un point
                                    « contour à dessiner » ; aucune position n’est inventée et aucune n’est extrudée en 3D.
                                </p>
                            </div>
                        @endif

                        <div data-map-panel="2d" @if ($defaultMode !== '2d') hidden @endif class="relative overflow-hidden rounded-2xl border border-emerald-200/70 bg-emerald-50 p-1.5 shadow-inner">
                            <div data-map-basemap-group data-print-hide role="group" aria-label="Fond de carte 2D" class="map-basemap-switch">
                                <button type="button" data-map-basemap="satellite" aria-pressed="true" class="map-basemap-button is-active">
                                    Satellite
                                </button>
                                <button type="button" data-map-basemap="plan" aria-pressed="false" class="map-basemap-button">
                                    Plan
                                </button>
                            </div>
                            <div data-map-2d class="farm-map-canvas" role="application" aria-label="Carte 2D des contours de parcelles"></div>
                            <p data-map-tile-status hidden class="farm-map-tile-status" role="status">
                                Fond de carte indisponible (réseau faible). Les contours enregistrés restent affichés.
                            </p>
                            <div data-map-load-error hidden class="farm-map-empty">
                                <p class="text-base font-semibold text-slate-800">Carte 2D indisponible</p>
                                <p class="mt-1 text-sm text-slate-600">La bibliothèque cartographique n’a pas pu être chargée.</p>
                                <button type="button" data-map-mode="plan" class="mt-3 inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">
                                    Utiliser le plan indicatif
                                </button>
                            </div>
                            <span class="farm-map-caption">Imagerie Esri / plan OpenStreetMap · contours enregistrés</span>
                        </div>

                        <div data-map-panel="3d" hidden class="relative overflow-hidden rounded-2xl border border-emerald-200/70 bg-emerald-50 p-1.5 shadow-inner">
                            <div data-map-3d class="farm-map-canvas farm-map-canvas--3d" role="application" aria-label="Vue 3D extrudée des parcelles"></div>
                            <div data-map-empty="3d" hidden class="farm-map-empty">
                                <p class="text-base font-semibold text-slate-800">Aucun contour à extruder</p>
                                <p class="mt-1 text-sm text-slate-600">Dessinez au moins un contour de parcelle pour alimenter la vue 3D.</p>
                                <button type="button" data-map-mode="plan" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                    Voir le plan indicatif
                                </button>
                                @can('create', App\Models\ProductionUnit::class)
                                    <a href="{{ route('production-units.create') }}" class="mt-2 inline-flex rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">
                                        Créer une parcelle
                                    </a>
                                @endcan
                            </div>
                            <p data-map-3d-imagery-status hidden class="farm-map-tile-status farm-map-tile-status--3d" role="status">
                                Imagerie satellite indisponible ; le sol neutre est conservé.
                            </p>
                            <span class="farm-map-caption">Imagerie Esri plaquée à plat · relief non mesuré</span>
                            <p class="farm-map-terrain-note">
                                <strong>Relief non disponible.</strong>
                                {{ $terrain['note'] ?? '' }}
                                @if (! empty($terrain['source']))
                                    Source d’altitude : {{ $terrain['source'] }}.
                                @endif
                            </p>
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
                                            data-harvest-state="{{ $row['harvest_state']['key'] }}"
                                            class="farm-map-plot group absolute {{ $row['has_boundary'] ? '' : 'farm-map-plot--missing' }}"
                                            style="left: {{ $layout['left'] }}%; top: {{ $layout['top'] }}%; width: {{ $layout['width'] }}%; height: {{ $layout['height'] }}%; --plot-color: {{ $row['color'] }}; --plot-dark: {{ $row['color_dark'] }}; --harvest-state-color: {{ $row['harvest_state']['color'] }};"
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
                                                    <span class="mt-0.5 flex min-w-0 items-center gap-1 text-[9px] font-semibold text-white/90">
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $row['harvest_state']['color'] }}"></span>
                                                        <span class="truncate">{{ $row['harvest_state']['label'] }}</span>
                                                    </span>
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <noscript>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                                <p class="font-semibold">Carte interactive indisponible sans JavaScript.</p>
                                <p class="mt-1">Le contour de chaque parcelle reste consultable ci-dessous.</p>
                                <ul class="mt-2 divide-y divide-amber-100">
                                    @foreach ($map['units'] as $row)
                                        <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                                            <span class="min-w-0 truncate">
                                                <span class="font-mono font-semibold">{{ $row['unit']->code }}</span>
                                                · {{ $row['unit']->name }}
                                            </span>
                                            <span class="shrink-0 text-amber-800">
                                                {{ number_format((float) $row['unit']->area_m2, 0, ',', ' ') }} m²
                                                · {{ $row['has_boundary'] ? 'contour enregistré' : 'contour à dessiner' }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </noscript>
                    </div>

                    <div data-print-hide class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Sélection rapide</span>
                        @foreach ($map['units'] as $row)
                            @php
                                $unit = $row['unit'];
                            @endphp
                            <button type="button"
                                    data-map-select="{{ $unit->id }}"
                                    data-harvest-state="{{ $row['harvest_state']['key'] }}"
                                    title="{{ $row['harvest_state']['label'] }}"
                                    class="map-unit-chip {{ $row['has_boundary'] ? '' : 'map-unit-chip--missing' }}"
                                    aria-pressed="false">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $row['harvest_state']['color'] }}"></span>
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
                        @foreach ($map['harvest']['states'] as $state)
                            @if ($state['count'] > 0)
                                <span class="inline-flex items-center gap-1.5" title="{{ $state['description'] }}">
                                    <span class="h-2.5 w-2.5 rounded-sm ring-2 ring-white" style="background-color: {{ $state['color'] }}"></span>
                                    {{ $state['label'] }} ({{ $state['count'] }})
                                </span>
                            @endif
                        @endforeach
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white"></span>
                            Alerte
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full border-2 border-dashed border-amber-600 bg-amber-100"></span>
                            Contour à dessiner
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
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="font-semibold text-slate-700">Récoltes enregistrées</p>
                                <p class="mt-0.5 text-slate-500">
                                    {{ $map['harvest']['recorded_count'] }}
                                    <span class="text-slate-400">· {{ $map['harvest']['planned_count'] }} planifiée(s)</span>
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="font-semibold text-slate-700">Prochaine récolte planifiée</p>
                                <p class="mt-0.5 truncate text-slate-500" title="{{ $map['harvest']['next_planned_label'] }}">
                                    {{ $map['harvest']['next_planned_date'] ?? 'Aucune date' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">
                            Les contours affichés sont les polygones enregistrés. La vue 3D est une
                            <strong class="font-semibold text-slate-700">extrusion des contours</strong>, pas un
                            modèle topographique.
                            @if (empty($terrain['has_elevation']))
                                <strong class="font-semibold text-slate-700">Relief non disponible :</strong>
                                aucune source d’altitude fiable n’est configurée, donc les pentes et altitudes ne sont pas affichées.
                            @endif
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
                                 data-harvest-state="{{ $row['harvest_state']['key'] }}"
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
                                    Aucun contour n’est enregistré : cette parcelle apparaît dans le plan indicatif.
                                    Sur la carte 2D, elle n’est affichée que si une position ponctuelle est déjà connue ;
                                    elle n’est jamais extrudée en 3D.
                                    @can('update', $unit)
                                        <a href="{{ route('production-units.boundary.edit', $unit) }}" class="font-semibold underline underline-offset-2">
                                            Dessiner le contour
                                        </a>
                                    @endcan
                                </div>
                            @endif

                            @php
                                $harvestState = $row['harvest_state'];
                            @endphp
                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3"
                                 style="--state-color: {{ $harvestState['color'] }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex min-w-0 items-start gap-2">
                                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $harvestState['color'] }}"></span>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">État de récolte</p>
                                            <p class="truncate text-sm font-semibold" style="color: {{ $harvestState['color'] }}">{{ $harvestState['label'] }}</p>
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200">
                                        {{ $harvestState['harvest_count'] }} récolte(s)
                                    </span>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $harvestState['reason'] }}</p>
                                <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-slate-200">
                                        <dt class="font-semibold text-slate-500">Dernière récolte</dt>
                                        <dd class="mt-0.5 text-slate-700">
                                            @if ($harvestState['last_harvested_at'])
                                                {{ $harvestState['last_harvested_at'] }}
                                                @if ($harvestState['last_harvest_quantity'] !== null)
                                                    · {{ number_format($harvestState['last_harvest_quantity'], 1, ',', ' ') }} {{ $harvestState['last_harvest_unit'] }}
                                                @endif
                                            @else
                                                Aucune récolte enregistrée
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-slate-200">
                                        <dt class="font-semibold text-slate-500">Prochaine récolte planifiée</dt>
                                        <dd class="mt-0.5 text-slate-700">
                                            @if ($harvestState['next_planned_date'])
                                                {{ $harvestState['next_planned_date'] }}
                                                @if ($harvestState['next_planned_campaign'])
                                                    <span class="block truncate text-[11px] text-slate-500">{{ $harvestState['next_planned_campaign'] }}</span>
                                                @endif
                                            @else
                                                Aucune date planifiée
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-slate-200">
                                        <dt class="font-semibold text-slate-500">Récoltes enregistrées</dt>
                                        <dd class="mt-0.5 text-slate-700">{{ $harvestState['harvest_count'] }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-slate-200">
                                        <dt class="font-semibold text-slate-500">Campagne concernée</dt>
                                        <dd class="mt-0.5 truncate text-slate-700">
                                            @if ($harvestState['campaign'])
                                                <a href="{{ $harvestState['campaign']['url'] }}" class="font-semibold text-emerald-700 hover:text-emerald-800">
                                                    {{ $harvestState['campaign']['crop'] }} · {{ $harvestState['campaign']['status_label'] }}
                                                </a>
                                            @else
                                                Non renseignée
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                                <p class="mt-2 text-[11px] leading-relaxed text-slate-500">
                                    Aucune quantité attendue ni surface récoltée n’est déduite : seules les dates,
                                    quantités et unités réellement enregistrées sont affichées.
                                </p>
                            </div>

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

            @php
                $harvest = $map['harvest'];
                $timelineByMonth = collect($harvest['timeline'])->groupBy('month_key');
            @endphp
            <section data-print-hide class="harvest-panel mt-5 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="harvest-panel__header border-b border-slate-100 bg-gradient-to-br from-emerald-50 via-white to-amber-50 px-4 py-5 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="max-w-2xl">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Suivi des récoltes</p>
                            <h2 class="mt-1 text-xl font-semibold text-slate-800">États et frise temporelle</h2>
                            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                Les états sont calculés à partir des campagnes, des récoltes planifiées et des récoltes
                                enregistrées. Une parcelle sans donnée reste « non renseignée » : aucune date ni quantité
                                n’est inventée.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                                <p class="font-semibold text-slate-500">Enregistrées</p>
                                <p class="mt-0.5 text-lg font-semibold text-slate-800">{{ $harvest['recorded_count'] }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                                <p class="font-semibold text-slate-500">Planifiées</p>
                                <p class="mt-0.5 text-lg font-semibold text-slate-800">{{ $harvest['planned_count'] }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                                <p class="font-semibold text-slate-500">Prochaine</p>
                                <p class="mt-0.5 text-lg font-semibold text-slate-800">{{ $harvest['next_planned_date'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                                <p class="font-semibold text-slate-500">Parcelles</p>
                                <p class="mt-0.5 text-lg font-semibold text-slate-800">{{ $map['stats']['units'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2" data-harvest-filters role="group" aria-label="Filtrer les parcelles par état de récolte">
                        @foreach ($harvest['filters'] as $filter)
                            <button type="button"
                                    data-harvest-filter="{{ $filter['key'] }}"
                                    data-harvest-states="{{ implode(',', $filter['states']) }}"
                                    data-harvest-label="{{ $filter['label'] }}"
                                    aria-pressed="{{ $filter['key'] === 'all' ? 'true' : 'false' }}"
                                    class="harvest-filter-button {{ $filter['key'] === 'all' ? 'is-active' : '' }}">
                                <span>{{ $filter['label'] }}</span>
                                <span class="harvest-filter-count">{{ $filter['count'] }}</span>
                            </button>
                        @endforeach
                        <p class="ml-auto text-[11px] text-slate-500" data-harvest-filter-status role="status" aria-live="polite">
                            Toutes les parcelles sont affichées.
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(17rem,1fr)]">
                    <div class="min-w-0">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-800">Frise des récoltes</h3>
                            <span class="text-[11px] text-slate-500">Aujourd’hui : {{ $harvest['today'] }}</span>
                        </div>

                        <div class="mt-3 space-y-5" data-harvest-timeline>
                            @forelse ($timelineByMonth as $monthKey => $events)
                                <section data-harvest-month="{{ $monthKey }}">
                                    <h4 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
                                        {{ $events->first()['month_label'] ?? $monthKey }}
                                    </h4>
                                    <ol class="mt-2 space-y-1.5">
                                        @foreach ($events as $event)
                                            <li data-harvest-event
                                                data-harvest-state="{{ $event['state_key'] }}"
                                                data-harvest-timestamp="{{ $event['timestamp'] }}">
                                                <a href="{{ $event['url'] }}"
                                                   class="harvest-timeline-event {{ $event['is_future'] ? 'is-future' : 'is-past' }}">
                                                    <span class="harvest-timeline-date">
                                                        <span class="text-sm font-semibold text-slate-700">{{ $event['day_label'] }}</span>
                                                        <span class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold {{ $event['kind'] === 'planned' ? 'text-sky-700' : 'text-emerald-700' }}">
                                                            <span class="h-1.5 w-1.5 rounded-full {{ $event['kind'] === 'planned' ? 'bg-sky-500' : 'bg-emerald-600' }}"></span>
                                                            {{ $event['kind'] === 'planned' ? 'Planifiée' : 'Enregistrée' }}
                                                        </span>
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-semibold text-slate-800">{{ $event['label'] }} · {{ $event['crop'] }}</span>
                                                        <span class="mt-0.5 block truncate text-[11px] text-slate-500">
                                                            {{ $event['unit_code'] }} · {{ $event['unit_name'] }} · {{ $event['campaign'] }}
                                                        </span>
                                                    </span>
                                                    <span class="shrink-0 text-right">
                                                        @if ($event['kind'] === 'recorded')
                                                            <span class="block text-xs font-semibold text-slate-800">
                                                                {{ number_format($event['quantity'], 1, ',', ' ') }} {{ $event['unit'] }}
                                                            </span>
                                                        @else
                                                            <span class="block text-[11px] font-medium text-slate-500">Quantité non renseignée</span>
                                                        @endif
                                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-semibold ring-1 ring-slate-200" style="color: {{ $event['state_color'] }}">
                                                            <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $event['state_color'] }}"></span>
                                                            {{ $event['state_label'] }}
                                                        </span>
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ol>
                                </section>
                            @empty
                                <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-5 text-sm text-slate-500">
                                    Aucune récolte enregistrée ni planifiée sur cette exploitation.
                                </p>
                            @endforelse
                            <p data-harvest-filter-empty hidden class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-5 text-sm text-slate-500">
                                Aucune récolte ne correspond à ce filtre.
                            </p>
                        </div>
                    </div>

                    <aside class="min-w-0">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <h3 class="text-sm font-semibold text-slate-800">Répartition des états</h3>
                            <p class="mt-0.5 text-[11px] text-slate-500">Chaque parcelle compte une fois, selon son état actuel.</p>
                            <ul class="mt-3 space-y-2">
                                @foreach ($harvest['states'] as $state)
                                    @if ($state['count'] > 0)
                                        <li>
                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                <span class="inline-flex min-w-0 items-center gap-1.5 font-semibold text-slate-700">
                                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $state['color'] }}"></span>
                                                    <span class="truncate">{{ $state['label'] }}</span>
                                                </span>
                                                <span class="shrink-0 text-slate-500">{{ $state['count'] }} / {{ $map['stats']['units'] }}</span>
                                            </div>
                                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white ring-1 ring-slate-200">
                                                <div class="h-full rounded-full" style="width: {{ $map['stats']['units'] > 0 ? round($state['count'] / $map['stats']['units'] * 100, 1) : 0 }}%; background-color: {{ $state['color'] }}"></div>
                                            </div>
                                            <p class="mt-1 text-[10px] leading-relaxed text-slate-500">{{ $state['description'] }}</p>
                                        </li>
                                    @endif
                                @endforeach
                                @if (collect($harvest['states'])->every(fn (array $state): bool => $state['count'] === 0))
                                    <li class="text-xs text-slate-500">Aucun état calculable.</li>
                                @endif
                            </ul>
                        </div>

                        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-900">
                            <strong class="font-semibold">Données réelles uniquement.</strong>
                            Une récolte planifiée n’est pas une récolte réalisée. Les quantités restent dans leur unité
                            d’origine et ne sont jamais additionnées entre unités différentes.
                        </div>
                    </aside>
                </div>
            </section>

            @php
                $printLayers = collect($map['layers'])
                    ->filter(fn (array $layer): bool => $layer['available']);
            @endphp
            <section class="print-summary" aria-label="Synthèse imprimable du plan">
                <header class="print-summary__header">
                    <div>
                        <p class="print-summary__eyebrow">VolyLegume · plan de l’exploitation</p>
                        <h2>{{ $farm->name }}</h2>
                        <p>Document généré le {{ $map['generated_at'] }} à partir des données enregistrées.</p>
                    </div>
                    <dl class="print-summary__stats">
                        <div><dt>Parcelles</dt><dd>{{ $map['stats']['units'] }}</dd></div>
                        <div><dt>Surface</dt><dd>{{ number_format($map['stats']['area_m2'], 0, ',', ' ') }} m²</dd></div>
                        <div><dt>Contours</dt><dd>{{ $map['stats']['with_boundary'] }} / {{ $map['stats']['units'] }}</dd></div>
                        <div><dt>Alertes</dt><dd>{{ $map['stats']['alerts'] }}</dd></div>
                    </dl>
                </header>

                <div class="print-summary__content">
                    <section>
                        <h3>Parcelles</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Code</th>
                                    <th scope="col">Parcelle</th>
                                    <th scope="col">Surface</th>
                                    <th scope="col">Contour</th>
                                    <th scope="col">Campagne principale</th>
                                    <th scope="col">État récolte</th>
                                    <th scope="col">Alertes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($map['units'] as $row)
                                    @php
                                        $printedCampaign = $row['primary_campaign'];
                                    @endphp
                                    <tr>
                                        <td class="font-mono">{{ $row['unit']->code }}</td>
                                        <td>
                                            <strong>{{ $row['unit']->name }}</strong>
                                            @if ($row['unit']->location)
                                                <span class="print-summary__muted">{{ $row['unit']->location }}</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $row['unit']->area_m2, 0, ',', ' ') }} m²</td>
                                        <td>{{ $row['has_boundary'] ? 'Enregistré' : 'À dessiner' }}</td>
                                        <td>
                                            @if ($printedCampaign)
                                                {{ $printedCampaign['crop'] ?? $printedCampaign['name'] }}
                                                <span class="print-summary__muted">
                                                    {{ $printedCampaign['name'] }} · {{ $printedCampaign['status_label'] }}
                                                    @if ($printedCampaign['progress'] !== null)
                                                        · {{ $printedCampaign['progress'] }} %
                                                    @endif
                                                </span>
                                            @else
                                                <span class="print-summary__muted">Aucune campagne active</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $row['harvest_state']['label'] }}
                                            @if ($row['harvest_state']['next_planned_date'])
                                                <span class="print-summary__muted">· {{ $row['harvest_state']['next_planned_date'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['alert_count'] > 0 ? $row['alert_count'] : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>

                    <section>
                        <h3>Couches affichées</h3>
                        @forelse ($printLayers as $layer)
                            <div class="print-summary__layer" data-print-layer="{{ $layer['key'] }}">
                                <p><strong>{{ $layer['label'] }}</strong> · {{ $layer['count'] }}</p>
                                <ul>
                                    @forelse ($layer['legend'] as $entry)
                                        <li>{{ $entry['label'] }}</li>
                                    @empty
                                        <li>Aucune valeur enregistrée.</li>
                                    @endforelse
                                </ul>
                            </div>
                        @empty
                            <p>Aucune couche active.</p>
                        @endforelse
                    </section>
                </div>

                <footer class="print-summary__note">
                    <p>
                        Les couches ne contiennent que des données enregistrées pour l’exploitation active. Les mentions
                        « à venir » et « en retard » sont calculées à partir des dates et statuts saisis. Les quantités de
                        récolte conservent leur unité d’origine et ne sont pas additionnées entre unités différentes.
                    </p>
                    <p>
                        La vue 3D n’est pas imprimée : elle est une extrusion symbolique des contours, sans source
                        d’altitude fiable. Aucun relief, aucune position et aucune quantité ne sont inventés.
                    </p>
                </footer>
            </section>
        @endif
    </div>

    <script type="application/json" data-farm-map-config>{!! json_encode($map['geometry'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
