<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="code" label="Code (ex. FERT-01)" required value="{{ old('code', $input->code ?? null) }}" />
        <x-field name="name" label="Nom de l’intrant" required value="{{ old('name', $input->name ?? null) }}" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="category" label="Catégorie" required
                        :value="old('category', optional($input->category ?? null)->value)"
                        :options="$categories->mapWithKeys(fn ($category) => [$category->value => $category->label()])" />
        <x-select-field name="base_unit" label="Unité de base" required
                        :value="old('base_unit', optional($input->base_unit ?? null)->value)"
                        :options="$units->mapWithKeys(fn ($unit) => [$unit->value => $unit->label()])" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="minimum_stock" label="Seuil d’alerte (stock minimal)" type="number" step="0.001" min="0" required
                 value="{{ old('minimum_stock', $input->minimum_stock ?? 0) }}" />
        @isset ($withInitialStock)
            <x-field name="initial_quantity" label="Stock initial (optionnel)" type="number" step="0.001" min="0"
                     value="{{ old('initial_quantity') }}"
                     hint="Enregistré comme mouvement « stock initial »." />
        @endisset
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="default_pre_harvest_days" label="Délai avant récolte (jours)" type="number" min="0"
                 value="{{ old('default_pre_harvest_days', $input->default_pre_harvest_days ?? null) }}"
                 hint="Obligatoire pour un produit phytosanitaire." />
        <x-field name="delay_source" label="Source du délai" value="{{ old('delay_source', $input->delay_source ?? null) }}"
                 placeholder="Notice fabricant, INRA…" />
    </div>

    <x-checkbox-field name="is_active" label="Intrant actif (proposable aux traitements et mouvements)"
                      :checked="old('is_active', (bool) ($input->is_active ?? true))" />
</div>
