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
            <a href="{{ route('issues.create', ['production_unit_id' => $campaign->production_unit_id, 'campaign_id' => $campaign->id]) }}"
               class="rounded border border-amber-300 px-3 py-1.5 text-amber-800 hover:bg-amber-50">Signaler un problème</a>
            <a href="{{ route('campaigns.photos.index', $campaign) }}"
               class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Photos ({{ $photoCount }})</a>
            <a href="{{ route('campaigns.photos.compare', $campaign) }}"
               class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Avant / après</a>
        </div>
    </div>

    @php
        $bound = $latestHarvestBound ? \Illuminate\Support\Carbon::parse($latestHarvestBound) : null;
        $stageStyles = [
            'amber' => 'bg-amber-100 text-amber-800 border-amber-300',
            'lime' => 'bg-lime-100 text-lime-800 border-lime-300',
            'emerald' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'teal' => 'bg-teal-100 text-teal-800 border-teal-300',
            'sky' => 'bg-sky-100 text-sky-800 border-sky-300',
            'violet' => 'bg-violet-100 text-violet-800 border-violet-300',
            'orange' => 'bg-orange-100 text-orange-800 border-orange-300',
            'slate' => 'bg-slate-100 text-slate-700 border-slate-300',
        ];
    @endphp

    <x-card class="mb-6" title="Cycle cultural"
            subtitle="La progression est calculée uniquement à partir des étapes réellement enregistrées.">
        <div class="flex flex-wrap items-center gap-4">
            <div class="min-w-[13rem] flex-1">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase text-slate-500">Étape actuelle</p>
                        <p class="text-lg font-semibold text-slate-800">{{ $progress['current']?->label() ?? 'Non renseignée' }}</p>
                    </div>
                    <p class="text-2xl font-bold text-emerald-700">{{ $progress['progress'] }} %</p>
                </div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-600" style="width: {{ $progress['progress'] }}%"></div>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $progress['completed'] }} / {{ $progress['total'] }} étape(s) enregistrée(s)
                    @if ($progress['latest_at']) · dernière mise à jour {{ $progress['latest_at']->format('d/m/Y') }} @endif
                </p>
            </div>
        </div>

        <ol class="mt-4 grid gap-2 sm:grid-cols-3">
            @foreach ($progress['timeline'] as $step)
                <li class="rounded-lg border px-3 py-2 text-xs {{ $step['recorded'] ? ($stageStyles[$step['stage']->color()] ?? 'bg-emerald-50 text-emerald-800 border-emerald-200') : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                    <span class="font-semibold">{{ $step['stage']->label() }}</span>
                    @if ($step['recorded'])
                        <span class="block">{{ $step['event']->recorded_at->format('d/m/Y') }}</span>
                    @else
                        <span class="block">Non enregistrée</span>
                    @endif
                </li>
            @endforeach
        </ol>

        @can('create', \App\Models\CampaignStageEvent::class)
            <form method="POST" action="{{ route('campaigns.stages.store', $campaign) }}" enctype="multipart/form-data"
                  class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label for="stage" class="block text-sm font-medium text-slate-700">Étape à enregistrer</label>
                    <select id="stage" name="stage" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @foreach (\App\Enums\CropStage::ordered() as $stage)
                            <option value="{{ $stage->value }}">{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="recorded_at" class="block text-sm font-medium text-slate-700">Date réelle</label>
                    <input id="recorded_at" name="recorded_at" type="date" required value="{{ now()->toDateString() }}"
                           class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label for="stage_notes" class="block text-sm font-medium text-slate-700">Observations</label>
                    <textarea id="stage_notes" name="notes" rows="2" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <label for="stage_photo" class="block text-sm font-medium text-slate-700">Photo de l’étape (facultatif)</label>
                    <input id="stage_photo" name="photo" type="file" accept="image/*" class="mt-1 w-full text-sm">
                </div>
                <div>
                    <label for="stage_caption" class="block text-sm font-medium text-slate-700">Légende de la photo</label>
                    <input id="stage_caption" name="caption" maxlength="255" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer l’étape
                    </button>
                </div>
            </form>
        @endcan
    </x-card>
    <div class="mb-4 rounded border px-4 py-3 text-sm
        {{ $bound ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900' }}">
        @if ($bound)
            Restriction antirésiduelle : récolte autorisée à partir du <strong>{{ $bound->format('d/m/Y H:i') }}</strong>
            (borne calculée côté serveur, égalité acceptée).
        @else
            Aucun traitement phytosanitaire : aucune restriction de délai avant récolte.
        @endif
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
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
        <x-card>
            <p class="text-xs uppercase text-slate-500">Eau utilisée</p>
            <p class="mt-1 text-lg font-semibold">{{ number_format((float) $irrigationSummary['total_volume_m3'], 3, ',', ' ') }} m³</p>
            <p class="text-xs text-slate-500">{{ $irrigationSummary['events_count'] }} arrosage(s) · {{ $irrigationSummary['volume_per_m2'] !== null ? number_format((float) $irrigationSummary['volume_per_m2'], 4, ',', ' ').' m³/m²' : '—' }}</p>
            @if ($irrigationSummary['quota_m3'] !== null)
                <p class="mt-1 text-xs {{ $irrigationSummary['status'] === 'exceeded' ? 'text-red-600' : ($irrigationSummary['status'] === 'approaching' ? 'text-amber-700' : 'text-emerald-700') }}">
                    Quota {{ number_format((float) $irrigationSummary['quota_m3'], 3, ',', ' ') }} m³ · {{ $irrigationSummary['status'] === 'exceeded' ? 'dépassement' : ($irrigationSummary['status'] === 'approaching' ? 'approche du seuil' : 'OK') }}
                </p>
            @endif
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

            <x-card title="Problèmes agricoles" subtitle="Constats rattachés à cette culture">
                @if ($issues->isEmpty())
                    <p class="text-sm text-slate-600">Aucun problème signalé.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($issues as $issue)
                            <li class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2">
                                <div class="min-w-0">
                                    <a href="{{ route('issues.show', $issue) }}" class="font-medium text-emerald-700 hover:underline">
                                        {{ $issue->title }}
                                    </a>
                                    <p class="text-xs text-slate-500">{{ $issue->type->label() }} · {{ $issue->observed_at->format('d/m/Y') }}</p>
                                </div>
                                <span class="shrink-0 rounded px-2 py-0.5 text-xs font-semibold {{ $issue->severity->value === 'critical' ? 'bg-red-100 text-red-800' : ($issue->severity->value === 'high' ? 'bg-orange-100 text-orange-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $issue->severity->label() }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
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
