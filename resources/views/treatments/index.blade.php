@extends('layouts.app')

@section('title', 'Traitements')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Traitements phytosanitaires</h1>
            <p class="text-sm text-slate-500">Chaque traitement consomme du stock, crée une activité et fixe une borne de récolte.</p>
        </div>
        <a href="{{ route('treatments.create') }}"
           class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Nouveau traitement
        </a>
    </div>

    <form method="GET" action="{{ route('treatments.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
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
            <label for="f-input" class="block text-xs font-medium text-slate-600">Produit</label>
            <select id="f-input" name="input_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($inputs as $input)
                    <option value="{{ $input->id }}" @selected(request('input_id') == $input->id)>{{ $input->name }}</option>
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
        <a href="{{ route('treatments.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($treatments->isEmpty())
            <p class="text-sm text-slate-600">Aucun traitement enregistré.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Campagne</th>
                        <th class="py-2 pr-4">Produit</th>
                        <th class="py-2 pr-4">Dose</th>
                        <th class="py-2 pr-4">Délai</th>
                        <th class="py-2">Récolte autorisée ≥</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($treatments as $treatment)
                        <tr>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <a href="{{ route('treatments.show', $treatment) }}" class="text-emerald-700 hover:underline">
                                    {{ $treatment->applied_at->format('d/m/Y H:i') }}
                                </a>
                            </td>
                            <td class="py-2 pr-4 text-xs">{{ $treatment->campaign?->name ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $treatment->input?->name ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $treatment->dose_value }} / {{ $treatment->dose_unit }}</td>
                            <td class="py-2 pr-4">{{ $treatment->pre_harvest_days }} j</td>
                            <td class="py-2 text-xs font-medium text-amber-800">
                                {{ $treatment->minimum_harvest_at->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $treatments->links() }}</div>
        @endif
    </x-card>
@endsection
