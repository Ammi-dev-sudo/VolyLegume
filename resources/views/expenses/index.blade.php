@extends('layouts.app')

@section('title', 'Dépenses')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Dépenses</h1>
            <p class="text-sm text-slate-500">Total affiché : <strong>{{ number_format((float) $total, 0, ',', ' ') }} Ar</strong></p>
        </div>
        @can('manage-finances')
            <a href="{{ route('expenses.create') }}"
               class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Nouvelle dépense
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('expenses.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="f-campaign" class="block text-xs font-medium text-slate-600">Campagne</label>
            <select id="f-campaign" name="campaign_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Toutes</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected(request('campaign_id') == $campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </select>
        </div>
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
            <label for="f-from" class="block text-xs font-medium text-slate-600">Du</label>
            <input type="date" id="f-from" name="from" value="{{ request('from') }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="f-to" class="block text-xs font-medium text-slate-600">Au</label>
            <input type="date" id="f-to" name="to" value="{{ request('to') }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('expenses.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($expenses->isEmpty())
            <p class="text-sm text-slate-600">Aucune dépense enregistrée.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Description</th>
                        <th class="py-2 pr-4">Catégorie</th>
                        <th class="py-2 pr-4">Campagne</th>
                        <th class="py-2 pr-4 text-right">Montant</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($expenses as $expense)
                        <tr>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $expense->expense_date->format('d/m/Y') }}</td>
                            <td class="py-2 pr-4">{{ $expense->description }}</td>
                            <td class="py-2 pr-4 text-xs">{{ $expense->category->label() }}</td>
                            <td class="py-2 pr-4 text-xs">{{ $expense->campaign?->name ?? '—' }}</td>
                            <td class="py-2 pr-4 text-right font-medium">{{ number_format((float) $expense->amount, 0, ',', ' ') }} Ar</td>
                            <td class="py-2 text-right text-xs">
                                @can('manage-finances')
                                    <a href="{{ route('expenses.edit', $expense) }}" class="text-emerald-700 hover:underline">Modifier</a>
                                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline"
                                          onsubmit="return confirm('Supprimer cette dépense ?');">
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
            <div class="mt-4">{{ $expenses->links() }}</div>
        @endif
    </x-card>
@endsection
