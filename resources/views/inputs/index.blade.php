@extends('layouts.app')

@section('title', 'Intrants & stocks')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Intrants & stocks</h1>
            <p class="text-sm text-slate-500">
                <a href="{{ route('stock-movements.index') }}" class="text-emerald-700 hover:underline">Historique des mouvements →</a>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('stock-movements.create') }}"
               class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Mouvement de stock</a>
            @can('manage-referentials')
                <a href="{{ route('inputs.create') }}"
                   class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Nouvel intrant</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('inputs.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="f-category" class="block text-xs font-medium text-slate-600">Catégorie</label>
            <select id="f-category" name="category" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Toutes</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(request('category') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-search" class="block text-xs font-medium text-slate-600">Recherche</label>
            <input type="search" id="f-search" name="search" value="{{ request('search') }}"
                   placeholder="Nom…" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <label class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="critical" value="1" @checked(request()->boolean('critical')) class="rounded border-slate-300 text-emerald-700">
            Stocks critiques uniquement
        </label>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('inputs.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($inputs->isEmpty())
            <p class="text-sm text-slate-600">Aucun intrant enregistré.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Code</th>
                        <th class="py-2 pr-4">Intrant</th>
                        <th class="py-2 pr-4">Catégorie</th>
                        <th class="py-2 pr-4">Stock</th>
                        <th class="py-2 pr-4">Seuil</th>
                        <th class="py-2 pr-4">État</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($inputs as $input)
                        <tr class="{{ $input->isCritical() ? 'bg-red-50' : '' }}">
                            <td class="py-2 pr-4 font-mono text-xs">{{ $input->code }}</td>
                            <td class="py-2 pr-4">
                                <a href="{{ route('inputs.show', $input) }}" class="font-medium text-emerald-700 hover:underline">
                                    {{ $input->name }}
                                </a>
                            </td>
                            <td class="py-2 pr-4 text-xs">{{ $input->category->label() }}</td>
                            <td class="py-2 pr-4">{{ number_format((float) $input->quantity, 3, ',', ' ') }} {{ $input->base_unit->value }}</td>
                            <td class="py-2 pr-4">{{ number_format((float) $input->minimum_stock, 3, ',', ' ') }}</td>
                            <td class="py-2 pr-4 text-xs">
                                @if ($input->isCritical())
                                    <span class="rounded bg-red-100 px-2 py-0.5 font-semibold text-red-700">Critique</span>
                                @elseif (! $input->is_active)
                                    <span class="rounded bg-slate-200 px-2 py-0.5 text-slate-600">Inactif</span>
                                @else
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">OK</span>
                                @endif
                            </td>
                            <td class="py-2 text-right text-xs">
                                <a href="{{ route('stock-movements.create', ['input_id' => $input->id]) }}" class="text-slate-600 hover:underline">Mouvement</a>
                                @can('manage-referentials')
                                    <a href="{{ route('inputs.edit', $input) }}" class="ml-3 text-emerald-700 hover:underline">Modifier</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $inputs->links() }}</div>
        @endif
    </x-card>
@endsection
