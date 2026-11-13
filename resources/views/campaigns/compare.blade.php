@extends('layouts.app')

@section('title', 'Comparaison avant / après — '.$campaign->name)

@php
    $photoUrl = fn (?string $path) => app(\App\Services\PhotoStorage::class)->url($path);
    $days = ($before && $after && $before->taken_at && $after->taken_at)
        ? (int) $before->taken_at->startOfDay()->diffInDays($after->taken_at->startOfDay())
        : null;
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('campaigns.photos.index', $campaign) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">&larr; Chronologie photos</a>
            <h1 class="mt-2 text-xl font-semibold text-slate-800">Avant / après — {{ $campaign->name }}</h1>
            <p class="text-sm text-slate-500">{{ $campaign->crop?->name }} · {{ $campaign->productionUnit?->code }} — {{ $campaign->productionUnit?->name }}</p>
        </div>
        <a href="{{ route('campaigns.show', $campaign) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Fiche de la campagne
        </a>
    </div>

    @if ($photos->isEmpty())
        <x-card>
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                <p class="font-semibold text-slate-700">Aucune photo à comparer</p>
                <p class="mt-1 text-sm text-slate-500">Ajoutez au moins deux photos datées dans la chronologie de la culture.</p>
                <a href="{{ route('campaigns.photos.index', $campaign) }}" class="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                    Ajouter des photos &rarr;
                </a>
            </div>
        </x-card>
    @else
        <x-card class="mb-4" title="Choisir les deux photos">
            <form method="GET" action="{{ route('campaigns.photos.compare', $campaign) }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <div>
                    <label for="before" class="block text-sm font-medium text-slate-700">Photo « avant »</label>
                    <select id="before" name="before" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($photos as $photo)
                            <option value="{{ $photo->id }}" @selected($before?->id === $photo->id)>
                                {{ $photo->taken_at?->format('d/m/Y') }} — {{ $photo->growth_stage?->label() ?? 'Observation' }} {{ $photo->caption ? '· '.$photo->caption : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="after" class="block text-sm font-medium text-slate-700">Photo « après »</label>
                    <select id="after" name="after" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($photos as $photo)
                            <option value="{{ $photo->id }}" @selected($after?->id === $photo->id)>
                                {{ $photo->taken_at?->format('d/m/Y') }} — {{ $photo->growth_stage?->label() ?? 'Observation' }} {{ $photo->caption ? '· '.$photo->caption : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Comparer
                    </button>
                </div>
            </form>
            @if ($days !== null)
                <p class="mt-3 text-sm text-slate-500">Intervalle entre les deux prises de vue : <strong class="text-slate-800">{{ $days }} jour(s)</strong>.</p>
            @endif
        </x-card>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([['label' => 'Avant', 'photo' => $before, 'tone' => 'red'] , ['label' => 'Après', 'photo' => $after, 'tone' => 'emerald']] as $panel)
                <x-card>
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold {{ $panel['tone'] === 'red' ? 'text-red-700' : 'text-emerald-700' }}">{{ $panel['label'] }}</h2>
                        @if ($panel['photo'])
                            <a href="{{ $photoUrl($panel['photo']->photo_path) }}" target="_blank" rel="noopener"
                               class="text-xs font-semibold text-slate-600 hover:underline">Ouvrir en plein écran</a>
                        @endif
                    </div>
                    @if ($panel['photo'])
                        <img src="{{ $photoUrl($panel['photo']->photo_path) }}"
                             alt="{{ $panel['photo']->caption ?: $panel['label'] }}"
                             class="max-h-[30rem] w-full rounded-xl border border-slate-200 object-cover">
                        <dl class="mt-3 space-y-1 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Date</dt>
                                <dd class="font-medium">{{ $panel['photo']->taken_at?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Étape</dt>
                                <dd class="font-medium">{{ $panel['photo']->growth_stage?->label() ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Type</dt>
                                <dd class="font-medium">{{ $panel['photo']->observation_type ?? '—' }}</dd>
                            </div>
                            @if ($panel['photo']->caption)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Description</dt>
                                    <dd class="text-right font-medium">{{ $panel['photo']->caption }}</dd>
                                </div>
                            @endif
                            @if ($panel['photo']->uploadedBy)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Auteur</dt>
                                    <dd class="font-medium">{{ $panel['photo']->uploadedBy->name }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="text-sm text-slate-500">Photo non sélectionnée.</p>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
