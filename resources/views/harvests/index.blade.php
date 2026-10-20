@extends('layouts.app')

@section('title', 'Récoltes')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Récoltes</h1>
            <p class="text-sm text-slate-500">Le contrôle du délai antirésiduel est appliqué à l’enregistrement.</p>
        </div>
        <a href="{{ route('harvests.create') }}"
           class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Nouvelle récolte
        </a>
    </div>

    <form method="GET" action="{{ route('harvests.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
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
        <a href="{{ route('harvests.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($harvests->isEmpty())
            <p class="text-sm text-slate-600">Aucune récolte enregistrée.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Campagne</th>
                        <th class="py-2 pr-4">Quantité</th>
                        <th class="py-2 pr-4">Qualité</th>
                        <th class="py-2">Enregistrée par</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($harvests as $harvest)
                        <tr>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <a href="{{ route('harvests.show', $harvest) }}" class="text-emerald-700 hover:underline">
                                    {{ $harvest->harvested_at->format('d/m/Y H:i') }}
                                </a>
                            </td>
                            <td class="py-2 pr-4 text-xs">
                                {{ $harvest->campaign?->name ?? '—' }}
                                <span class="text-slate-400">({{ $harvest->campaign?->crop?->name }})</span>
                            </td>
                            <td class="py-2 pr-4 font-medium">
                                {{ number_format((float) $harvest->quantity, 2, ',', ' ') }} {{ $harvest->unit }}
                            </td>
                            <td class="py-2 pr-4 text-xs">{{ $harvest->quality ?? '—' }}</td>
                            <td class="py-2 text-xs">{{ $harvest->recordedBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $harvests->links() }}</div>
        @endif
    </x-card>
@endsection
