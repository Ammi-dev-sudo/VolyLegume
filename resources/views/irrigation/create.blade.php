@extends('layouts.app')

@section('title', 'Nouvel arrosage')

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-slate-800">Enregistrer un arrosage</h1>
            <a href="{{ route('irrigation.index') }}" class="text-sm text-slate-500 underline">Retour</a>
        </div>

        <x-card>
            <form method="POST" action="{{ route('irrigation.store') }}" class="space-y-4">
                @csrf
                <x-select-field name="campaign_id" label="Campagne" required
                                :options="$campaigns->mapWithKeys(fn ($c) => [$c->id => $c->name.' — '.$c->productionUnit?->code])"
                                :value="old('campaign_id')" />
                <x-select-field name="production_unit_id" label="Parcelle" required
                                :options="$productionUnits->mapWithKeys(fn ($u) => [$u->id => $u->code.' — '.$u->name])"
                                :value="old('production_unit_id')" />
                <x-field name="irrigated_at" label="Date d'arrosage" type="date" required value="{{ old('irrigated_at', now()->toDateString()) }}" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="volume_m3" label="Volume (m³)" type="number" step="0.001" min="0.001" required value="{{ old('volume_m3') }}" />
                    <x-field name="duration_hours" label="Durée (heures, facultatif)" type="number" step="0.01" min="0.01" value="{{ old('duration_hours') }}" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="method" label="Méthode (facultatif)" value="{{ old('method') }}" placeholder="goutte-à-goutte, aspersion..." />
                    <x-field name="source" label="Source (facultatif)" value="{{ old('source') }}" placeholder="forage, puits, réservoir..." />
                </div>
                <x-textarea-field name="notes" label="Notes" rows="3" value="{{ old('notes') }}" />

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Enregistrer</button>
                    <a href="{{ route('irrigation.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
