@extends('layouts.app')

@section('title', 'Cultures')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-slate-800">Cultures</h1>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('crops.index') }}" class="flex flex-wrap gap-2">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Rechercher…"
                       class="rounded border border-slate-300 px-3 py-1.5 text-sm">
                <select name="category_id" class="rounded border border-slate-300 px-3 py-1.5 text-sm">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="rounded bg-slate-700 px-3 py-1.5 text-sm text-white hover:bg-slate-800">OK</button>
            </form>
            @can('manage-referentials')
                <a href="{{ route('crops.create') }}"
                   class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Nouvelle culture
                </a>
            @endcan
        </div>
    </div>

    <x-card>
        @if ($crops->isEmpty())
            <p class="text-sm text-slate-600">Aucune culture enregistrée.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Culture</th>
                        <th class="py-2 pr-4">Catégorie</th>
                        <th class="py-2 pr-4">Variété</th>
                        <th class="py-2 pr-4">Cycle estimé (j)</th>
                        <th class="py-2 pr-4">Statut</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($crops as $crop)
                        <tr>
                            <td class="py-2 pr-4 font-medium">
                                <a href="{{ route('crops.guide', $crop) }}" class="text-emerald-700 hover:underline">
                                    {{ $crop->name }}
                                </a>
                            </td>
                            <td class="py-2 pr-4">
                                @if ($crop->category)
                                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                        {{ $crop->category->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">Non classée</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ $crop->variety }}</td>
                            <td class="py-2 pr-4">{{ $crop->estimated_cycle_days ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                <span class="rounded px-2 py-0.5 text-xs {{ $crop->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $crop->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-2 text-right">
                                <a href="{{ route('crops.guide', $crop) }}" class="text-emerald-700 hover:underline">Fiche</a>
                                @can('manage-referentials')
                                    <a href="{{ route('crops.edit', $crop) }}" class="ml-3 text-emerald-700 hover:underline">Modifier</a>
                                    <form method="POST" action="{{ route('crops.destroy', $crop) }}" class="inline"
                                          onsubmit="return confirm('Supprimer cette culture ?');">
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
            <div class="mt-4">{{ $crops->links() }}</div>
        @endif
    </x-card>
@endsection
