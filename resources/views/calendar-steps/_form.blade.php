<div class="space-y-4">
    <x-select-field name="crop_id" label="Culture" required :value="old('crop_id', $step->crop_id)"
                    :options="$crops->mapWithKeys(fn ($crop) => [$crop->id => $crop->name])"
                    placeholder="— Choisir une culture —" />

    <x-field name="name" label="Intitulé de l’étape" required value="{{ old('name', $step->name) }}"
             placeholder="Semis, repiquage, désherbage…" />

    <x-select-field name="activity_type" label="Type d’activité" required
                    :value="old('activity_type', optional($step->activity_type)->value ?? null)"
                    :options="collect(\App\Enums\ActivityType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])" />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="offset_days" label="Décalage depuis la date de référence (jours)" type="number"
                 required value="{{ old('offset_days', $step->offset_days ?? 0) }}"
                 hint="0 pour le jour même (semis/plantation)." />
        <x-field name="position" label="Position dans le calendrier" type="number" min="1" required
                 value="{{ old('position', $step->position ?? 1) }}" />
    </div>

    <x-textarea-field name="description" label="Description / consignes" rows="3"
                      value="{{ old('description', $step->description ?? null) }}" />

    <x-checkbox-field name="is_active" label="Étape active (prise en compte à la génération)"
                      :checked="old('is_active', (bool) ($step->is_active ?? true))" />
</div>
