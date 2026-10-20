@extends('layouts.app')

@section('title', 'Mouvements de stock')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Mouvements de stock</h1>
            <p class="text-sm text-slate-500">
                <a href="{{ route('inputs.index') }}" class="text-emerald-700 hover:underline">← Retour aux intrants</a>
            </p>
        </div>
        <a href="{{ route('stock-movements.create') }}"
           class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Nouveau mouvement
        </a>
    </div>

    <form method="GET" action="{{ route('stock-movements.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="f-input" class="block text-xs font-medium text-slate-600">Intrant</label>
            <select id="f-input" name="input_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($inputs as $input)
                    <option value="{{ $input->id }}" @selected(request('input_id') == $input->id)>{{ $input->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-type" class="block text-xs font-medium text-slate-600">Type</label>
            <select id="f-type" name="type" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
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
            <label for="f-from" class="block text-xs font-medium text-slate-600">Du</label>
            <input type="date" id="f-from" name="from" value="{{ request('from') }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="f-to" class="block text-xs font-medium text-slate-600">Au</label>
            <input type="date" id="f-to" name="to" value="{{ request('to') }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('stock-movements.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($movements->isEmpty())
            <p class="text-sm text-slate-600">Aucun mouvement.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Intrant</th>
                        <th class="py-2 pr-4">Type</th>
                        <th class="py-2 pr-4">Variation</th>
                        <th class="py-2 pr-4">Campagne</th>
                        <th class="py-2 pr-4">Motif</th>
                        <th class="py-2">Par</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($movements as $movement)
                        <tr>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">
                                <a href="{{ route('inputs.show', $movement->input) }}" class="text-emerald-700 hover:underline">
                                    {{ $movement->input?->name ?? '—' }}
                                </a>
                            </td>
                            <td class="py-2 pr-4 text-xs">{{ $movement->type->label() }}</td>
                            <td class="py-2 pr-4 font-mono text-xs {{ $movement->quantity_delta >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                {{ $movement->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float) $movement->quantity_delta, 3, ',', ' ') }}
                                {{ $movement->input?->base_unit?->value }}
                            </td>
                            <td class="py-2 pr-4 text-xs">{{ $movement->campaign?->name ?? '—' }}</td>
                            <td class="py-2 pr-4 text-xs">{{ $movement->reason }}</td>
                            <td class="py-2 text-xs">{{ $movement->createdBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $movements->links() }}</div>
        @endif
    </x-card>
@endsection
