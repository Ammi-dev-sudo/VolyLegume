@extends('layouts.app')

@section('title', 'Modifier la culture')

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold text-slate-800">Modifier « {{ $crop->name }} »</h1>
            <a href="{{ route('crops.guide', $crop) }}" class="text-sm text-emerald-700 hover:underline">
                Voir la fiche de culture
            </a>
        </div>

        <x-card>
            <form method="POST" action="{{ route('crops.update', $crop) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-select-field name="category_id" label="Catégorie" required
                                :options="$categories->pluck('name', 'id')"
                                placeholder="— Choisir une catégorie —"
                                :value="$crop->category_id" />
                <x-field name="name" label="Nom de la culture" required value="{{ old('name', $crop->name) }}" />
                <x-field name="variety" label="Variété" value="{{ old('variety', $crop->variety) }}" />
                <x-field name="estimated_cycle_days" label="Cycle estimé (jours)" type="number" min="1"
                         value="{{ old('estimated_cycle_days', $crop->estimated_cycle_days) }}" />
                <x-checkbox-field name="is_active" label="Culture active (proposable aux campagnes)"
                                  :checked="old('is_active', (bool) $crop->is_active)" />

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Mettre à jour
                    </button>
                    <a href="{{ route('crops.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
