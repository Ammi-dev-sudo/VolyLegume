@extends('layouts.app')

@section('title', 'Photos — '.$campaign->name)

@php
    $photoUrl = fn (?string $path) => app(\App\Services\PhotoStorage::class)->url($path);
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">&larr; Campagne</a>
            <h1 class="mt-2 text-xl font-semibold text-slate-800">Suivi photographique — {{ $campaign->name }}</h1>
            <p class="text-sm text-slate-500">
                {{ $campaign->crop?->name }} · {{ $campaign->productionUnit?->code }} — {{ $campaign->productionUnit?->name }}
                · {{ $photos->count() }} photo(s)
            </p>
        </div>
        <a href="{{ route('campaigns.photos.compare', $campaign) }}"
           class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Comparer avant / après
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2" title="Chronologie" subtitle="Toutes les photos enregistrées pour cette culture.">
            <form method="GET" action="{{ route('campaigns.photos.index', $campaign) }}" class="mb-4 flex flex-wrap items-end gap-2 text-sm">
                <div>
                    <label for="pf-stage" class="block text-xs font-medium text-slate-600">Étape</label>
                    <select id="pf-stage" name="stage" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                        <option value="">Toutes</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage->value }}" @selected($selectedStage === $stage->value)>{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="pf-type" class="block text-xs font-medium text-slate-600">Type</label>
                    <select id="pf-type" name="type" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                        <option value="">Tous</option>
                        @foreach ($observationTypes as $value => $label)
                            <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
                <a href="{{ route('campaigns.photos.index', $campaign) }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
            </form>

            @if ($photos->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                    <p class="font-semibold text-slate-700">Aucune photographie</p>
                    <p class="mt-1 text-sm text-slate-500">Ajoutez la première photo de cette culture pour construire l’historique visuel.</p>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($photos as $photo)
                        <figure class="group overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <a href="{{ $photoUrl($photo->photo_path) }}" target="_blank" rel="noopener" class="block">
                                <img src="{{ $photoUrl($photo->photo_path) }}" alt="{{ $photo->caption ?: 'Photo de culture' }}"
                                     class="h-36 w-full object-cover transition duration-200 group-hover:scale-[1.02]" loading="lazy">
                            </a>
                            <figcaption class="px-2 py-2 text-xs text-slate-500">
                                <p class="font-semibold text-slate-700">{{ $photo->growth_stage?->label() ?? 'Observation' }}</p>
                                <p>{{ $photo->taken_at?->format('d/m/Y') }} · {{ $photo->observation_type ?? 'observation' }}</p>
                                @if ($photo->caption)<p class="mt-0.5 text-slate-600">{{ $photo->caption }}</p>@endif
                                @if ($photo->uploadedBy)<p class="text-[11px] text-slate-400">{{ $photo->uploadedBy->name }}</p>@endif
                            </figcaption>
                            @can('delete', $photo)
                                <form method="POST" action="{{ route('campaigns.photos.destroy', [$campaign, $photo]) }}" class="px-2 pb-2"
                                      onsubmit="return confirm('Supprimer cette photo ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Supprimer</button>
                                </form>
                            @endcan
                        </figure>
                    @endforeach
                </div>
            @endif
        </x-card>

        <div class="space-y-4">
            <x-card title="Ajouter une photo" subtitle="La photo est liée à la culture et à son étape.">
                <form method="POST" action="{{ route('campaigns.photos.store', $campaign) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div>
                        <label for="photo" class="block text-sm font-medium text-slate-700">Fichier image</label>
                        <input id="photo" name="photo" type="file" accept="image/*" required
                               class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="growth_stage" class="block text-sm font-medium text-slate-700">Étape de croissance</label>
                        <select id="growth_stage" name="growth_stage" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Non précisée</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage->value }}" @selected(old('growth_stage') === $stage->value)>{{ $stage->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="observation_type" class="block text-sm font-medium text-slate-700">Type d’observation</label>
                        <select id="observation_type" name="observation_type" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($observationTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('observation_type', 'observation') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="taken_at" class="block text-sm font-medium text-slate-700">Date de prise</label>
                        <input id="taken_at" name="taken_at" type="date" value="{{ old('taken_at', now()->toDateString()) }}"
                               class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="caption" class="block text-sm font-medium text-slate-700">Description</label>
                        <input id="caption" name="caption" maxlength="255" value="{{ old('caption') }}"
                               class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="w-full rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Ajouter à la chronologie
                    </button>
                </form>
            </x-card>

            <x-card title="Repères">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Semis / plantation</dt>
                        <dd class="font-medium">{{ $campaign->start_date?->format('d/m/Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Récolte prévue</dt>
                        <dd class="font-medium">{{ $campaign->expected_end_date?->format('d/m/Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Surface</dt>
                        <dd class="font-medium">{{ number_format((float) $campaign->cultivated_area_m2, 0, ',', ' ') }} m²</dd>
                    </div>
                    @if ($campaign->zone)
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Zone</dt>
                            <dd class="font-medium">{{ $campaign->zone->code }} — {{ $campaign->zone->name }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </div>
    </div>
@endsection
