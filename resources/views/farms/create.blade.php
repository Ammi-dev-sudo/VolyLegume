@extends('layouts.app')

@section('title', 'Créer une exploitation')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Créer une exploitation</h1>

        <x-card>
            <form method="POST" action="{{ route('farms.store') }}" class="space-y-4">
                @csrf
                <x-field name="name" label="Nom de l’exploitation" required placeholder="Ferme maraîchère ..." />
                <x-field name="location" label="Localisation" required placeholder="Quartier, ville" />
                <x-field name="area_m2" label="Superficie totale (m²)" type="number" step="0.01" min="0.01" required />

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Créer et activer
                    </button>
                    <a href="{{ route('farms.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        Annuler
                    </a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
