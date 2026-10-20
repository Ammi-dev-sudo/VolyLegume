@extends('layouts.app')

@section('title', $activity->title)

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ $activity->title }}</h1>
            <p class="text-sm text-slate-500">
                {{ $activity->type->label() }}
                · <a href="{{ route('campaigns.show', $activity->campaign) }}" class="text-emerald-700 hover:underline">
                    {{ $activity->campaign?->name }}
                </a>
                @if ($activity->campaign)
                    · {{ $activity->campaign->productionUnit?->code }}
                @endif
            </p>
        </div>
        <div class="flex gap-2 text-sm">
            @if ($activity->status->value !== 'completed')
                <a href="{{ route('activities.edit', $activity) }}"
                   class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Modifier</a>
            @endif
            @if ($activity->status->value === 'planned')
                <form method="POST" action="{{ route('activities.complete', $activity) }}">
                    @csrf
                    <input type="hidden" name="performed_at" value="{{ now()->toDateTimeString() }}">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-3 py-1.5 font-semibold text-white hover:bg-emerald-800">
                        Marquer réalisée
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($activity->isLate())
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Cette activité a dépassé sa date prévue ({{ $activity->planned_date->format('d/m/Y') }}).
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Détails">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Statut</dt>
                    <dd>
                        <span class="rounded px-2 py-0.5 text-xs
                            {{ $activity->status->value === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($activity->status->value === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700') }}">
                            {{ $activity->status->label() }}
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Date prévue</dt>
                    <dd class="font-medium">{{ $activity->planned_date->format('d/m/Y') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Réalisée le</dt>
                    <dd>{{ $activity->performed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Assignée à</dt>
                    <dd>{{ $activity->assignedTo?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Créée par</dt>
                    <dd>{{ $activity->createdBy?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Réalisée par</dt>
                    <dd>{{ $activity->performedBy?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Coût estimé</dt>
                    <dd>
                        @if ($activity->estimated_cost !== null)
                            {{ number_format((float) $activity->estimated_cost, 0, ',', ' ') }} Ar
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if ($activity->calendarStep)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Étape de calendrier</dt>
                        <dd>{{ $activity->calendarStep->name }} (J{{ $activity->calendarStep->offset_days >= 0 ? '+' : '' }}{{ $activity->calendarStep->offset_days }})</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card title="Description">
            @if (blank($activity->description))
                <p class="text-sm text-slate-500">Aucune description.</p>
            @else
                <p class="whitespace-pre-line text-sm text-slate-700">{{ $activity->description }}</p>
            @endif
        </x-card>
    </div>

    @if ($activity->status->value === 'planned')
        <form method="POST" action="{{ route('activities.destroy', $activity) }}" class="mt-6"
              onsubmit="return confirm('Supprimer cette activité ?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline">Supprimer l’activité</button>
        </form>
    @endif
@endsection
