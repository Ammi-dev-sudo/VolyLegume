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

    <x-select-field name="production_unit_zone_id" label="Zone de culture (facultatif)"
                    :value="old('production_unit_zone_id', $campaign->production_unit_zone_id ?? null)"
                    :options="$zones->mapWithKeys(fn ($zone) => [$zone->id => $zone->productionUnit?->code . ' · ' . $zone->code . ' — ' . $zone->name])"
                    placeholder="— Toute la parcelle —"
                    hint="La surface cultivée ne pourra pas dépasser la surface de la zone sélectionnée." />

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

    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="planting_density" label="Densité de plantation (facultatif)" type="number" step="0.01" min="0"
                 value="{{ old('planting_density', $campaign->planting_density ?? null) }}" />
        <x-field name="expected_yield_kg" label="Rendement attendu (kg, facultatif)" type="number" step="0.001" min="0"
                 value="{{ old('expected_yield_kg', $campaign->expected_yield_kg ?? null) }}" />
        <x-field name="actual_yield_kg" label="Rendement réel (kg, facultatif)" type="number" step="0.001" min="0"
                 value="{{ old('actual_yield_kg', $campaign->actual_yield_kg ?? null) }}" />
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-field name="planned_budget" label="Budget prévisionnel (Ar, facultatif)" type="number" step="0.01" min="0"
                 value="{{ old('planned_budget', $campaign->planned_budget ?? null) }}" />
        <x-field name="water_quota_m3" label="Quota eau (m³, facultatif)" type="number" step="0.001" min="0"
                 value="{{ old('water_quota_m3', $campaign->water_quota_m3 ?? null) }}"
                 hint="Paramètre d'exploitation, pas une recommandation." />
        <x-field name="water_alert_threshold_percent" label="Seuil d'alerte eau (%)" type="number" step="0.01" min="0" max="100"
                 value="{{ old('water_alert_threshold_percent', $campaign->water_alert_threshold_percent ?? 80) }}" />
        <div></div>
    </div>

    <x-textarea-field name="water_notes" label="Notes eau (facultatif)" rows="2" value="{{ old('water_notes', $campaign->water_notes ?? null) }}" />

    <x-textarea-field name="notes" label="Notes" rows="3" value="{{ old('notes', $campaign->notes ?? null) }}" />
</div>
