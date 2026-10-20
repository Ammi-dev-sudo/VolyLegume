@extends('layouts.app')

@section('title', 'Traitement')

@section('content')
    <div class="mb-4">
        <p class="text-sm text-slate-500">
            <a href="{{ route('treatments.index') }}" class="text-emerald-700 hover:underline">← Traitements</a>
        </p>
        <h1 class="text-xl font-semibold text-slate-800">{{ $treatment->input?->name }}</h1>
        <p class="text-sm text-slate-500">
            Campagne
            <a href="{{ route('campaigns.show', $treatment->campaign) }}" class="text-emerald-700 hover:underline">
                {{ $treatment->campaign?->name }}
            </a>
        </p>
    </div>

    <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Récolte autorisée à partir du <strong>{{ $treatment->minimum_harvest_at->format('d/m/Y H:i') }}</strong>
        (application du {{ $treatment->applied_at->format('d/m/Y H:i') }} + {{ $treatment->pre_harvest_days }} jour(s)
        × 24 h).
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Traitement">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Produit</dt>
                    <dd class="text-right">
                        <a href="{{ route('inputs.show', $treatment->input) }}" class="text-emerald-700 hover:underline">
                            {{ $treatment->input?->name }}
                        </a>
                    </dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Quantité consommée</dt>
                    <dd>{{ number_format((float) $treatment->input?->quantity, 3, ',', ' ') }} {{ $treatment->input?->base_unit?->value }} en stock</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Dose</dt>
                    <dd>{{ $treatment->dose_value }} {{ $treatment->dose_unit }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Délai avant récolte</dt>
                    <dd>{{ $treatment->pre_harvest_days }} jours</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Source du délai</dt>
                    <dd>{{ $treatment->delay_source_snapshot ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Appliqué le</dt>
                    <dd>{{ $treatment->applied_at->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Appliqué par</dt>
                    <dd>{{ $treatment->appliedBy?->name ?? '—' }}</dd>
                </div>
            </dl>
            @if (filled($treatment->notes))
                <p class="mt-4 whitespace-pre-line border-t border-slate-100 pt-3 text-sm text-slate-700">{{ $treatment->notes }}</p>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="Traçabilité">
                <ul class="space-y-3 text-sm">
                    <li class="flex justify-between gap-4">
                        <span class="text-slate-500">Activité générée</span>
                        @if ($treatment->activity)
                            <a href="{{ route('activities.show', $treatment->activity) }}" class="text-emerald-700 hover:underline text-right">
                                {{ $treatment->activity->title }}
                            </a>
                        @else
                            <span>—</span>
                        @endif
                    </li>
                    <li class="flex justify-between gap-4">
                        <span class="text-slate-500">Mouvement de stock</span>
                        @if ($treatment->stockMovement)
                            <span class="font-mono text-xs text-right">
                                {{ $treatment->stockMovement->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float) $treatment->stockMovement->quantity_delta, 3, ',', ' ') }}
                                le {{ $treatment->stockMovement->occurred_at->format('d/m/Y H:i') }}
                            </span>
                        @else
                            <span>—</span>
                        @endif
                    </li>
                </ul>
            </x-card>

            <x-card title="Récoltes de la campagne">
                @php
                    $linkedHarvests = $treatment->campaign?->harvests ?? collect();
                @endphp
                @if ($linkedHarvests->isEmpty())
                    <p class="text-sm text-slate-600">Aucune récolte enregistrée pour cette campagne.</p>
                @else
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($linkedHarvests->sortBy('harvested_at') as $harvest)
                            <li class="flex justify-between py-2">
                                <a href="{{ route('harvests.show', $harvest) }}" class="text-emerald-700 hover:underline">
                                    {{ $harvest->harvested_at->format('d/m/Y H:i') }}
                                </a>
                                <span class="text-xs">{{ number_format((float) $harvest->quantity, 2, ',', ' ') }} {{ $harvest->unit }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
@endsection
