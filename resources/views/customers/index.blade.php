@extends('layouts.app')

@section('title', 'Clients')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Clients</h1>
            <p class="text-sm text-slate-500">Particuliers, commerçants, restaurants et coopératives de l’exploitation.</p>
        </div>
        @can('create', App\Models\Customer::class)
            <a href="{{ route('customers.create') }}"
               class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Nouveau client
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('customers.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="search" class="block text-xs font-medium text-slate-600">Recherche</label>
            <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Nom, code, téléphone"
                   class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="type" class="block text-xs font-medium text-slate-600">Type</label>
            <select id="type" name="type" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('customers.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($customers->isEmpty())
            <p class="text-sm text-slate-600">Aucun client enregistré.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Code</th>
                            <th class="py-2 pr-4">Client</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Contact</th>
                            <th class="py-2 pr-4">Ventes</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($customers as $customer)
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $customer->code }}</td>
                                <td class="py-2 pr-4">
                                    <p class="font-medium text-slate-800">{{ $customer->name }}</p>
                                    @if ($customer->address)
                                        <p class="text-xs text-slate-500">{{ $customer->address }}</p>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-xs">{{ $customer->type->label() }}</td>
                                <td class="py-2 pr-4 text-xs">
                                    {{ $customer->contact_name ?? '—' }}<br>
                                    <span class="text-slate-500">{{ $customer->phone ?? $customer->email ?? '' }}</span>
                                </td>
                                <td class="py-2 pr-4 text-xs">{{ $customer->sales_count }}</td>
                                <td class="py-2 pr-4">
                                    <span class="rounded px-2 py-0.5 text-xs {{ $customer->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $customer->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="py-2 text-right text-xs">
                                    <a href="{{ route('sales.index', ['customer_id' => $customer->id]) }}" class="text-slate-600 hover:underline">Ventes</a>
                                    @can('update', $customer)
                                        <a href="{{ route('customers.edit', $customer) }}" class="ml-2 text-emerald-700 hover:underline">Modifier</a>
                                    @endcan
                                    @can('delete', $customer)
                                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="ml-2 inline"
                                              onsubmit="return confirm('Supprimer ce client ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $customers->links() }}</div>
        @endif
    </x-card>
@endsection
