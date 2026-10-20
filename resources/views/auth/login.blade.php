@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Mot de passe</label>
            <input id="password" name="password" type="password" required
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-emerald-600 focus:outline-none">
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
            Se souvenir de moi
        </label>

        <button type="submit"
                class="w-full rounded bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">
            Se connecter
        </button>

        <p class="text-center text-sm text-slate-500">
            Pas encore de compte ?
            <a href="{{ route('register') }}" class="text-emerald-700 underline">Créer un compte</a>
        </p>
    </form>
@endsection
