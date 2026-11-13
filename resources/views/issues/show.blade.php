@extends('layouts.app')

@section('title', $issue->title)

@php
    $photoUrl = fn (?string $path) => app(\App\Services\PhotoStorage::class)->url($path);
    $severityStyles = [
        'low' => 'bg-slate-100 text-slate-700',
        'moderate' => 'bg-amber-100 text-amber-800',
        'high' => 'bg-orange-100 text-orange-800',
        'critical' => 'bg-red-100 text-red-800',
    ];
    $statusStyles = [
        'open' => 'bg-red-50 text-red-700',
        'in_progress' => 'bg-sky-50 text-sky-700',
        'monitoring' => 'bg-amber-50 text-amber-700',
        'resolved' => 'bg-emerald-50 text-emerald-700',
    ];
    $daysOpen = (int) $issue->observed_at->startOfDay()->diffInDays(today()->startOfDay());
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('issues.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">&larr; Problèmes agricoles</a>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold text-slate-800">{{ $issue->title }}</h1>
                <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $severityStyles[$issue->severity->value] ?? 'bg-slate-100 text-slate-700' }}">
                    {{ $issue->severity->label() }}
                </span>
                <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $statusStyles[$issue->status->value] ?? 'bg-slate-100 text-slate-700' }}">
                    {{ $issue->status->label() }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                <span class="font-mono">{{ $issue->reference }}</span>
                · {{ $issue->type->label() }}
                · observé le {{ $issue->observed_at->format('d/m/Y') }}
                · {{ $daysOpen }} jour(s) de suivi
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $issue)
                <a href="{{ route('issues.edit', $issue) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
            @endcan
            @if ($issue->campaign)
                <a href="{{ route('treatments.create', ['campaign_id' => $issue->campaign_id, 'crop_issue_id' => $issue->id]) }}"
                   class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Enregistrer un traitement
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card title="Constat et localisation">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase text-slate-500">Parcelle</p>
                        <p class="mt-1 font-medium text-slate-800">
                            <a href="{{ route('production-units.show', $issue->production_unit_id) }}" class="text-emerald-700 hover:underline">
                                {{ $issue->productionUnit?->code }} — {{ $issue->productionUnit?->name }}
                            </a>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Culture</p>
                        <p class="mt-1 font-medium text-slate-800">
                            @if ($issue->campaign)
                                <a href="{{ route('campaigns.show', $issue->campaign) }}" class="text-emerald-700 hover:underline">
                                    {{ $issue->campaign->name }} · {{ $issue->campaign->crop?->name }}
                                </a>
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Surface affectée</p>
                        <p class="mt-1 font-medium text-slate-800">
                            {{ $issue->affected_area_m2 !== null ? number_format((float) $issue->affected_area_m2, 0, ',', ' ').' m²' : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Signalé par</p>
                        <p class="mt-1 font-medium text-slate-800">{{ $issue->reportedBy?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Responsable du suivi</p>
                        <p class="mt-1 font-medium text-slate-800">{{ $issue->assignedTo?->name ?? 'Non assigné' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-slate-500">Résolution</p>
                        <p class="mt-1 font-medium text-slate-800">
                            {{ $issue->resolved_at?->format('d/m/Y H:i') ?? 'En cours' }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">
                    {{ $issue->description }}
                </div>

                @if ($issue->recommended_actions)
                    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Actions recommandées</p>
                        <p class="mt-1 text-sm leading-relaxed text-sky-900">{{ $issue->recommended_actions }}</p>
                    </div>
                @endif

                @if ($issue->resolution_notes)
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Notes de résolution</p>
                        <p class="mt-1 text-sm leading-relaxed text-emerald-900">{{ $issue->resolution_notes }}</p>
                    </div>
                @endif
            </x-card>

            @if ($beforePhoto && $afterPhoto)
                <x-card title="Comparaison avant / après" subtitle="Deux photographies du même constat, à des dates différentes.">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <figure class="overflow-hidden rounded-xl border border-slate-200">
                            <a href="{{ $photoUrl($beforePhoto->photo_path) }}" target="_blank" rel="noopener">
                                <img src="{{ $photoUrl($beforePhoto->photo_path) }}" alt="{{ $beforePhoto->caption ?: 'Avant intervention' }}"
                                     class="h-56 w-full object-cover">
                            </a>
                            <figcaption class="bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <span class="font-semibold text-red-700">Avant</span>
                                · {{ $beforePhoto->taken_at?->format('d/m/Y') }}
                                @if ($beforePhoto->caption) · {{ $beforePhoto->caption }} @endif
                            </figcaption>
                        </figure>
                        <figure class="overflow-hidden rounded-xl border border-slate-200">
                            <a href="{{ $photoUrl($afterPhoto->photo_path) }}" target="_blank" rel="noopener">
                                <img src="{{ $photoUrl($afterPhoto->photo_path) }}" alt="{{ $afterPhoto->caption ?: 'Après intervention' }}"
                                     class="h-56 w-full object-cover">
                            </a>
                            <figcaption class="bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <span class="font-semibold text-emerald-700">Après</span>
                                · {{ $afterPhoto->taken_at?->format('d/m/Y') }}
                                @if ($afterPhoto->caption) · {{ $afterPhoto->caption }} @endif
                            </figcaption>
                        </figure>
                    </div>
                </x-card>
            @endif

            <x-card title="Galerie chronologique" subtitle="Photos de constat, avant/après intervention et évolution.">
                @if ($issue->photos->isEmpty())
                    <p class="text-sm text-slate-500">Aucune photographie. Ajoutez un constat visuel ci-dessous.</p>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($issue->photos as $photo)
                            <figure class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <a href="{{ $photoUrl($photo->photo_path) }}" target="_blank" rel="noopener">
                                    <img src="{{ $photoUrl($photo->photo_path) }}" alt="{{ $photo->caption ?: 'Photo du problème' }}"
                                         class="h-32 w-full object-cover" loading="lazy">
                                </a>
                                <figcaption class="px-2 py-1.5 text-xs text-slate-500">
                                    <span class="font-semibold text-slate-700">{{ $photo->kind->label() }}</span>
                                    · {{ $photo->taken_at?->format('d/m/Y') }}
                                    @if ($photo->caption)<p class="truncate">{{ $photo->caption }}</p>@endif
                                </figcaption>
                                @can('delete', $photo)
                                    <form method="POST" action="{{ route('issues.photos.destroy', $photo) }}" class="px-2 pb-2"
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

                <form method="POST" action="{{ route('issues.photos.store', $issue) }}" enctype="multipart/form-data"
                      class="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="issue-photo" class="block text-sm font-medium text-slate-700">Ajouter une photographie</label>
                        <input id="issue-photo" name="photo" type="file" accept="image/*" required
                               class="mt-1 w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                        @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="issue-kind" class="block text-sm font-medium text-slate-700">Type</label>
                        <select id="issue-kind" name="kind" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($photoKinds as $kind)
                                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="issue-taken" class="block text-sm font-medium text-slate-700">Date de prise</label>
                        <input id="issue-taken" name="taken_at" type="date" value="{{ old('taken_at', now()->toDateString()) }}"
                               class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="issue-caption" class="block text-sm font-medium text-slate-700">Description</label>
                        <input id="issue-caption" name="caption" value="{{ old('caption') }}" maxlength="255"
                               class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Ajouter la photo
                        </button>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="space-y-4">
            @can('update', $issue)
                <x-card title="Mettre à jour le statut">
                    <form method="POST" action="{{ route('issues.status', $issue) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($issue->status === $status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <textarea name="resolution_notes" rows="3" placeholder="Notes de résolution (facultatif)"
                                  class="w-full rounded border border-slate-300 px-3 py-2 text-sm">{{ old('resolution_notes', $issue->resolution_notes) }}</textarea>
                        <button type="submit" class="w-full rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Enregistrer le statut
                        </button>
                    </form>
                </x-card>
            @endcan

            <x-card title="Interventions liées">
                @if ($issue->treatments->isEmpty())
                    <p class="text-sm text-slate-500">Aucun traitement associé.</p>
                @else
                    <ul class="space-y-3 text-sm">
                        @foreach ($issue->treatments as $treatment)
                            <li class="rounded-lg border border-slate-200 p-3">
                                <a href="{{ route('treatments.show', $treatment) }}" class="font-semibold text-emerald-700 hover:underline">
                                    {{ $treatment->input?->name ?? 'Traitement' }}
                                </a>
                                <p class="text-xs text-slate-500">
                                    {{ $treatment->applied_at->format('d/m/Y') }}
                                    · {{ $treatment->dose_value }} {{ $treatment->dose_unit }}
                                    @if ($treatment->appliedBy) · {{ $treatment->appliedBy->name }} @endif
                                </p>
                                @if ($treatment->result_observed)
                                    <p class="mt-1 text-xs text-slate-600">Résultat : {{ $treatment->result_observed }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @can('delete', $issue)
                <x-card title="Suppression">
                    <p class="text-sm text-slate-500">La suppression retire aussi les photos et le lien vers les traitements.</p>
                    <form method="POST" action="{{ route('issues.destroy', $issue) }}" class="mt-3"
                          onsubmit="return confirm('Supprimer définitivement ce problème ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Supprimer le problème
                        </button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
@endsection
