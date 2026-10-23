@extends('layouts.app')

@section('title', 'Fiche de culture — '.$crop->name)

@section('content')
    @php
        $fields = [
            ['label' => 'Variétés', 'value' => $guide->varieties],
            ['label' => 'Conditions climatiques', 'value' => $guide->climate],
            ['label' => 'Type de sol', 'value' => $guide->soil],
            ['label' => 'Préparation du terrain', 'value' => $guide->land_preparation],
            ['label' => 'Matériel de plantation', 'value' => $guide->planting_material],
            ['label' => 'Pépinière', 'value' => $guide->nursery],
            ['label' => 'Semis', 'value' => $guide->sowing],
            ['label' => 'Repiquage', 'value' => $guide->transplanting],
            ['label' => 'Irrigation', 'value' => $guide->irrigation],
            ['label' => 'Fertilisation', 'value' => $guide->fertilization],
            ['label' => 'Entretien', 'value' => $guide->maintenance],
            ['label' => 'Maladies et ravageurs', 'value' => $guide->pests_diseases],
            ['label' => 'Durée du cycle', 'value' => $guide->cycle_duration],
            ['label' => 'Récolte', 'value' => $guide->harvest],
            ['label' => 'Rendement', 'value' => $guide->expected_yield],
            ['label' => 'Conservation', 'value' => $guide->storage],
        ];
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Fiche de culture</p>
            <h1 class="text-xl font-semibold text-slate-800">{{ $crop->name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                @if ($crop->category)
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                        {{ $crop->category->name }}
                    </span>
                @endif
                <span>Variété : {{ $crop->variety === 'non_precisee' ? 'non précisée' : $crop->variety }}</span>
                @if ($crop->estimated_cycle_days)
                    <span>· Cycle indicatif : {{ $crop->estimated_cycle_days }} jours</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('crops.index') }}"
               class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Retour aux cultures</a>
            <a href="{{ route('calendar-steps.index', ['crop_id' => $crop->id]) }}"
               class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Modèle de calendrier</a>
            @can('manage-referentials')
                <a href="{{ route('crops.edit', $crop) }}"
                   class="rounded bg-emerald-700 px-3 py-1.5 font-semibold text-white hover:bg-emerald-800">Modifier la culture</a>
            @endcan
        </div>
    </div>

    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-semibold">Fiche à adapter à votre contexte</p>
        <p class="mt-1">
            Les durées, doses, espacements, rendements et traitements doivent être confirmés avec la variété,
            la région, la saison et les recommandations techniques locales. Cette fiche est une aide à la décision,
            pas une prescription unique valable partout à Madagascar.
        </p>
    </div>

    <x-card class="mb-6" title="Résumé" :subtitle="$crop->category?->name">
        <p class="text-sm leading-relaxed text-slate-700">{{ $guide->overview }}</p>
    </x-card>

    <x-card class="mb-6" title="Fiche technique"
            subtitle="Rubriques structurées pour préparer, conduire et conserver la culture.">
        <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
            @foreach ($fields as $field)
                @if (filled($field['value']))
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $field['value'] }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </x-card>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <x-card title="Étapes communes"
                subtitle="Base applicable à presque toutes les cultures maraîchères.">
            <x-guide-steps :steps="$guide->common_steps" />
        </x-card>

        <x-card :title="'Étapes spécifiques — '.($crop->category?->name ?? 'catégorie non précisée')"
                subtitle="Opérations particulières à cette famille de légumes.">
            <x-guide-steps :steps="$guide->category_steps" />
        </x-card>
    </div>

    @if (! empty($guide->specific_steps))
        <x-card class="mb-6" :title="'Points spécifiques — '.$crop->name"
                subtitle="Différences importantes à ne pas négliger pour ce légume.">
            <x-guide-steps :steps="$guide->specific_steps" />
        </x-card>
    @endif

    <x-card title="Calendrier cultural"
            subtitle="Étapes datées utilisées pour générer les activités d’une campagne.">
        @if ($crop->calendarSteps->isEmpty())
            <p class="text-sm text-slate-600">
                Aucun modèle de calendrier n’est encore défini pour cette culture. La fiche ci-dessus fournit les
                opérations de base ; ajoutez un modèle de calendrier pour planifier automatiquement les activités.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Position</th>
                            <th class="py-2 pr-4">Étape</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Décalage</th>
                            <th class="py-2">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($crop->calendarSteps as $step)
                            <tr>
                                <td class="py-2 pr-4 text-xs text-slate-500">{{ $step->position }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $step->name }}</td>
                                <td class="py-2 pr-4 text-xs">{{ $step->activity_type->label() }}</td>
                                <td class="py-2 pr-4 text-xs font-mono">
                                    J{{ $step->offset_days >= 0 ? '+' : '' }}{{ $step->offset_days }}
                                </td>
                                <td class="py-2 text-xs text-slate-600">{{ $step->description ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if (filled($guide->sources))
        <p class="mt-4 text-xs leading-relaxed text-slate-500">{{ $guide->sources }}</p>
    @endif
@endsection
