@extends('layouts.app')

@section('title', 'Compte '.$user->name)

@section('content')
    @include('admin._nav')

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ $user->name }}</h1>
            <p class="text-sm text-slate-500">{{ $user->email }}</p>
            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                <span class="rounded px-2 py-0.5 {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                    {{ $user->is_active ? 'Compte actif' : 'Compte inactif' }}
                </span>
                @if ($user->is_platform_admin)
                    <span class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">Administrateur plateforme</span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.users.status', $user) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    {{ $user->is_active ? 'Désactiver le compte' : 'Réactiver le compte' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.users.platform-admin', $user) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 hover:bg-amber-100">
                    {{ $user->is_platform_admin ? 'Retirer le rôle plateforme' : 'Nommer administrateur plateforme' }}
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 font-semibold text-slate-800">Rattachements aux exploitations</h2>
            <div class="space-y-4">
                @forelse ($user->memberships as $membership)
                    <form method="POST" action="{{ route('admin.users.memberships.update', [$user, $membership]) }}"
                          class="rounded-xl border border-slate-200 p-3">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <a href="{{ route('admin.farms.show', $membership->farm_id) }}" class="font-medium text-emerald-700 hover:underline">
                                {{ $membership->farm?->name ?? 'Exploitation #'.$membership->farm_id }}
                            </a>
                            <span class="text-xs text-slate-400">#{{ $membership->id }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-end gap-2 text-sm">
                            <div>
                                <label class="block text-xs text-slate-600">Rôle</label>
                                <select name="role" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($membership->role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="flex items-center gap-2 pb-1.5 text-xs">
                                <input type="checkbox" name="is_active" value="1" @checked($membership->is_active) class="rounded border-slate-300 text-emerald-700">
                                Actif
                            </label>
                            <button type="submit" class="rounded bg-slate-700 px-3 py-1.5 text-xs text-white hover:bg-slate-800">Enregistrer</button>
                        </div>
                    </form>
                @empty
                    <p class="text-sm text-slate-500">Aucun rattachement.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h2 class="mb-3 font-semibold text-slate-800">Rattacher à une exploitation</h2>
            <form method="POST" action="{{ route('admin.users.memberships.store', $user) }}" class="space-y-3 text-sm">
                @csrf
                <div>
                    <label for="farm_id" class="block text-xs text-slate-600">Exploitation</label>
                    <select id="farm_id" name="farm_id" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}">{{ $farm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="role" class="block text-xs text-slate-600">Rôle</label>
                    <select id="role" name="role" required class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-xs">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-emerald-700">
                    Rattachement actif
                </label>
                <button type="submit" class="rounded bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">Rattacher</button>
            </form>
        </x-card>
    </div>
@endsection
