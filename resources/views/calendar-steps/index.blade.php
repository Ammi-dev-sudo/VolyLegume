@extends('layouts.app')

@section('title', 'Modèles de calendrier')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Modèles de calendrier</h1>
            <p class="text-sm text-slate-500">Étapes types par culture, reprises à la génération du calendrier d’une campagne.</p>
        </div>
        @can('manage-referentials')
            <a href="{{ route('calendar-steps.create') }}"
               class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Nouvelle étape
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('calendar-steps.index') }}" class="mb-4 flex items-center gap-2 text-sm">
        <label for="crop_id" class="text-slate-600">Culture :</label>
        <select id="crop_id" name="crop_id" class="rounded border border-slate-300 px-2 py-1.5"
                onchange="this.form.submit()">
            <option value="">Toutes</option>
            @foreach ($crops as $crop)
                <option value="{{ $crop->id }}" @selected(request('crop_id') == $crop->id)>
                    {{ $crop->name }}
                </option>
            @endforeach
        </select>
    </form>

    <x-card>
        @if ($steps->isEmpty())
            <p class="text-sm text-slate-600">Aucune étape définie.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Culture</th>
                        <th class="py-2 pr-4">Étape</th>
                        <th class="py-2 pr-4">Activité</th>
                        <th class="py-2 pr-4">Décalage (j)</th>
                        <th class="py-2 pr-4">Position</th>
                        <th class="py-2 pr-4">Active</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($steps as $step)
                        <tr>
                            <td class="py-2 pr-4">{{ $step->crop?->name ?? '—' }}</td>
                            <td class="py-2 pr-4 font-medium">{{ $step->name }}</td>
                            <td class="py-2 pr-4">{{ $step->activity_type->label() }}</td>
                            <td class="py-2 pr-4">+{{ $step->offset_days }}</td>
                            <td class="py-2 pr-4">{{ $step->position }}</td>
                            <td class="py-2 pr-4">{{ $step->is_active ? 'Oui' : 'Non' }}</td>
                            <td class="py-2 text-right">
                                @can('manage-referentials')
                                    <a href="{{ route('calendar-steps.edit', $step) }}" class="text-emerald-700 hover:underline">Modifier</a>
                                    <form method="POST" action="{{ route('calendar-steps.destroy', $step) }}" class="inline"
                                          onsubmit="return confirm('Supprimer cette étape ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-3 text-red-600 hover:underline">Supprimer</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $steps->links() }}</div>
        @endif
    </x-card>
@endsection
