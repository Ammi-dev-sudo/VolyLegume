@extends('layouts.public')

@section('title', 'Traçabilité du lot '.$lot->code)

@section('content')
    <div class="mb-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Traçabilité publique</p>
        <h1 class="text-xl font-semibold text-slate-800">Lot {{ $lot->code }} — {{ $lot->campaign?->crop?->name }} {{ $lot->campaign?->crop?->variety }}</h1>
        <p class="text-sm text-slate-500">Récolté le {{ $lot->harvested_at->format('d/m/Y H:i') }} · {{ $lot->farm?->name }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Parcelle</p>
            <p class="mt-1 font-semibold text-slate-800">{{ $lot->campaign?->productionUnit?->code }} — {{ $lot->campaign?->productionUnit?->name }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Campagne</p>
            <p class="mt-1 font-semibold text-slate-800">{{ $lot->campaign?->name }}</p>
            <p class="text-xs text-slate-500">Du {{ $lot->campaign?->start_date?->format('d/m/Y') }} au {{ $lot->campaign?->expected_end_date?->format('d/m/Y') }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Quantité</p>
            <p class="mt-1 text-lg font-semibold text-slate-800">{{ number_format((float) $lot->initial_quantity, 2, ',', ' ') }} {{ $lot->unit }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Qualité</p>
            <p class="mt-1 font-semibold text-slate-800">{{ $lot->quality ?? '—' }}</p>
        </x-card>
    </div>

    <x-card class="mt-6" title="Chaîne de traçabilité" subtitle="Du sol à la récolte">
        <ol class="relative space-y-4 border-l border-emerald-200 pl-5">
            <li>
                <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-600"></span>
                <p class="text-sm font-semibold text-slate-800">Parcelle {{ $lot->campaign?->productionUnit?->code }}</p>
                <p class="text-xs text-slate-500">{{ $lot->campaign?->productionUnit?->location }} · {{ number_format((float) $lot->campaign?->cultivated_area_m2, 0, ',', ' ') }} m²</p>
            </li>
            <li>
                <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-600"></span>
                <p class="text-sm font-semibold text-slate-800">Campagne {{ $lot->campaign?->name }} — {{ $lot->campaign?->crop?->name }} ({{ $lot->campaign?->crop?->variety }})</p>
                <p class="text-xs text-slate-500">{{ $lot->campaign?->reference_type->label() }} du {{ $lot->campaign?->start_date?->format('d/m/Y') }}</p>
            </li>
            <li>
                <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-600"></span>
                <p class="text-sm font-semibold text-slate-800">Activités</p>
                <p class="text-xs text-slate-500">{{ $lot->campaign?->activities->count() ?? 0 }} activité(s)</p>
                @if ($lot->campaign?->activities->isNotEmpty())
                    <ul class="mt-1 list-inside list-disc text-xs text-slate-500">
                        @foreach ($lot->campaign->activities->take(6) as $activity)
                            <li>{{ $activity->type->label() }} — {{ $activity->planned_date->format('d/m/Y') }} ({{ $activity->status->label() }})</li>
                        @endforeach
                    </ul>
                @endif
            </li>
            <li>
                <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-600"></span>
                <p class="text-sm font-semibold text-slate-800">Traitements</p>
                @if ($lot->campaign?->treatments->isEmpty())
                    <p class="text-xs text-slate-500">Aucun traitement enregistré.</p>
                @else
                    <ul class="mt-1 list-inside list-disc text-xs text-slate-500">
                        @foreach ($lot->campaign->treatments as $treatment)
                            <li>{{ $treatment->input?->name }} — appliqué le {{ $treatment->applied_at->format('d/m/Y') }} · délai avant récolte {{ $treatment->minimum_harvest_at->format('d/m/Y H:i') }}</li>
                        @endforeach
                    </ul>
                @endif
            </li>
            <li>
                <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-amber-500"></span>
                <p class="text-sm font-semibold text-slate-800">Récolte</p>
                <p class="text-xs text-slate-500">Le {{ $lot->harvested_at->format('d/m/Y H:i') }} · {{ number_format((float) $lot->initial_quantity, 2, ',', ' ') }} {{ $lot->unit }} · par {{ $lot->harvest->recordedBy?->name ?? '—' }}</p>
            </li>
        </ol>
    </x-card>
@endsection
