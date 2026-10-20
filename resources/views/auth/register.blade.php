@extends('layouts.guest')

@section('title', 'Créer un compte')

@section('content')
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium">Nom et prénoms</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Mot de passe</label>
            <input id="password" name="password" type="password" required
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmation</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
        </div>

        <button type="submit"
                class="w-full rounded bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">
            Créer mon compte
        </button>

        <p class="text-center text-sm text-slate-500">
            Déjà inscrit ?
            <a href="{{ route('login') }}" class="text-emerald-700 underline">Se connecter</a>
        </p>
    </form>
@endsection
