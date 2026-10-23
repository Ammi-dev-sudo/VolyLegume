@extends('layouts.app')

@section('title', 'Administration de la plateforme')

@section('content')
    @include('admin._nav')

    <div class="mb-5">
        <h1 class="text-xl font-semibold text-slate-800">Administration de la plateforme</h1>
        <p class="text-sm text-slate-500">Supervision des comptes, des exploitations et des opérations sensibles.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Comptes', $counts['users'], $counts['active_users'].' actifs'],
            ['Administrateurs plateforme', $counts['platform_admins'], 'rôle global'],
            ['Exploitations', $counts['farms'], $counts['active_farms'].' actives'],
            ['Campagnes', $counts['campaigns'], $counts['activities'].' activités'],
            ['Stocks critiques', $counts['critical_inputs'], 'toutes exploitations'],
        ] as [$label, $value, $hint])
            <x-card>
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($value, 0, ',', ' ') }}</p>
                <p class="text-xs text-slate-400">{{ $hint }}</p>
            </x-card>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">Derniers événements d’audit</h2>
                <a href="{{ route('admin.audit.index') }}" class="text-sm text-emerald-700 hover:underline">Tout voir</a>
            </div>
            <div class="divide-y divide-slate-100 text-sm">
                @forelse ($recentLogs as $log)
                    <div class="py-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-mono text-xs text-slate-600">{{ $log->action }}</span>
                            <span class="text-xs text-slate-400">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="text-slate-700">{{ $log->description }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $log->user?->name ?? 'Système' }}
                            @if ($log->farm) · {{ $log->farm->name }} @endif
                        </p>
                    </div>
                @empty
                    <p class="py-2 text-slate-500">Aucun événement enregistré.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h2 class="mb-3 font-semibold text-slate-800">Comptes récents</h2>
            <div class="space-y-3 text-sm">
                @foreach ($recentUsers as $user)
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('admin.users.show', $user) }}" class="truncate font-medium text-emerald-700 hover:underline">{{ $user->name }}</a>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                        <span class="shrink-0 rounded px-1.5 py-0.5 text-xs {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                            {{ $user->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
@endsection
