@extends('layouts.app')

@section('title', 'Modifier la parcelle')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Modifier la parcelle « {{ $productionUnit->name }} »</h1>

        <x-card>
            <form method="POST" action="{{ route('production-units.update', $productionUnit) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-field name="code" label="Code (ex. P01)" required value="{{ old('code', $productionUnit->code) }}" />
                <x-field name="name" label="Nom de la parcelle" required value="{{ old('name', $productionUnit->name) }}" />
                <x-field name="area_m2" label="Superficie (m²)" type="number" step="0.01" min="0.01" required
                         value="{{ old('area_m2', $productionUnit->area_m2) }}" />
                <x-field name="location" label="Localisation (repère, rangée)" required
                         value="{{ old('location', $productionUnit->location) }}" />
                <x-select-field name="status" label="Statut" required
                                :options="collect($productionUnit->status::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])"
                                :value="old('status', $productionUnit->status->value)" />

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Mettre à jour
                    </button>
                    <a href="{{ route('production-units.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
