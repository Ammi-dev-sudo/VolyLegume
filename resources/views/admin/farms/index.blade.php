@extends('layouts.app')

@section('title', 'Exploitations')

@section('content')
    @include('admin._nav')

    <div class="mb-4">
        <h1 class="text-xl font-semibold text-slate-800">Exploitations</h1>
        <p class="text-sm text-slate-500">Supervision de toutes les exploitations de la plateforme.</p>
    </div>

    <form method="GET" action="{{ route('admin.farms.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="f-search" class="block text-xs font-medium text-slate-600">Recherche</label>
            <input type="search" id="f-search" name="search" value="{{ request('search') }}"
                   placeholder="Nom ou localisation…" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="f-status" class="block text-xs font-medium text-slate-600">Statut</label>
            <select id="f-status" name="status" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('admin.farms.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-4">Exploitation</th>
                    <th class="py-2 pr-4">Responsable</th>
                    <th class="py-2 pr-4">Membres</th>
                    <th class="py-2 pr-4">Campagnes</th>
                    <th class="py-2 pr-4">Parcelles</th>
                    <th class="py-2 pr-4">Statut</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($farms as $farm)
                    <tr>
                        <td class="py-2 pr-4">
                            <a href="{{ route('admin.farms.show', $farm->id) }}" class="font-medium text-emerald-700 hover:underline">{{ $farm->name }}</a>
                            <p class="text-xs text-slate-500">{{ $farm->location }}</p>
                        </td>
                        <td class="py-2 pr-4 text-xs">{{ $farm->manager?->name ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $farm->memberships_count }}</td>
                        <td class="py-2 pr-4">{{ $farm->campaigns_count }}</td>
                        <td class="py-2 pr-4">{{ $farm->production_units_count }}</td>
                        <td class="py-2 pr-4">
                            <span class="rounded px-2 py-0.5 text-xs {{ $farm->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $farm->status->label() }}
                            </span>
                        </td>
                        <td class="py-2 text-right text-xs">
                            <a href="{{ route('admin.farms.show', $farm->id) }}" class="text-emerald-700 hover:underline">Détail</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $farms->links() }}</div>
    </x-card>
@endsection
