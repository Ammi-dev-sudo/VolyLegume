@extends('layouts.app')

@section('title', 'Modifier la parcelle')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Modifier la parcelle « {{ $productionUnit->name }} »</h1>

        <x-card>
            <form method="POST" action="{{ route('production-units.update', $productionUnit) }}" class="space-y-4" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <x-field name="code" label="Code (ex. P01)" required value="{{ old('code', $productionUnit->code) }}" />
                <x-field name="name" label="Nom de la parcelle" required value="{{ old('name', $productionUnit->name) }}" />
                <x-field name="area_m2" label="Superficie (m²)" type="number" step="0.01" min="0.01" required
                         value="{{ old('area_m2', $productionUnit->area_m2) }}" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="length_m" label="Longueur (m, facultatif)" type="number" step="0.01" min="0.01"
                             value="{{ old('length_m', $productionUnit->length_m) }}" />
                    <x-field name="width_m" label="Largeur (m, facultatif)" type="number" step="0.01" min="0.01"
                             value="{{ old('width_m', $productionUnit->width_m) }}" />
                </div>
                <p class="-mt-2 text-xs text-slate-500">Les dimensions servent à dessiner le plan ; elles restent indicatives.</p>
                <x-field name="location" label="Localisation (repère, rangée)" required
                         value="{{ old('location', $productionUnit->location) }}" />
                <x-select-field name="status" label="Statut" required
                                :options="collect($productionUnit->status::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])"
                                :value="old('status', $productionUnit->status->value)" />

                @if ($productionUnit->photo_path)
                    <div>
                        <p class="text-sm font-medium text-slate-700">Photo actuelle</p>
                        <img src="{{ asset('storage/'.$productionUnit->photo_path) }}" alt="Photo de {{ $productionUnit->name }}" class="mt-2 h-40 rounded border border-slate-200">
                        <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300">
                            Supprimer cette photo
                        </label>
                    </div>
                @endif

                <div>
                    <label for="photo" class="block text-sm font-medium text-slate-700">{{ $productionUnit->photo_path ? 'Remplacer la photo' : 'Photo de la parcelle (facultatif)' }}</label>
                    <input id="photo" name="photo" type="file" accept="image/*"
                           class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG, WebP jusqu'à 5 Mo.</p>
                </div>

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
