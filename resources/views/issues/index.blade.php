@extends('layouts.app')

@section('title', 'Problèmes agricoles')

@php
    $severityStyles = [
        'low' => 'bg-slate-100 text-slate-700',
        'moderate' => 'bg-amber-100 text-amber-800',
        'high' => 'bg-orange-100 text-orange-800',
        'critical' => 'bg-red-100 text-red-800',
    ];
    $statusStyles = [
        'open' => 'bg-red-50 text-red-700',
        'in_progress' => 'bg-sky-50 text-sky-700',
        'monitoring' => 'bg-amber-50 text-amber-700',
        'resolved' => 'bg-emerald-50 text-emerald-700',
    ];
@endphp

@section('content')
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-emerald-950 to-amber-900 px-5 py-6 text-white shadow-xl shadow-slate-950/15 animate-fade-in-down sm:px-8 sm:py-8">
        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-amber-300/20 blur-3xl animate-blob"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/4 h-56 w-56 rounded-full bg-rose-300/15 blur-3xl animate-blob" style="animation-delay: -7s"></div>
        <div class="relative flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="animate-fade-in-up text-xs font-semibold uppercase tracking-[0.2em] text-amber-100/75">Observations de terrain</p>
                <h1 class="mt-2 animate-fade-in-up text-2xl font-bold sm:text-3xl" style="animation-delay: 70ms">Problèmes agricoles</h1>
                <p class="mt-2 animate-fade-in-up text-sm text-emerald-50/80" style="animation-delay: 140ms">Suivez les observations, leur gravité et leur résolution au fil des visites.</p>
            </div>
            @can('create', \App\Models\CropIssue::class)
                <a href="{{ route('issues.create') }}" class="btn-shine relative rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">+ Signaler un problème</a>
            @endcan
        </div>
        @if ($summary['critical'] > 0)
            <div class="relative mt-5 inline-flex animate-fade-in-up items-center gap-2 rounded-xl border border-rose-200/30 bg-rose-400/15 px-3 py-2 text-sm text-rose-50" role="status" style="animation-delay: 210ms">
                <span class="h-2 w-2 rounded-full bg-rose-300 animate-pulse-soft"></span>
                {{ $summary['critical'] }} problème(s) critique(s) demandent une attention prioritaire.
            </div>
        @endif
    </section>

    <div class="dashboard-grid mb-5" data-reveal-group="40">
        <x-stat-card label="Problèmes ouverts" :value="$summary['open']" :count="$summary['open']" hint="Non résolus" />
        <x-stat-card label="Critiques" :value="$summary['critical']" :count="$summary['critical']" hint="Intervention urgente" :tone="$summary['critical'] > 0 ? 'danger' : 'default'" />
        <x-stat-card label="Résolus" :value="$summary['resolved']" :count="$summary['resolved']" hint="Historique" tone="info" />
        <x-stat-card label="Total" :value="$summary['total']" :count="$summary['total']" hint="Toutes périodes" />
    </div>

    <form method="GET" action="{{ route('issues.index') }}" data-reveal class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm shadow-sm">
        <div>
            <label for="f-status" class="block text-xs font-medium text-slate-600">Statut</label>
            <select id="f-status" name="status" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="active" @selected($status === 'active')>Non résolus</option>
                <option value="all" @selected($status === 'all')>Tous</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-type" class="block text-xs font-medium text-slate-600">Type</label>
            <select id="f-type" name="type" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}" @selected(request('type') === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-severity" class="block text-xs font-medium text-slate-600">Gravité</label>
            <select id="f-severity" name="severity" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Toutes</option>
                @foreach ($severities as $option)
                    <option value="{{ $option->value }}" @selected(request('severity') === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-unit" class="block text-xs font-medium text-slate-600">Parcelle</label>
            <select id="f-unit" name="production_unit_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Toutes</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((string) request('production_unit_id') === (string) $unit->id)>{{ $unit->code }} — {{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[12rem] flex-1">
            <label for="f-q" class="block text-xs font-medium text-slate-600">Recherche</label>
            <input id="f-q" name="q" value="{{ request('q') }}" placeholder="Référence, titre, description…"
                   class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
        </div>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('issues.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($issues->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                <p class="font-semibold text-slate-700">Aucun problème pour ces filtres</p>
                <p class="mt-1 text-sm text-slate-500">Signalez une maladie, un nuisible ou un stress hydrique pour déclencher le suivi.</p>
                @can('create', \App\Models\CropIssue::class)
                    <a href="{{ route('issues.create') }}" class="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                        Signaler un problème &rarr;
                    </a>
                @endcan
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Référence</th>
                            <th class="py-2 pr-4">Problème</th>
                            <th class="py-2 pr-4">Parcelle / culture</th>
                            <th class="py-2 pr-4">Gravité</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 pr-4">Observé le</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($issues as $issue)
                            <tr data-reveal class="align-top transition-colors duration-200 hover:bg-emerald-50/60">
                                <td class="py-2 pr-4 font-mono text-xs">{{ $issue->reference }}</td>
                                <td class="py-2 pr-4">
                                    <a href="{{ route('issues.show', $issue) }}" class="font-medium text-emerald-700 hover:underline">
                                        {{ $issue->title }}
                                    </a>
                                    <p class="text-xs text-slate-500">{{ $issue->type->label() }} · {{ $issue->photos_count }} photo(s)</p>
                                </td>
                                <td class="py-2 pr-4 text-xs">
                                    {{ $issue->productionUnit?->code }} — {{ $issue->productionUnit?->name }}
                                    @if ($issue->campaign)
                                        <p class="text-slate-500">{{ $issue->campaign->name }}</p>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $severityStyles[$issue->severity->value] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $issue->severity->label() }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $statusStyles[$issue->status->value] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $issue->status->label() }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-xs">{{ $issue->observed_at?->format('d/m/Y') }}</td>
                                <td class="py-2 text-right text-xs">
                                    <a href="{{ route('issues.show', $issue) }}" class="text-emerald-700 hover:underline">Détails</a>
                                    @can('update', $issue)
                                        <a href="{{ route('issues.edit', $issue) }}" class="ml-3 text-emerald-700 hover:underline">Modifier</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $issues->links() }}</div>
        @endif
    </x-card>
@endsection
