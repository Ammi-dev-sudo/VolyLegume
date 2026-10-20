@extends('layouts.app')

@section('title', 'Génération du calendrier')

@section('content')
    <div class="mb-4">
        <p class="text-sm text-slate-500">
            <a href="{{ route('campaigns.show', $campaign) }}" class="text-emerald-700 hover:underline">← {{ $campaign->name }}</a>
        </p>
        <h1 class="text-xl font-semibold text-slate-800">Aperçu de la génération du calendrier</h1>
        <p class="text-sm text-slate-500">
            Date de référence : <strong>{{ $campaign->start_date->format('d/m/Y') }}</strong>
            ({{ $campaign->reference_type->label() }}) — culture {{ $campaign->crop?->name }}.
        </p>
    </div>

    <x-card>
        @if ($rows->isEmpty())
            <p class="text-sm text-slate-600">
                Aucune étape active pour cette culture.
                <a href="{{ route('calendar-steps.index', ['crop_id' => $campaign->crop_id]) }}" class="text-emerald-700 hover:underline">
                    Définir des étapes
                </a>.
            </p>
        @else
            <form method="POST" action="{{ route('campaigns.calendar.generate', $campaign) }}">
                @csrf
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Générer</th>
                            <th class="py-2 pr-4">Position</th>
                            <th class="py-2 pr-4">Étape</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Décalage</th>
                            <th class="py-2 pr-4">Date calculée</th>
                            <th class="py-2">État actuel</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php
                                $step = $row['step'];
                                $current = $existing[$step->id] ?? null;
                            @endphp
                            <tr>
                                <td class="py-2 pr-4">
                                    <input type="checkbox" name="step_ids[]" value="{{ $step->id }}"
                                           @checked($current === null || $current->status->value === 'planned')
                                           class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                                </td>
                                <td class="py-2 pr-4 text-xs text-slate-500">{{ $step->position }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $step->name }}</td>
                                <td class="py-2 pr-4 text-xs">{{ $step->activity_type->label() }}</td>
                                <td class="py-2 pr-4 text-xs">J{{ $step->offset_days >= 0 ? '+' : '' }}{{ $step->offset_days }}</td>
                                <td class="py-2 pr-4 font-medium text-emerald-700">{{ $row['planned_date']->format('d/m/Y') }}</td>
                                <td class="py-2 text-xs">
                                    @if ($current === null)
                                        <span class="text-slate-500">activité à créer</span>
                                    @elseif ($current->status->value === 'completed')
                                        <span class="text-emerald-700">réalisée le {{ $current->performed_at?->format('d/m/Y') }} (conservée)</span>
                                    @elseif ($current->status->value === 'cancelled')
                                        <span class="text-red-600">annulée (conservée)</span>
                                    @else
                                        <span class="text-amber-700">prévue le {{ $current->planned_date->format('d/m/Y') }} (recalculée)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Générer les activités sélectionnées
                    </button>
                    <a href="{{ route('campaigns.show', $campaign) }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Retour à la campagne</a>
                </div>
            </form>
        @endif
    </x-card>
@endsection
