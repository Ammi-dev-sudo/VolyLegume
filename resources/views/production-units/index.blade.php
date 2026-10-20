@extends('layouts.app')

@section('title', 'Parcelles')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-slate-800">Parcelles</h1>
        @can('manage-referentials')
            <a href="{{ route('production-units.create') }}"
               class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Nouvelle parcelle
            </a>
        @endcan
    </div>

    <x-card>
        @if ($units->isEmpty())
            <p class="text-sm text-slate-600">Aucune parcelle enregistrée pour cette exploitation.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Code</th>
                        <th class="py-2 pr-4">Nom</th>
                        <th class="py-2 pr-4">Superficie</th>
                        <th class="py-2 pr-4">Localisation</th>
                        <th class="py-2 pr-4">Statut</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($units as $unit)
                        <tr>
                            <td class="py-2 pr-4 font-mono text-xs">{{ $unit->code }}</td>
                            <td class="py-2 pr-4">{{ $unit->name }}</td>
                            <td class="py-2 pr-4">{{ number_format((float) $unit->area_m2, 0, ',', ' ') }} m²</td>
                            <td class="py-2 pr-4">{{ $unit->location }}</td>
                            <td class="py-2 pr-4">
                                <span class="rounded px-2 py-0.5 text-xs
                                    {{ $unit->status->value === 'occupied' ? 'bg-emerald-100 text-emerald-800' : ($unit->status->value === 'inactive' ? 'bg-slate-200 text-slate-600' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $unit->status->label() }}
                                </span>
                            </td>
                            <td class="py-2 text-right">
                                @can('manage-referentials')
                                    <a href="{{ route('production-units.edit', $unit) }}" class="text-emerald-700 hover:underline">Modifier</a>
                                    <form method="POST" action="{{ route('production-units.destroy', $unit) }}" class="inline"
                                          onsubmit="return confirm('Supprimer cette parcelle ?');">
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

            <div class="mt-4">{{ $units->links() }}</div>
        @endif
    </x-card>
@endsection
