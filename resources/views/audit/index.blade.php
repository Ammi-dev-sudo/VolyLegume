@extends('layouts.app')

@section('title', 'Journal d’audit')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-slate-800">Journal d’audit</h1>
        <p class="text-sm text-slate-500">
            Traçabilité des opérations sensibles et des accès refusés pour l’exploitation active.
        </p>
    </div>

    <form method="GET" action="{{ route('audit.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="f-search" class="block text-xs font-medium text-slate-600">Recherche</label>
            <input type="search" id="f-search" name="search" value="{{ request('search') }}"
                   placeholder="Action ou description…" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="f-action" class="block text-xs font-medium text-slate-600">Action</label>
            <select id="f-action" name="action" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Toutes</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-user" class="block text-xs font-medium text-slate-600">Utilisateur</label>
            <select id="f-user" name="user_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((int) request('user_id') === $member->id)>{{ $member->name }}</option>
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
        <a href="{{ route('audit.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($logs->isEmpty())
            <p class="text-sm text-slate-600">Aucune entrée pour ces critères.</p>
        @else
            <div class="space-y-3">
                @foreach ($logs as $log)
                    <article class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{{ $log->action }}</span>
                                <span class="text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</span>
                                @if ($log->user)
                                    <span class="font-medium text-slate-700">{{ $log->user->name }}</span>
                                @else
                                    <span class="text-slate-400">Système</span>
                                @endif
                            </div>
                            @if ($log->auditable_type)
                                <span class="text-xs text-slate-400">
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-slate-700">{{ $log->description }}</p>
                        @if ($log->old_values || $log->new_values || $log->context)
                            <details class="mt-2 text-xs text-slate-500">
                                <summary class="cursor-pointer text-emerald-700">Détails techniques</summary>
                                <pre class="mt-2 overflow-x-auto rounded bg-slate-50 p-2">{{ json_encode(['avant' => $log->old_values, 'après' => $log->new_values, 'contexte' => $log->context], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="mt-4">{{ $logs->links() }}</div>
        @endif
    </x-card>
@endsection
