@extends('layouts.guest')

@section('title', 'Créer un compte')

@section('content')
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div class="animate-fade-in-up" style="animation-delay: 100ms">
            <label for="name" class="block text-sm font-medium text-slate-700">Nom et prénoms</label>
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.5" />
                        <path d="M5 20a7 7 0 0 1 14 0" stroke-linecap="round" />
                    </svg>
                </span>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                       placeholder="Rasoa Andrianina"
                       class="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-3 transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
            </div>
        </div>

        <div class="animate-fade-in-up" style="animation-delay: 160ms">
            <label for="email" class="block text-sm font-medium text-slate-700">Adresse e-mail</label>
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="M4 7l8 6 8-6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       placeholder="vous@exemple.mg"
                       class="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-3 transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
            </div>
        </div>

        <div class="grid animate-fade-in-up gap-4 sm:grid-cols-2" style="animation-delay: 220ms">
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
                <div class="relative mt-1">
                    <input id="password" name="password" type="password" required
                           class="w-full rounded-xl border border-slate-300 px-3 py-2.5 pr-10 transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                    <button type="button" data-toggle-password="password"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-emerald-700"
                            aria-label="Afficher ou masquer le mot de passe">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="2.8" />
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmation</label>
                <div class="relative mt-1">
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="w-full rounded-xl border border-slate-300 px-3 py-2.5 pr-10 transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none">
                    <button type="button" data-toggle-password="password_confirmation"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-emerald-700"
                            aria-label="Afficher ou masquer la confirmation">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="2.8" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <button type="submit"
                class="btn-shine w-full animate-fade-in-up rounded-xl bg-gradient-to-r from-emerald-700 via-emerald-600 to-emerald-700 bg-[length:200%_100%] px-4 py-2.5 font-semibold text-white shadow-lg shadow-emerald-700/20 transition hover:-translate-y-0.5 hover:bg-right hover:shadow-xl active:translate-y-0 active:scale-[0.99]"
                style="animation-delay: 280ms">
            Créer mon compte
        </button>

        <p class="animate-fade-in-up text-center text-sm text-slate-500" style="animation-delay: 340ms">
            Déjà inscrit ?
            <a href="{{ route('login') }}" class="font-semibold text-emerald-700 underline-offset-2 transition hover:text-emerald-800 hover:underline">Se connecter</a>
        </p>
    </form>
@endsection
