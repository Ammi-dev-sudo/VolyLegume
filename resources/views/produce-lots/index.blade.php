@extends('layouts.app')

@section('title', 'Stock récolté')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Stock récolté</h1>
            <p class="text-sm text-slate-500">Lots issus des récoltes, disponibilité et traçabilité jusqu’à la vente.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('harvests.create') }}"
               class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                Nouvelle récolte
            </a>
            <a href="{{ route('sales.create') }}"
               class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Nouvelle vente
            </a>
        </div>
    </div>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Stock disponible</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700">
                {{ number_format($availableTotal, 1, ',', ' ') }} kg
            </p>
            <p class="text-xs text-slate-500">Tous lots confondus</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Lots affichés</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $lots->total() }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">À vendre</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
                {{ $lots->getCollection()->where('status', App\Enums\ProduceLotStatus::Available)->count() }}
            </p>
            <p class="text-xs text-slate-500">sur cette page</p>
        </x-card>
    </div>

    <form method="GET" action="{{ route('produce-lots.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="search" class="block text-xs font-medium text-slate-600">Recherche</label>
            <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Code lot, emplacement"
                   class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="campaign_id" class="block text-xs font-medium text-slate-600">Campagne</label>
            <select id="campaign_id" name="campaign_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Toutes</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected(request('campaign_id') == $campaign->id)>
                        {{ $campaign->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-xs font-medium text-slate-600">Statut</label>
            <select id="status" name="status" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 pb-2 text-xs text-slate-700">
            <input type="checkbox" name="available" value="1" @checked(request()->boolean('available'))
                   class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
            Disponibles uniquement
        </label>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('produce-lots.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($lots->isEmpty())
            <p class="text-sm text-slate-600">Aucun lot de récolte. Enregistrez une récolte pour alimenter le stock.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Lot</th>
                            <th class="py-2 pr-4">Culture / campagne</th>
                            <th class="py-2 pr-4">Récolte</th>
                            <th class="py-2 pr-4">Disponible</th>
                            <th class="py-2 pr-4">Initial</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 text-right">Détail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lots as $lot)
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $lot->code }}</td>
                                <td class="py-2 pr-4">
                                    <p class="font-medium">{{ $lot->campaign?->crop?->name ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ $lot->campaign?->name }}</p>
                                </td>
                                <td class="py-2 pr-4 text-xs">{{ $lot->harvested_at->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4 font-semibold {{ (float) $lot->available_quantity > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ number_format((float) $lot->available_quantity, 2, ',', ' ') }} {{ $lot->unit }}
                                </td>
                                <td class="py-2 pr-4 text-xs text-slate-500">
                                    {{ number_format((float) $lot->initial_quantity, 2, ',', ' ') }} {{ $lot->unit }}
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="rounded px-2 py-0.5 text-xs
                                        {{ $lot->status === App\Enums\ProduceLotStatus::Available ? 'bg-emerald-100 text-emerald-800' : ($lot->status === App\Enums\ProduceLotStatus::Lost ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $lot->status->label() }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('produce-lots.show', $lot) }}" class="text-emerald-700 hover:underline">Ouvrir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $lots->links() }}</div>
        @endif
    </x-card>
@endsection
