@extends('layouts.app')

@section('title', 'Activités')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-slate-800">Activités</h1>
        <a href="{{ route('activities.create') }}"
           class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Nouvelle activité
        </a>
    </div>

    <form method="GET" action="{{ route('activities.index') }}" class="mb-4 rounded border border-slate-200 bg-white p-3">
        <div class="grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-4">
            <div>
                <label for="f-campaign" class="block text-xs font-medium text-slate-600">Campagne</label>
                <select id="f-campaign" name="campaign_id" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    <option value="">Toutes</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected(request('campaign_id') == $campaign->id)>{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-unit" class="block text-xs font-medium text-slate-600">Parcelle</label>
                <select id="f-unit" name="production_unit_id" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    <option value="">Toutes</option>
                    @foreach ($productionUnits as $unit)
                        <option value="{{ $unit->id }}" @selected(request('production_unit_id') == $unit->id)>{{ $unit->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-type" class="block text-xs font-medium text-slate-600">Type</label>
                <select id="f-type" name="type" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    <option value="">Tous</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-status" class="block text-xs font-medium text-slate-600">Statut</label>
                <select id="f-status" name="status" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    <option value="">Tous</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-from" class="block text-xs font-medium text-slate-600">Du</label>
                <input type="date" id="f-from" name="from" value="{{ request('from') }}"
                       class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
            </div>
            <div>
                <label for="f-to" class="block text-xs font-medium text-slate-600">Au</label>
                <input type="date" id="f-to" name="to" value="{{ request('to') }}"
                       class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
            </div>
            <div>
                <label for="f-created" class="block text-xs font-medium text-slate-600">Créée par</label>
                <select id="f-created" name="created_by" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                    <option value="">Tous</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected(request('created_by') == $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-search" class="block text-xs font-medium text-slate-600">Recherche</label>
                <input type="search" id="f-search" name="search" value="{{ request('search') }}"
                       placeholder="Intitulé…" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue')) class="rounded border-slate-300 text-emerald-700">
                En retard uniquement
            </label>
            <select name="sort" class="rounded border border-slate-300 px-2 py-1.5">
                @foreach (['planned_date' => 'Date prévue', 'status' => 'Statut', 'type' => 'Type', 'title' => 'Intitulé'] as $sortKey => $sortLabel)
                    <option value="{{ $sortKey }}" @selected(request('sort') === $sortKey)>{{ $sortLabel }}</option>
                @endforeach
            </select>
            <select name="direction" class="rounded border border-slate-300 px-2 py-1.5">
                <option value="desc" @selected(request('direction', 'asc') === 'desc')>Décroissant</option>
                <option value="asc" @selected(request('direction', 'asc') === 'asc')>Croissant</option>
            </select>
            <button type="submit" class="rounded bg-slate-700 px-3 py-1.5 text-white hover:bg-slate-800">Filtrer</button>
            <a href="{{ route('activities.index') }}" class="text-slate-500 underline">Réinitialiser</a>
        </div>
    </form>

    <x-card>
        @if ($activities->isEmpty())
            <p class="text-sm text-slate-600">Aucune activité ne correspond aux filtres.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Activité</th>
                        <th class="py-2 pr-4">Campagne / parcelle</th>
                        <th class="py-2 pr-4">Type</th>
                        <th class="py-2 pr-4">Date prévue</th>
                        <th class="py-2 pr-4">Assignée à</th>
                        <th class="py-2 pr-4">Statut</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($activities as $activity)
                        <tr class="{{ $activity->isLate() ? 'bg-amber-50' : '' }}">
                            <td class="py-2 pr-4">
                                <a href="{{ route('activities.show', $activity) }}" class="font-medium text-emerald-700 hover:underline">
                                    {{ $activity->title }}
                                </a>
                            </td>
                            <td class="py-2 pr-4 text-xs">
                                {{ $activity->campaign?->name ?? '—' }}
                                <span class="text-slate-400">({{ $activity->campaign?->productionUnit?->code ?? '—' }})</span>
                            </td>
                            <td class="py-2 pr-4">{{ $activity->type->label() }}</td>
                            <td class="py-2 pr-4">{{ $activity->planned_date->format('d/m/Y') }}</td>
                            <td class="py-2 pr-4 text-xs">{{ $activity->assignedTo?->name ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                <span class="rounded px-2 py-0.5 text-xs
                                    {{ $activity->status->value === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($activity->status->value === 'cancelled' ? 'bg-red-100 text-red-700' : ($activity->isLate() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) }}">
                                    {{ $activity->status->label() }}
                                </span>
                            </td>
                            <td class="py-2 text-right text-xs">
                                <a href="{{ route('activities.edit', $activity) }}" class="text-emerald-700 hover:underline">Modifier</a>
                                @if ($activity->status->value === 'planned')
                                    <form method="POST" action="{{ route('activities.complete', $activity) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="performed_at" value="{{ now()->toDateString() }}">
                                        <button type="submit" class="ml-3 text-emerald-700 hover:underline">Réaliser</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $activities->links() }}</div>
        @endif
    </x-card>
@endsection
