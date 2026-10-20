@extends('layouts.app')

@section('title', 'Nouveau mouvement de stock')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Nouveau mouvement de stock</h1>

        <x-card>
            <form method="POST" action="{{ route('stock-movements.store') }}" class="space-y-4">
                @csrf

                <x-select-field name="input_id" label="Intrant" required
                                :value="old('input_id', $selectedInput)"
                                :options="$inputs->mapWithKeys(fn ($input) => [$input->id => $input->name . ' — ' . number_format((float) $input->quantity, 3, ',', ' ') . ' ' . $input->base_unit->value])"
                                placeholder="— Choisir —" />

                <x-select-field name="type" label="Type de mouvement" required
                                :value="old('type', $selectedType)"
                                :options="$types->mapWithKeys(fn ($type) => [$type->value => $type->label()])"
                                hint="Entrées et stock initial augmentent le solde ; sorties et consommations le diminuent. L’ajustement accepte une valeur négative." />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="quantity" label="Quantité (signée pour un ajustement)" type="number" step="0.001" required
                             value="{{ old('quantity') }}" />
                    <x-field name="occurred_at" label="Date du mouvement" type="date"
                             value="{{ old('occurred_at', now()->toDateString()) }}" />
                </div>

                <x-select-field name="campaign_id" label="Campagne (facultatif)" placeholder="— Aucune —"
                                :value="old('campaign_id')"
                                :options="$campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $campaign->name])" />

                <x-field name="reason" label="Motif / référence" required value="{{ old('reason') }}"
                         placeholder="Achat chez fournisseur, consommation arrosage…" />

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer
                    </button>
                    <a href="{{ route('stock-movements.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
