@extends('layouts.app')

@section('title', $farm->name)

@section('content')
    @include('admin._nav')

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ $farm->name }}</h1>
            <p class="text-sm text-slate-500">{{ $farm->location }} · {{ $farm->type }} · {{ number_format((float) $farm->area_m2, 0, ',', ' ') }} m²</p>
            <span class="mt-1 inline-block rounded px-2 py-0.5 text-xs {{ $farm->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                {{ $farm->status->label() }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.farms.status', $farm->id) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                {{ $farm->isActive() ? 'Désactiver l’exploitation' : 'Activer l’exploitation' }}
            </button>
        </form>
    </div>

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Membres', $farm->memberships_count],
            ['Parcelles', $farm->production_units_count],
            ['Campagnes', $farm->campaigns_count],
            ['Activités', $farm->activities_count],
            ['Récoltes', $farm->harvests_count],
        ] as [$label, $value])
            <x-card>
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($value, 0, ',', ' ') }}</p>
            </x-card>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 font-semibold text-slate-800">Équipe</h2>
            <div class="divide-y divide-slate-100 text-sm">
                @foreach ($farm->memberships as $membership)
                    <div class="flex items-center justify-between gap-2 py-2">
                        <div>
                            <a href="{{ route('admin.users.show', $membership->user) }}" class="font-medium text-emerald-700 hover:underline">
                                {{ $membership->user?->name ?? 'Compte supprimé' }}
                            </a>
                            <p class="text-xs text-slate-500">{{ $membership->user?->email }}</p>
                        </div>
                        <div class="text-right text-xs">
                            <span class="rounded bg-slate-100 px-2 py-0.5">{{ $membership->role->label() }}</span>
                            <p class="mt-1 {{ $membership->is_active ? 'text-emerald-700' : 'text-slate-400' }}">
                                {{ $membership->is_active ? 'Actif' : 'Inactif' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card>
            <h2 class="mb-3 font-semibold text-slate-800">Derniers événements</h2>
            <div class="divide-y divide-slate-100 text-sm">
                @forelse ($logs as $log)
                    <div class="py-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs text-slate-600">{{ $log->action }}</span>
                            <span class="text-xs text-slate-400">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="text-slate-700">{{ $log->description }}</p>
                    </div>
                @empty
                    <p class="text-slate-500">Aucun événement.</p>
                @endforelse
            </div>
        </x-card>
    </div>
@endsection
