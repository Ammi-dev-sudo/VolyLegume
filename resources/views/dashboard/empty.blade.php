@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <x-card>
        <div class="flex flex-col items-center gap-6 py-6 text-center sm:flex-row sm:text-left">
            <div class="relative shrink-0">
                <div class="absolute inset-3 rounded-full bg-emerald-300/30 blur-2xl animate-pulse-soft"></div>
                <img src="{{ asset('images/logo.png') }}" alt="Logo VolyLegume"
                     class="float-slow relative h-28 w-28 rounded-2xl bg-emerald-50 p-2 ring-1 ring-emerald-100">
            </div>
            <div>
                <h1 class="text-xl font-semibold text-emerald-900">Bienvenue sur VolyLegume</h1>
                <p class="mt-2 text-slate-600">
                    Vous n’êtes rattaché à aucune exploitation active. Créez votre exploitation pour
                    démarrer la gestion de vos parcelles, campagnes et stocks.
                </p>
                <div class="mt-4 flex flex-wrap justify-center gap-2 sm:justify-start">
                    <a href="{{ route('farms.create') }}"
                       class="btn-shine inline-block rounded-xl bg-gradient-to-r from-emerald-700 to-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                        Créer une exploitation
                    </a>
                    <a href="{{ route('resources.index') }}"
                       class="inline-block rounded-xl border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:-translate-y-0.5 hover:bg-emerald-50">
                        Voir les ressources
                    </a>
                </div>
            </div>
        </div>
    </x-card>
@endsection
