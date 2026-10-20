@extends('layouts.app')

@section('title', 'Modifier l’exploitation')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Modifier « {{ $farm->name }} »</h1>

        <x-card>
            <form method="POST" action="{{ route('farms.update', $farm) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-field name="name" label="Nom de l’exploitation" required value="{{ old('name', $farm->name) }}" />
                <x-field name="location" label="Localisation" required value="{{ old('location', $farm->location) }}" />
                <x-field name="area_m2" label="Superficie totale (m²)" type="number" min="1" step="0.01" required
                         value="{{ old('area_m2', $farm->area_m2) }}" />

                <x-select-field name="manager_id" label="Responsable principal" placeholder="— Aucun —"
                                :options="$managers->mapWithKeys(fn ($membership) => [$membership->user_id => $membership->user->name])->all()"
                                :value="old('manager_id', $farm->manager_id)"
                                hint="Choisir un membre actif avec le rôle Responsable. Ajoutez d’abord un membre depuis la page Équipe si nécessaire." />

                <x-select-field name="status" label="Statut" required
                                :options="$statuses->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()"
                                :value="old('status', $farm->status->value)"
                                hint="L’activation exige un responsable principal rattaché et actif." />

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer
                    </button>
                    <a href="{{ route('farms.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
