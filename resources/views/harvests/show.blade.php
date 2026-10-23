@extends('layouts.app')

@section('title', 'Récolte')

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">
                <a href="{{ route('harvests.index') }}" class="text-emerald-700 hover:underline">← Récoltes</a>
            </p>
            <h1 class="text-xl font-semibold text-slate-800">
                {{ number_format((float) $harvest->quantity, 2, ',', ' ') }} {{ $harvest->unit }}
                — {{ $harvest->harvested_at->format('d/m/Y H:i') }}
            </h1>
            <p class="text-sm text-slate-500">
                Campagne
                <a href="{{ route('campaigns.show', $harvest->campaign) }}" class="text-emerald-700 hover:underline">
                    {{ $harvest->campaign?->name }}
                </a>
                · {{ $harvest->campaign?->crop?->name }}
            </p>
        </div>
        <div class="flex gap-2 text-sm">
            @if ($harvest->revenues->isEmpty())
                <form method="POST" action="{{ route('harvests.destroy', $harvest) }}"
                      onsubmit="return confirm('Supprimer cette récolte ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded border border-slate-300 px-3 py-1.5 text-red-600 hover:bg-red-50">
                        Supprimer
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($harvest->produceLot)
        <x-card class="mb-6" title="Lot de stock récolté">
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <div>
                    <p class="font-mono text-xs text-slate-500">{{ $harvest->produceLot->code }}</p>
                    <p class="font-medium text-slate-800">
                        {{ number_format((float) $harvest->produceLot->available_quantity, 2, ',', ' ') }} {{ $harvest->produceLot->unit }}
                        disponibles sur {{ number_format((float) $harvest->produceLot->initial_quantity, 2, ',', ' ') }} {{ $harvest->produceLot->unit }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $harvest->produceLot->status->label() }}</p>
                </div>
                <a href="{{ route('produce-lots.show', $harvest->produceLot) }}"
                   class="rounded border border-emerald-700 px-3 py-1.5 text-sm text-emerald-700 hover:bg-emerald-50">
                    Voir la traçabilité et vendre
                </a>
            </div>
        </x-card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Récolte">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Quantité</dt>
                    <dd class="font-medium">{{ number_format((float) $harvest->quantity, 3, ',', ' ') }} {{ $harvest->unit }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Date et heure</dt>
                    <dd>{{ $harvest->harvested_at->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Qualité</dt>
                    <dd>{{ $harvest->quality ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Calibre / grade</dt>
                    <dd>{{ $harvest->grade ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Surface récoltée</dt>
                    <dd>{{ $harvest->harvested_area_m2 !== null ? number_format((float) $harvest->harvested_area_m2, 2, ',', ' ').' m²' : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Pertes déclarées</dt>
                    <dd>{{ number_format((float) $harvest->loss_quantity, 2, ',', ' ') }} {{ $harvest->unit }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Enregistrée par</dt>
                    <dd>{{ $harvest->recordedBy?->name ?? '—' }}</dd>
                </div>
            </dl>
            @if (filled($harvest->notes))
                <p class="mt-4 whitespace-pre-line border-t border-slate-100 pt-3 text-sm text-slate-700">{{ $harvest->notes }}</p>
            @endif
        </x-card>

        <x-card title="Recettes liées">
            @if ($harvest->revenues->isEmpty())
                <p class="text-sm text-slate-600">Aucune recette enregistrée pour cette récolte.</p>
                @can('manage-finances')
                    <a href="{{ route('revenues.create', ['harvest_id' => $harvest->id]) }}"
                       class="mt-3 inline-block rounded border border-emerald-700 px-3 py-1.5 text-sm text-emerald-700 hover:bg-emerald-50">
                        Enregistrer une vente
                    </a>
                @endcan
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($harvest->revenues as $revenue)
                        <li class="flex justify-between py-2">
                            <a href="{{ route('revenues.edit', $revenue) }}" class="text-emerald-700 hover:underline">
                                {{ $revenue->description }}
                            </a>
                            <span class="font-medium">{{ number_format((float) $revenue->amount, 0, ',', ' ') }} Ar</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
