@extends('layouts.app')

@section('title', $productionUnit->name)

@php
    $mediaUrl = fn (?string $path) => app(\App\Services\PhotoStorage::class)->url($path);
    $zonesArea = (float) $zones->sum('area_m2');
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('production-units.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                &larr; Toutes les parcelles
            </a>
            <h1 class="mt-2 text-2xl font-semibold text-slate-800">{{ $productionUnit->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                <span class="font-mono">{{ $productionUnit->code }}</span>
                · {{ $productionUnit->location }}
                · {{ $productionUnit->status->label() }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('farm-map.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Voir sur le plan
            </a>
            @can('update', $productionUnit)
                <a href="{{ route('production-units.boundary.edit', $productionUnit) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ $productionUnit->hasBoundary() ? 'Modifier le contour' : 'Dessiner le contour' }}
                </a>
                <a href="{{ route('production-units.edit', $productionUnit) }}" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Modifier
                </a>
            @endcan
        </div>
    </div>

    <div class="dashboard-grid mb-5" data-reveal-group="50">
        <x-stat-card label="Superficie" :value="number_format((float) $productionUnit->area_m2, 0, ',', ' ').' m²'"
                     :count="$productionUnit->area_m2" :decimals="0" suffix=" m²" />
        <x-stat-card label="Campagnes" :value="$stats['campaigns']" :count="$stats['campaigns']"
                     :hint="$stats['active_campaigns'].' active(s)'" tone="info" />
        <x-stat-card label="Récoltes cumulées" :value="number_format($stats['harvest_quantity'], 1, ',', ' ').' kg'"
                     :count="$stats['harvest_quantity']" :decimals="1" suffix=" kg" />
        <x-stat-card label="Eau enregistrée" :value="number_format($stats['irrigation_volume'], 1, ',', ' ').' m³'"
                     :count="$stats['irrigation_volume']" :decimals="1" suffix=" m³" tone="gold" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Caractéristiques">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Statut</dt>
                    <dd class="font-medium text-slate-800">{{ $productionUnit->status->label() }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Dimensions</dt>
                    <dd class="font-medium text-slate-800">
                        {{ ($productionUnit->length_m !== null && $productionUnit->width_m !== null) ? number_format((float) $productionUnit->length_m, 2, ',', ' ').' × '.number_format((float) $productionUnit->width_m, 2, ',', ' ').' m' : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Type de sol</dt>
                    <dd class="font-medium text-slate-800">{{ $productionUnit->soil_type ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Irrigation</dt>
                    <dd class="font-medium text-slate-800">{{ $productionUnit->irrigation_type ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Exposition</dt>
                    <dd class="font-medium text-slate-800">{{ $productionUnit->sun_exposure ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Pente</dt>
                    <dd class="font-medium text-slate-800">{{ $productionUnit->slope ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Contour</dt>
                    <dd class="font-medium text-slate-800">
                        @if ($productionUnit->hasBoundary())
                            {{ number_format((float) $productionUnit->boundaryAreaM2(), 0, ',', ' ') }} m²
                            · {{ number_format((float) $productionUnit->boundaryPerimeterM(), 0, ',', ' ') }} m
                        @else
                            À dessiner
                        @endif
                    </dd>
                </div>
                @if ($productionUnit->latitude !== null && $productionUnit->longitude !== null)
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Coordonnées</dt>
                        <dd class="font-mono text-xs text-slate-800">
                            {{ number_format((float) $productionUnit->latitude, 5, '.', '') }},
                            {{ number_format((float) $productionUnit->longitude, 5, '.', '') }}
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($productionUnit->notes)
                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $productionUnit->notes }}</p>
            @endif
        </x-card>

        <x-card class="lg:col-span-2" title="Campagnes" subtitle="Historique des cultures sur cette parcelle">
            @if ($campaigns->isEmpty())
                <p class="text-sm text-slate-500">Aucune campagne rattachée à cette parcelle.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Campagne</th>
                                <th class="py-2 pr-4">Culture</th>
                                <th class="py-2 pr-4">Période</th>
                                <th class="py-2 pr-4">Surface</th>
                                <th class="py-2">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($campaigns as $campaign)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <a href="{{ route('campaigns.show', $campaign) }}" class="font-medium text-emerald-700 hover:underline">
                                            {{ $campaign->name }}
                                        </a>
                                    </td>
                                    <td class="py-2 pr-4">{{ $campaign->crop?->name ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-xs">
                                        {{ $campaign->start_date?->format('d/m/Y') ?? '—' }}
                                        @if ($campaign->expected_end_date)
                                            → {{ $campaign->expected_end_date->format('d/m/Y') }}
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4">{{ number_format((float) $campaign->cultivated_area_m2, 0, ',', ' ') }} m²</td>
                                    <td class="py-2">
                                        <span class="rounded px-2 py-0.5 text-xs
                                            {{ $campaign->status->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $campaign->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card title="Zones de culture" subtitle="Sous-parcelles utilisées pour rattacher précisément les campagnes">
            @if ($zones->isEmpty())
                <p class="text-sm text-slate-500">Aucune zone définie. La parcelle peut être cultivée dans son ensemble.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($zones as $zone)
                        <li class="py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-800">
                                        <span class="font-mono text-xs text-slate-500">{{ $zone->code }}</span>
                                        {{ $zone->name }}
                                        @unless ($zone->is_active)
                                            <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">Inactive</span>
                                        @endunless
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ number_format((float) $zone->area_m2, 0, ',', ' ') }} m²
                                        · {{ $zone->campaigns_count }} campagne(s)
                                        @if ($zone->irrigation_type) · {{ $zone->irrigation_type }} @endif
                                    </p>
                                </div>
                                @can('delete', $zone)
                                    <form method="POST" action="{{ route('zones.destroy', $zone) }}"
                                          onsubmit="return confirm('Supprimer cette zone ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Supprimer</button>
                                    </form>
                                @endcan
                            </div>
                            @can('update', $zone)
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs font-semibold text-emerald-700">Modifier</summary>
                                    <form method="POST" action="{{ route('zones.update', $zone) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                                        @csrf
                                        @method('PUT')
                                        <input name="code" value="{{ $zone->code }}" required maxlength="30" aria-label="Code de la zone"
                                               class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                                        <input name="name" value="{{ $zone->name }}" required maxlength="120" aria-label="Nom de la zone"
                                               class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                                        <input name="area_m2" type="number" step="0.01" min="0.01" value="{{ $zone->area_m2 }}" required aria-label="Surface de la zone"
                                               class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                                        <input name="irrigation_type" value="{{ $zone->irrigation_type }}" maxlength="60" placeholder="Irrigation" aria-label="Irrigation de la zone"
                                               class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                                        <input name="soil_type" value="{{ $zone->soil_type }}" maxlength="60" placeholder="Type de sol" aria-label="Type de sol de la zone"
                                               class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                                        <label class="flex items-center gap-2 text-xs text-slate-600">
                                            <input type="checkbox" name="is_active" value="1" @checked($zone->is_active)>
                                            Zone active
                                        </label>
                                        <textarea name="notes" rows="2" placeholder="Notes" aria-label="Notes de la zone"
                                                  class="rounded border border-slate-300 px-2 py-1.5 text-sm sm:col-span-2">{{ $zone->notes }}</textarea>
                                        <div class="sm:col-span-2">
                                            <button type="submit" class="rounded bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                                                Enregistrer la zone
                                            </button>
                                        </div>
                                    </form>
                                </details>
                            @endcan
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-500">
                    Total zones : <strong>{{ number_format($zonesArea, 0, ',', ' ') }} m²</strong>
                    / {{ number_format((float) $productionUnit->area_m2, 0, ',', ' ') }} m²
                </p>
            @endif

            @can('update', $productionUnit)
                <details class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-emerald-700">Ajouter une zone</summary>
                    <form method="POST" action="{{ route('production-units.zones.store', $productionUnit) }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="zone_code" class="block text-xs font-medium text-slate-600">Code</label>
                            <input id="zone_code" name="code" required maxlength="30" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="zone_name" class="block text-xs font-medium text-slate-600">Nom</label>
                            <input id="zone_name" name="name" required maxlength="120" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="zone_area" class="block text-xs font-medium text-slate-600">Surface (m²)</label>
                            <input id="zone_area" name="area_m2" type="number" step="0.01" min="0.01" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label for="zone_irrigation" class="block text-xs font-medium text-slate-600">Irrigation</label>
                            <input id="zone_irrigation" name="irrigation_type" maxlength="60" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="zone_notes" class="block text-xs font-medium text-slate-600">Notes</label>
                            <textarea id="zone_notes" name="notes" rows="2" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"></textarea>
                        </div>
                        <input type="hidden" name="is_active" value="1">
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800">Créer la zone</button>
                        </div>
                    </form>
                </details>
            @endcan
        </x-card>

        <x-card title="Problèmes agricoles" subtitle="Constats signalés sur cette parcelle">
            @if ($issues->isEmpty())
                <p class="text-sm text-slate-500">Aucun problème signalé.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($issues as $issue)
                        <li class="flex items-start justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <a href="{{ route('issues.show', $issue) }}" class="font-medium text-emerald-700 hover:underline">
                                    {{ $issue->reference }} — {{ $issue->title }}
                                </a>
                                <p class="text-xs text-slate-500">
                                    {{ $issue->type->label() }} · {{ $issue->observed_at?->format('d/m/Y') }}
                                    @if ($issue->campaign) · {{ $issue->campaign->name }} @endif
                                </p>
                            </div>
                            <span class="shrink-0 rounded px-2 py-0.5 text-[11px] font-semibold
                                {{ $issue->severity->value === 'critical' ? 'bg-red-100 text-red-800' : ($issue->severity->value === 'high' ? 'bg-orange-100 text-orange-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ $issue->severity->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('issues.create', ['production_unit_id' => $productionUnit->id]) }}"
               class="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                Signaler un problème &rarr;
            </a>
        </x-card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card title="Dernières activités" subtitle="Travaux planifiés ou réalisés sur la parcelle">
            @if ($activities->isEmpty())
                <p class="text-sm text-slate-500">Aucune activité enregistrée.</p>
            @else
                <ul class="upcoming-list">
                    @foreach ($activities as $activity)
                        <li class="upcoming-item">
                            <div class="min-w-0">
                                <a href="{{ route('activities.show', $activity) }}" class="upcoming-item__title hover:text-emerald-800">
                                    {{ $activity->title }}
                                </a>
                                <p class="upcoming-item__meta">
                                    {{ $activity->campaign?->name ?? '—' }} · {{ $activity->planned_date?->format('d/m/Y') ?? '—' }}
                                </p>
                            </div>
                            <span class="upcoming-item__badge {{ $activity->isLate() ? 'upcoming-item__badge--soon' : '' }}">
                                {{ $activity->status->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Dernières récoltes" subtitle="Quantités enregistrées sur la parcelle">
            @if ($harvests->isEmpty())
                <p class="text-sm text-slate-500">Aucune récolte enregistrée.</p>
            @else
                <ul class="upcoming-list">
                    @foreach ($harvests as $harvest)
                        <li class="upcoming-item">
                            <div class="min-w-0">
                                <p class="upcoming-item__title">{{ $harvest->harvest_code }}</p>
                                <p class="upcoming-item__meta">
                                    {{ $harvest->campaign?->name ?? '—' }} · {{ $harvest->harvested_at?->format('d/m/Y') ?? '—' }}
                                </p>
                            </div>
                            <span class="upcoming-item__badge">
                                {{ number_format((float) $harvest->quantity, 1, ',', ' ') }} {{ $harvest->unit }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card title="Irrigation" subtitle="Derniers arrosages rattachés à la parcelle">
            @if ($irrigations->isEmpty())
                <p class="text-sm text-slate-500">Aucun arrosage enregistré.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Date</th>
                                <th class="py-2 pr-4">Campagne</th>
                                <th class="py-2 pr-4">Durée</th>
                                <th class="py-2">Volume</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($irrigations as $irrigation)
                                <tr>
                                    <td class="py-2 pr-4">{{ $irrigation->irrigated_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $irrigation->campaign?->name ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $irrigation->duration_hours !== null ? number_format((float) $irrigation->duration_hours, 1, ',', ' ').' h' : '—' }}</td>
                                    <td class="py-2">{{ number_format((float) $irrigation->volume_m3, 1, ',', ' ') }} m³</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Photos" subtitle="Suivi visuel de la parcelle">
            @if ($productionUnit->photos->isEmpty() && ! $productionUnit->photo_path)
                <p class="text-sm text-slate-500">Aucune photo enregistrée.</p>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @if ($productionUnit->photo_path)
                        <figure class="overflow-hidden rounded-lg border border-slate-200">
                            <a href="{{ $mediaUrl($productionUnit->photo_path) }}" target="_blank" rel="noopener">
                                <img src="{{ $mediaUrl($productionUnit->photo_path) }}"
                                     alt="Photo principale de {{ $productionUnit->name }}"
                                     class="h-28 w-full object-cover">
                            </a>
                            <figcaption class="px-2 py-1 text-xs text-slate-500">Photo principale</figcaption>
                        </figure>
                    @endif
                    @foreach ($productionUnit->photos as $photo)
                        <figure class="overflow-hidden rounded-lg border border-slate-200">
                            <a href="{{ $mediaUrl($photo->photo_path) }}" target="_blank" rel="noopener">
                                <img src="{{ $mediaUrl($photo->photo_path) }}"
                                     alt="{{ $photo->caption ?: 'Photo de la parcelle' }}"
                                     class="h-28 w-full object-cover" loading="lazy">
                            </a>
                            <figcaption class="flex items-start justify-between gap-1 px-2 py-1 text-xs text-slate-500">
                                <span>
                                    {{ $photo->caption ?: ($photo->taken_at?->format('d/m/Y') ?? 'Photo') }}
                                    @if ($photo->growth_stage)
                                        <span class="block text-[11px] text-slate-400">{{ $photo->growth_stage->label() }}</span>
                                    @endif
                                </span>
                                @can('delete', $photo)
                                    <form method="POST" action="{{ route('production-units.photos.destroy', $photo) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-semibold text-red-600 hover:underline" title="Supprimer">&times;</button>
                                    </form>
                                @endcan
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif

            @can('create', \App\Models\ProductionUnitPhoto::class)
                <form method="POST" action="{{ route('production-units.photos.store', $productionUnit) }}" enctype="multipart/form-data"
                      class="mt-4 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="unit_photo" class="block text-xs font-medium text-slate-600">Ajouter une photo</label>
                        <input id="unit_photo" name="photo" type="file" accept="image/*" required class="mt-1 w-full text-sm">
                    </div>
                    <div>
                        <label for="unit_stage" class="block text-xs font-medium text-slate-600">Étape</label>
                        <select id="unit_stage" name="growth_stage" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">Non précisée</option>
                            @foreach (\App\Enums\CropStage::ordered() as $stage)
                                <option value="{{ $stage->value }}">{{ $stage->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="unit_taken" class="block text-xs font-medium text-slate-600">Date</label>
                        <input id="unit_taken" name="taken_at" type="date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="unit_caption" class="block text-xs font-medium text-slate-600">Légende</label>
                        <input id="unit_caption" name="caption" maxlength="255" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800">
                            Ajouter la photo
                        </button>
                    </div>
                </form>
            @endcan
        </x-card>
    </div>
@endsection
