@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <x-card title="Bienvenue sur VolyLegume">
        <p class="text-slate-600">
            Vous n’êtes rattaché à aucune exploitation active. Créez votre exploitation pour
            démarrer la gestion de vos parcelles, campagnes et stocks.
        </p>
        <a href="{{ route('farms.create') }}"
           class="mt-4 inline-block rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Créer une exploitation
        </a>
    </x-card>
@endsection
