@extends('layouts.app')

@section('title', 'Campagnes')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Campagnes</h1>
            <p class="text-sm text-slate-500">{{ $campaigns->total() }} campagne(s).</p>
        </div>
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('campaigns.index') }}" class="flex items-center gap-2 text-sm">
                <label for="status" class="text-slate-600">Statut :</label>
                <select id="status" name="status" class="rounded border border-slate-300 px-2 py-1.5" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </form>
            @can('manage-referentials')
                <a href="{{ route('campaigns.create') }}"
                   class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Nouvelle campagne
                </a>
            @endcan
        </div>
    </div>

    <x-card>
        @if ($campaigns->isEmpty())
            <p class="text-sm text-slate-600">Aucune campagne.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Campagne</th>
                        <th class="py-2 pr-4">Parcelle</th>
                        <th class="py-2 pr-4">Culture</th>
                        <th class="py-2 pr-4">Début</th>
                        <th class="py-2 pr-4">Fin prévue</th>
                        <th class="py-2 pr-4">Statut</th>
                        <th class="py-2 text-right">Totaux</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td class="py-2 pr-4">
                                <a href="{{ route('campaigns.show', $campaign) }}" class="font-medium text-emerald-700 hover:underline">
                                    {{ $campaign->name }}
                                </a>
                            </td>
                            <td class="py-2 pr-4">{{ $campaign->productionUnit?->code ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $campaign->crop?->name ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $campaign->start_date->format('d/m/Y') }}</td>
                            <td class="py-2 pr-4">{{ $campaign->expected_end_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                <span class="rounded px-2 py-0.5 text-xs
                                    {{ $campaign->status->value === 'ongoing' ? 'bg-emerald-100 text-emerald-800' : ($campaign->status->value === 'planned' ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $campaign->status->label() }}
                                </span>
                            </td>
                            <td class="py-2 text-right text-xs text-slate-500">
                                {{ $campaign->activities_count }} activité(s) ·
                                {{ $campaign->treatments_count }} traitement(s) ·
                                {{ $campaign->harvests_count }} récolte(s)
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $campaigns->links() }}</div>
        @endif
    </x-card>
@endsection
