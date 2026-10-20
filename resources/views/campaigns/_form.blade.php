<div class="space-y-4">
    <x-field name="name" label="Nom de la campagne" required value="{{ old('name', $campaign->name ?? null) }}"
             placeholder="Tomate P01 — S1" />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="production_unit_id" label="Parcelle" required
                        :value="old('production_unit_id', $campaign->production_unit_id ?? null)"
                        :options="$productionUnits->mapWithKeys(fn ($unit) => [$unit->id => $unit->code . ' — ' . $unit->name])"
                        placeholder="— Choisir —" />
        <x-select-field name="crop_id" label="Culture" required
                        :value="old('crop_id', $campaign->crop_id ?? null)"
                        :options="$crops->mapWithKeys(fn ($crop) => [$crop->id => $crop->name . ' (' . $crop->variety . ')'])"
                        placeholder="— Choisir —" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="start_date" label="Date de référence" type="date" required
                 value="{{ old('start_date', isset($campaign) && $campaign->start_date ? $campaign->start_date->toDateString() : now()->toDateString()) }}" />
        <x-field name="expected_end_date" label="Fin prévue" type="date" required
                 value="{{ old('expected_end_date', isset($campaign) && $campaign->expected_end_date ? $campaign->expected_end_date->toDateString() : null) }}" />
        <x-field name="actual_end_date" label="Fin réelle" type="date"
                 value="{{ old('actual_end_date', isset($campaign) && $campaign->actual_end_date ? $campaign->actual_end_date->toDateString() : null) }}" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-select-field name="reference_type" label="Type de référence" required
                        :value="old('reference_type', optional($campaign->reference_type ?? null)->value)"
                        :options="$referenceTypes->mapWithKeys(fn ($type) => [$type->value => $type->label()])"
                        hint="Convention : la date de référence est la date de semis ou de plantation." />
        <x-field name="cultivated_area_m2" label="Superficie cultivée (m²)" type="number" step="0.01" min="0.01" required
                 value="{{ old('cultivated_area_m2', $campaign->cultivated_area_m2 ?? null) }}" />
        <x-select-field name="status" label="Statut" required
                        :value="old('status', optional($campaign->status ?? null)->value ?? \App\Enums\CampaignStatus::Planned->value)"
                        :options="$statuses->mapWithKeys(fn ($status) => [$status->value => $status->label()])" />
    </div>

    <x-textarea-field name="notes" label="Notes" rows="3" value="{{ old('notes', $campaign->notes ?? null) }}" />
</div>
