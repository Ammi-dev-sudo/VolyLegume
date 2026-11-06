@extends('layouts.app')

@section('title', 'Contour de la parcelle')

@section('content')
    <div class="mx-auto max-w-5xl">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-mono text-xs font-semibold text-emerald-700">{{ $productionUnit->code }}</p>
                <h1 class="text-xl font-semibold text-slate-800">Contour de « {{ $productionUnit->name }} »</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Dessinez le polygone réel de la parcelle. Les points sont enregistrés en longitude / latitude,
                    dans l’ordre attendu par GeoJSON.
                </p>
            </div>
            <a href="{{ route('farm-map.index') }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                Retour au plan
            </a>
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.7fr)_minmax(18rem,1fr)]">
            <x-card class="overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">Éditeur de contour</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Cliquez sur la carte pour ajouter un sommet, glissez un sommet pour le déplacer.
                        </p>
                    </div>
                    <div class="inline-flex flex-wrap gap-1.5">
                        <button type="button" data-boundary-fit
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                            Recentrer
                        </button>
                        <button type="button" data-boundary-undo
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                            Annuler le dernier point
                        </button>
                        <button type="button" data-boundary-clear
                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                            Tout effacer
                        </button>
                    </div>
                </div>

                @if ($center === null)
                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        Aucune coordonnée n’est encore enregistrée pour cette parcelle ni pour l’exploitation.
                        Positionnez la carte sur le terrain avant de dessiner : aucune position ne sera inventée.
                    </p>
                @endif

                <div class="relative mt-3 rounded-2xl border border-emerald-200/70 bg-emerald-50 p-1.5 shadow-inner">
                    <div data-boundary-map
                         class="farm-map-canvas farm-map-canvas--editor"
                         data-center-lat="{{ $center['lat'] ?? '' }}"
                         data-center-lng="{{ $center['lng'] ?? '' }}"
                         data-zoom="{{ $zoom }}"
                         role="application"
                         aria-label="Éditeur de contour de la parcelle"></div>
                    <p data-boundary-tile-status hidden class="farm-map-tile-status" role="status">
                        Fond de carte indisponible (réseau faible). Le dessin reste possible, mais sans repère visuel du terrain.
                    </p>
                    <div data-boundary-load-error hidden class="farm-map-empty">
                        <p class="text-base font-semibold text-slate-800">Éditeur indisponible</p>
                        <p class="mt-1 text-sm text-slate-600">
                            La bibliothèque cartographique n’a pas pu être chargée. Le contour déjà enregistré
                            reste conservé ; réessayez avec une connexion réseau.
                        </p>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span data-boundary-status>0 sommet · contour incomplet</span>
                    <span data-boundary-measure>Surface dessinée : —</span>
                </div>
                <p class="mt-1 text-[11px] text-slate-400">
                    Fond © OpenStreetMap · les tuiles nécessitent une connexion Internet.
                </p>
            </x-card>

            <div class="space-y-4">
                <form method="POST" action="{{ route('production-units.boundary.update', $productionUnit) }}"
                      class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                      data-boundary-form>
                    @csrf
                    @method('PUT')
                    @php($boundaryValue = old('boundary', $boundary ? json_encode($boundary) : ''))
                    @php($boundaryValue = is_array($boundaryValue) ? json_encode($boundaryValue) : $boundaryValue)
                    <input type="hidden" name="boundary" data-boundary-input value="{{ $boundaryValue }}">

                    <h2 class="text-lg font-semibold text-slate-800">Enregistrement</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Le contour est validé côté serveur : au moins 3 sommets distincts et une surface non nulle.
                    </p>

                    @error('boundary')
                        <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">{{ $message }}</p>
                    @enderror

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Surface déclarée</dt>
                            <dd class="font-semibold text-slate-800">{{ number_format((float) $productionUnit->area_m2, 0, ',', ' ') }} m²</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Surface du contour actuel</dt>
                            <dd class="font-semibold text-slate-800">
                                {{ $areaM2 === null ? '—' : number_format($areaM2, 0, ',', ' ').' m²' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Périmètre</dt>
                            <dd class="font-semibold text-slate-800">
                                {{ $perimeterM === null ? '—' : number_format($perimeterM, 0, ',', ' ').' m' }}
                            </dd>
                        </div>
                    </dl>

                    <label class="mt-4 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        <input type="checkbox" name="update_area" value="1" class="mt-0.5 rounded border-slate-300">
                        <span>
                            Mettre à jour la surface déclarée avec la surface du contour
                            @if (! $boundary)
                                dessiné
                            @endif
                        </span>
                    </label>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="submit"
                                class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">
                            Enregistrer le contour
                        </button>
                        <a href="{{ route('production-units.edit', $productionUnit) }}"
                           class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                            Modifier la fiche
                        </a>
                    </div>

                    @if ($boundary)
                        <label class="mt-4 flex items-start gap-2 border-t border-slate-100 pt-3 text-sm text-red-700">
                            <input type="checkbox" name="remove_boundary" value="1" class="mt-0.5 rounded border-red-300">
                            <span>Supprimer le contour enregistré (les autres données de la parcelle sont conservées)</span>
                        </label>
                    @endif
                </form>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-600 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-800">Mode d’emploi</h2>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-xs">
                        <li>Zoomez et déplacez la carte jusqu’à la parcelle.</li>
                        <li>Cliquez sur chaque angle du contour, dans l’ordre.</li>
                        <li>Glissez un sommet pour corriger sa position.</li>
                        <li>Faites un clic droit sur un sommet pour le supprimer.</li>
                        <li>Enregistrez : la surface et les vues 2D/3D sont recalculées.</li>
                    </ol>
                    <p class="mt-3 text-xs text-slate-500">
                        Le fond de carte nécessite une connexion Internet. Les contours déjà enregistrés restent
                        visibles hors connexion, mais les tuiles cartographiques peuvent manquer.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
