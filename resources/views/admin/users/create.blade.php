@extends('layouts.app')

@section('title', 'Nouveau compte')

@section('content')
    @include('admin._nav')

    <div class="mb-4">
        <h1 class="text-xl font-semibold text-slate-800">Nouveau compte</h1>
        <p class="text-sm text-slate-500">Le compte peut être rattaché immédiatement à une exploitation.</p>
    </div>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nom</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Courriel</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
                    <input id="password" type="password" name="password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmation</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="farm_id" class="block text-sm font-medium text-slate-700">Exploitation (facultatif)</label>
                    <select id="farm_id" name="farm_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                        <option value="">Aucune</option>
                        @foreach ($farms as $farm)
                            <option value="{{ $farm->id }}" @selected((int) old('farm_id') === $farm->id)>{{ $farm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-slate-700">Rôle dans l’exploitation</label>
                    <select id="role" name="role" class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_platform_admin" value="1" @checked(old('is_platform_admin')) class="rounded border-slate-300 text-emerald-700">
                Administrateur de la plateforme
            </label>
            <div class="flex gap-3">
                <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Créer</button>
                <a href="{{ route('admin.users.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    </x-card>
@endsection
