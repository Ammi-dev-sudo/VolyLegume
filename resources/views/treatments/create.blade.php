@extends('layouts.app')

@section('title', 'Nouveau traitement')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Nouveau traitement phytosanitaire</h1>

        <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            L’enregistrement est atomique : activité, mouvement de stock et traitement sont créés ensemble.
            La borne de récolte est calculée côté serveur.
        </div>

        <x-card>
            <form method="POST" action="{{ route('treatments.store') }}" class="space-y-4">
                @csrf

                <x-select-field name="campaign_id" label="Campagne" required
                                :value="old('campaign_id', $selectedCampaign)"
                                :options="$campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $campaign->name])"
                                placeholder="— Choisir —" />

                <x-select-field name="input_id" label="Produit phytosanitaire" required
                                :value="old('input_id')"
                                :options="$phiInputs->mapWithKeys(fn ($input) => [$input->id => $input->name . ' (' . number_format((float) $input->quantity, 3, ',', ' ') . ' ' . $input->base_unit->value . ')'])"
                                placeholder="— Choisir —"
                                hint="Seuls les produits avec délai renseigné sont proposés." />

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="quantity" label="Quantité consommée" type="number" step="0.001" min="0.001" required
                             value="{{ old('quantity') }}" />
                    <x-field name="dose_value" label="Dose" type="number" step="0.001" min="0.001" required
                             value="{{ old('dose_value') }}" />
                    <x-field name="dose_unit" label="Unité de dose" required value="{{ old('dose_unit', 'L/ha') }}" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="applied_at" label="Date et heure d’application" type="datetime-local" required
                             value="{{ old('applied_at', now()->format('Y-m-d\TH:i')) }}" />
                    <x-field name="pre_harvest_days" label="Délai avant récolte (jours)" type="number" min="0" required
                             value="{{ old('pre_harvest_days') }}"
                             hint="Pré-rempli depuis la fiche de l’intrant." />
                </div>

                <x-field name="delay_source" label="Source du délai" value="{{ old('delay_source') }}"
                         placeholder="Notice, INRA…" />

                <x-textarea-field name="notes" label="Observations" rows="3" value="{{ old('notes') }}" />

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer le traitement
                    </button>
                    <a href="{{ route('treatments.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>

    <script>
        const phi = @json($phiInputs->mapWithKeys(fn ($input) => [$input->id => [
            'days' => $input->default_pre_harvest_days,
            'source' => $input->delay_source,
        ]]));
        const select = document.querySelector('select[name="input_id"]');
        const days = document.querySelector('input[name="pre_harvest_days"]');
        const source = document.querySelector('input[name="delay_source"]');
        if (select) {
            select.addEventListener('change', function () {
                const data = phi[this.value];
                if (data) {
                    if (!days.value) days.value = data.days;
                    if (!source.value) source.value = data.source;
                }
            });
        }
    </script>
@endsection
