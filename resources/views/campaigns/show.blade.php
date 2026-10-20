@extends('layouts.app')

@section('title', $campaign->name)

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ $campaign->name }}</h1>
            <p class="text-sm text-slate-500">
                {{ $campaign->productionUnit?->code }} — {{ $campaign->productionUnit?->name ?? 'Parcelle inconnue' }}
                · {{ $campaign->crop?->name }} ({{ $campaign->crop?->variety }})
                · {{ $campaign->reference_type->label() }} du {{ $campaign->start_date->format('d/m/Y') }}
                · {{ number_format((float) $campaign->cultivated_area_m2, 0, ',', ' ') }} m²
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <span class="rounded px-2 py-1 text-xs font-semibold
                {{ $campaign->status->value === 'ongoing' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                {{ $campaign->status->label() }}
            </span>
            @can('manage-referentials')
                <a href="{{ route('campaigns.edit', $campaign) }}"
                   class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Modifier</a>
            @endcan
            <a href="{{ route('activities.create', ['campaign_id' => $campaign->id]) }}"
               class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Activité</a>
            <a href="{{ route('treatments.create', ['campaign_id' => $campaign->id]) }}"
               class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Traitement</a>
            <a href="{{ route('harvests.create', ['campaign_id' => $campaign->id]) }}"
               class="rounded bg-emerald-700 px-3 py-1.5 font-semibold text-white hover:bg-emerald-800">Récolte</a>
        </div>
    </div>

    @php
        $bound = $latestHarvestBound ? \Illuminate\Support\Carbon::parse($latestHarvestBound) : null;
    @endphp
    <div class="mb-4 rounded border px-4 py-3 text-sm
        {{ $bound ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900' }}">
        @if ($bound)
            Restriction antirésiduelle : récolte autorisée à partir du <strong>{{ $bound->format('d/m/Y H:i') }}</strong>
            (borne calculée côté serveur, égalité acceptée).
        @else
            Aucun traitement phytosanitaire : aucune restriction de délai avant récolte.
        @endif
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-card>
            <p class="text-xs uppercase text-slate-500">Dépenses</p>
            <p class="mt-1 text-lg font-semibold">{{ number_format((float) $metrics['expenses'], 0, ',', ' ') }} Ar</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase text-slate-500">Recettes</p>
            <p class="mt-1 text-lg font-semibold">{{ number_format((float) $metrics['revenues'], 0, ',', ' ') }} Ar</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase text-slate-500">Marge</p>
            <p class="mt-1 text-lg font-semibold {{ $metrics['margin'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                {{ number_format((float) $metrics['margin'], 0, ',', ' ') }} Ar
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase text-slate-500">Activités</p>
            <p class="mt-1 text-lg font-semibold">
                {{ $metrics['completion_rate'] !== null ? number_format($metrics['completion_rate'], 1, ',', ' ') . ' %' : '—' }}
            </p>
            <p class="text-xs text-slate-500">{{ $metrics['late_activities'] }} en retard</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase text-slate-500">Récolte cumulée</p>
            <p class="mt-1 text-lg font-semibold">{{ number_format((float) $metrics['harvested_quantity'], 1, ',', ' ') }}</p>
            <p class="text-xs text-slate-500">{{ $campaign->crop?->production_unit }}</p>
        </x-card>
    </div>

    <x-card class="mb-6" title="Génération du calendrier"
            subtitle="Dates calculées depuis la date de référence de la campagne ; les activités réalisées sont conservées.">
        @if ($calendarPreview->isEmpty())
            <p class="text-sm text-slate-600">Aucune étape de calendrier active pour « {{ $campaign->crop?->name }} ».</p>
        @else
            <form method="POST" action="{{ route('campaigns.calendar.generate', $campaign) }}">
                @csrf
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Générer</th>
                            <th class="py-2 pr-4">Étape</th>
                            <th class="py-2 pr-4">Activité</th>
                            <th class="py-2 pr-4">Date prévue</th>
                            <th class="py-2">État</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($calendarPreview as $row)
                            @php
                                $existingStep = $existing[$row['step']->id] ?? null;
                            @endphp
                            <tr>
                                <td class="py-2 pr-4">
                                    <input type="checkbox" name="step_ids[]" value="{{ $row['step']->id }}"
                                           @checked($existingStep === null || $existingStep->status->value === 'planned')
                                           class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                                </td>
                                <td class="py-2 pr-4 font-medium">{{ $row['step']->name }}</td>
                                <td class="py-2 pr-4">{{ $row['step']->activity_type->label() }}</td>
                                <td class="py-2 pr-4">{{ $row['planned_date']->format('d/m/Y') }}</td>
                                <td class="py-2 text-xs">
                                    @if ($existingStep === null)
                                        <span class="text-slate-500">à créer</span>
                                    @elseif ($existingStep->status->value === 'completed')
                                        <span class="text-emerald-700">réalisée (conservée)</span>
                                    @elseif ($existingStep->status->value === 'cancelled')
                                        <span class="text-red-600">annulée (conservée)</span>
                                    @else
                                        <span class="text-amber-700">planifiée (mise à jour)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4 flex flex-wrap gap-3">
                    @can('manage-referentials')
                        <button type="submit"
                                class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Générer / resynchroniser
                        </button>
                        <button type="submit" form="replan-form"
                                class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            Replanifier les activités non réalisées
                        </button>
                    @endcan
                    <a href="{{ route('campaigns.calendar', $campaign) }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Aperçu détaillé
                    </a>
                </div>
            </form>

            <form id="replan-form" method="POST" action="{{ route('campaigns.calendar.replan', $campaign) }}" class="hidden">
                @csrf
            </form>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Activités">
            @if ($activities->isEmpty())
                <p class="text-sm text-slate-600">Aucune activité.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($activities as $activity)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div>
                                <a href="{{ route('activities.show', $activity) }}" class="font-medium text-emerald-700 hover:underline">
                                    {{ $activity->title }}
                                </a>
                                <p class="text-xs text-slate-500">
                                    {{ $activity->type->label() }}
                                    · prévue le {{ $activity->planned_date->format('d/m/Y') }}
                                    @if ($activity->assignedTo)
                                        · {{ $activity->assignedTo->name }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="rounded px-2 py-0.5
                                    {{ $activity->status->value === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($activity->status->value === 'cancelled' ? 'bg-red-100 text-red-700' : ($activity->isLate() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) }}">
                                    {{ $activity->status->label() }}
                                </span>
                                @if ($activity->status->value === 'planned')
                                    <form method="POST" action="{{ route('activities.complete', $activity) }}">
                                        @csrf
                                        <input type="hidden" name="performed_at" value="{{ now()->toDateString() }}">
                                        <button type="submit" class="text-emerald-700 hover:underline">Réaliser</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="Traitements phytosanitaires">
                @if ($treatments->isEmpty())
                    <p class="text-sm text-slate-600">Aucun traitement enregistré.</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-3">Produit</th>
                                <th class="py-2 pr-3">Appliqué le</th>
                                <th class="py-2 pr-3">Dose</th>
                                <th class="py-2">Récolte ≥</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($treatments as $treatment)
                                <tr>
                                    <td class="py-2 pr-3">
                                        <a href="{{ route('treatments.show', $treatment) }}" class="text-emerald-700 hover:underline">
                                            {{ $treatment->input?->name }}
                                        </a>
                                    </td>
                                    <td class="py-2 pr-3">{{ $treatment->applied_at->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-3">{{ $treatment->dose_value }} / {{ $treatment->dose_unit }}</td>
                                    <td class="py-2 text-xs">{{ $treatment->minimum_harvest_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Récoltes">
                @if ($harvests->isEmpty())
                    <p class="text-sm text-slate-600">Aucune récolte.</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-3">Date</th>
                                <th class="py-2 pr-3">Quantité</th>
                                <th class="py-2 pr-3">Qualité</th>
                                <th class="py-2">Par</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($harvests as $harvest)
                                <tr>
                                    <td class="py-2 pr-3">
                                        <a href="{{ route('harvests.show', $harvest) }}" class="text-emerald-700 hover:underline">
                                            {{ $harvest->harvested_at->format('d/m/Y H:i') }}
                                        </a>
                                    </td>
                                    <td class="py-2 pr-3">{{ number_format((float) $harvest->quantity, 2, ',', ' ') }} {{ $harvest->unit }}</td>
                                    <td class="py-2 pr-3">{{ $harvest->quality ?? '—' }}</td>
                                    <td class="py-2 text-xs">{{ $harvest->recordedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        </div>
    </div>
@endsection
