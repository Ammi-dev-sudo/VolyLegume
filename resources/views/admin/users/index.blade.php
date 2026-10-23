@extends('layouts.app')

@section('title', 'Comptes & rôles')

@section('content')
    @include('admin._nav')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Comptes &amp; rôles</h1>
            <p class="text-sm text-slate-500">Gestion globale des comptes et des rattachements aux exploitations.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Nouveau compte
        </a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="f-search" class="block text-xs font-medium text-slate-600">Recherche</label>
            <input type="search" id="f-search" name="search" value="{{ request('search') }}"
                   placeholder="Nom ou courriel…" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="f-status" class="block text-xs font-medium text-slate-600">Statut</label>
            <select id="f-status" name="status" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                <option value="active" @selected(request('status') === 'active')>Actifs</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactifs</option>
            </select>
        </div>
        <label class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="admins" value="1" @checked(request()->boolean('admins')) class="rounded border-slate-300 text-emerald-700">
            Administrateurs plateforme
        </label>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('admin.users.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-4">Compte</th>
                    <th class="py-2 pr-4">Exploitations</th>
                    <th class="py-2 pr-4">Plateforme</th>
                    <th class="py-2 pr-4">Statut</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="py-2 pr-4">
                            <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-emerald-700 hover:underline">{{ $user->name }}</a>
                            <p class="text-xs text-slate-500">{{ $user->email }}</p>
                        </td>
                        <td class="py-2 pr-4">{{ $user->memberships_count }}</td>
                        <td class="py-2 pr-4">
                            @if ($user->is_platform_admin)
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Admin</span>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4">
                            <span class="rounded px-2 py-0.5 text-xs {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                {{ $user->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="py-2 text-right text-xs">
                            <form method="POST" action="{{ route('admin.users.status', $user) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-slate-600 hover:underline">
                                    {{ $user->is_active ? 'Désactiver' : 'Réactiver' }}
                                </button>
                            </form>
                            <a href="{{ route('admin.users.show', $user) }}" class="ml-3 text-emerald-700 hover:underline">Gérer</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $users->links() }}</div>
    </x-card>
@endsection
