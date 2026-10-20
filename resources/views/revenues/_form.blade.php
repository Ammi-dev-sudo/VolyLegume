<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="category" label="Catégorie" required
                        :value="old('category', optional($revenue->category ?? null)->value)"
                        :options="$categories->mapWithKeys(fn ($category) => [$category->value => $category->label()])" />
        <x-select-field name="campaign_id" label="Campagne (facultatif)" placeholder="— Aucune —"
                        :value="old('campaign_id', $revenue->campaign_id ?? null)"
                        :options="$campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $campaign->name])" />
    </div>

    <x-select-field name="harvest_id" label="Récolte (facultatif)" placeholder="— Aucune —"
                    :value="old('harvest_id', $revenue->harvest_id ?? null)"
                    :options="$harvests->mapWithKeys(fn ($harvest) => [
                        $harvest->id => $harvest->harvested_at->format('d/m/Y') . ' — ' . number_format((float) $harvest->quantity, 2, ',', ' ') . ' ' . $harvest->unit . ' (' . $harvest->campaign?->name . ')',
                    ])"
                    hint="Lier la recette à une récolte renseigne automatiquement la campagne." />

    <x-field name="description" label="Description" required value="{{ old('description', $revenue->description ?? null) }}"
             placeholder="Vente marché central, vente détaillante…" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="amount" label="Montant (Ar)" type="number" step="0.01" min="0.01" required
                 value="{{ old('amount', $revenue->amount ?? null) }}" />
        <x-field name="revenue_date" label="Date" type="date" required
                 value="{{ old('revenue_date', isset($revenue) && $revenue->revenue_date ? $revenue->revenue_date->toDateString() : now()->toDateString()) }}" />
        <x-field name="reference" label="Référence / n° bon" value="{{ old('reference', $revenue->reference ?? null) }}" />
    </div>
</div>
