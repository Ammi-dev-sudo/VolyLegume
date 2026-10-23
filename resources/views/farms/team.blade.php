@extends('layouts.app')

@section('title', 'Équipe')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Équipe — {{ $farm->name }}</h1>
            <p class="text-sm text-slate-500">
                Rattachements et rôles.
                <a href="{{ route('resources.index') }}#roles" class="font-semibold text-emerald-700 hover:underline">Voir le détail des permissions</a>
            </p>
        </div>
        <a href="{{ route('farms.edit', $farm) }}"
           class="rounded border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
            Modifier l’exploitation
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" title="Membres">
            @if ($memberships->isEmpty())
                <p class="text-sm text-slate-600">Aucun membre rattaché.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Membre</th>
                            <th class="py-2 pr-4">Rôle</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($memberships as $membership)
                            <tr>
                                <td class="py-2 pr-4">
                                    <span class="font-medium">{{ $membership->user->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $membership->user->email }}</span>
                                </td>
                                <td class="py-2 pr-4">
                                    @if ((int) $farm->manager_id === (int) $membership->user_id)
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                            Responsable principal
                                        </span>
                                    @endif
                                    <form method="POST" action="{{ route('team.update', $membership) }}" class="mt-1 inline">
                                        @csrf
                                        @method('PUT')
                                        <select name="role" class="rounded border border-slate-300 px-2 py-1 text-xs"
                                                @disabled((int) $farm->manager_id === (int) $membership->user_id)>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($membership->role === $role)>
                                                    {{ $role->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="ml-1 text-xs text-emerald-700 hover:underline">OK</button>
                                    </form>
                                    @error('role')
                                        <span class="block text-xs text-red-600">{{ $message }}</span>
                                    @enderror
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="rounded px-2 py-0.5 text-xs {{ $membership->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $membership->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    @if ($membership->is_active)
                                        <form method="POST" action="{{ route('team.destroy', $membership) }}" class="inline"
                                              onsubmit="return confirm('Désactiver ce rattachement ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">Désactiver</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">Historique conservé</span>
                                    @endif
                                    @error('membership')
                                        <span class="block text-xs text-red-600">{{ $message }}</span>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Rattacher un compte" subtitle="Le compte doit déjà exister.">
            <form method="POST" action="{{ route('team.store') }}" class="space-y-4">
                @csrf
                <x-field name="email" label="Adresse e-mail du compte" type="email" required
                         placeholder="prenom.nom@exemple.mg" />
                <x-select-field name="role" label="Rôle" required
                                :options="$roles->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all()"
                                :value="old('role', \App\Enums\FarmRole::Agent->value)" />
                <button type="submit"
                        class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Rattacher
                </button>
            </form>
        </x-card>
    </div>
@endsection
