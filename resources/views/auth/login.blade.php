@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div class="animate-fade-in-up" style="animation-delay: 120ms">
            <label for="email" class="block text-sm font-medium text-slate-700">Adresse e-mail</label>
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="M4 7l8 6 8-6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       placeholder="vous@exemple.mg"
                       class="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-3 transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
            </div>
        </div>

        <div class="animate-fade-in-up" style="animation-delay: 180ms">
            <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect x="4" y="10" width="16" height="10" rx="2" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round" />
                    </svg>
                </span>
                <input id="password" name="password" type="password" required
                       placeholder="••••••••"
                       class="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-12 transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                <button type="button" data-toggle-password="password"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 transition hover:text-emerald-700"
                        aria-label="Afficher ou masquer le mot de passe">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="2.8" />
                    </svg>
                </button>
            </div>
        </div>

        <label class="flex animate-fade-in-up items-center gap-2 text-sm text-slate-600" style="animation-delay: 240ms">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Se souvenir de moi
        </label>

        <button type="submit"
                class="btn-shine w-full animate-fade-in-up rounded-xl bg-gradient-to-r from-emerald-700 via-emerald-600 to-emerald-700 bg-[length:200%_100%] px-4 py-2.5 font-semibold text-white shadow-lg shadow-emerald-700/20 transition hover:-translate-y-0.5 hover:bg-right hover:shadow-xl active:translate-y-0 active:scale-[0.99]"
                style="animation-delay: 300ms">
            Se connecter
        </button>

        <p class="animate-fade-in-up text-center text-sm text-slate-500" style="animation-delay: 360ms">
            Pas encore de compte ?
            <a href="{{ route('register') }}" class="font-semibold text-emerald-700 underline-offset-2 transition hover:text-emerald-800 hover:underline">Créer un compte</a>
        </p>
    </form>
@endsection
