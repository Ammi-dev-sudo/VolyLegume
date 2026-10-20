<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="category" label="Catégorie" required
                        :value="old('category', optional($expense->category ?? null)->value)"
                        :options="$categories->mapWithKeys(fn ($category) => [$category->value => $category->label()])" />
        <x-select-field name="campaign_id" label="Campagne (facultatif)" placeholder="— Aucune —"
                        :value="old('campaign_id', $expense->campaign_id ?? null)"
                        :options="$campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $campaign->name])" />
    </div>

    <x-field name="description" label="Description" required value="{{ old('description', $expense->description ?? null) }}"
             placeholder="Achat d’engrais, journée de travail…" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="amount" label="Montant (Ar)" type="number" step="0.01" min="0.01" required
                 value="{{ old('amount', $expense->amount ?? null) }}" />
        <x-field name="expense_date" label="Date" type="date" required
                 value="{{ old('expense_date', isset($expense) && $expense->expense_date ? $expense->expense_date->toDateString() : now()->toDateString()) }}" />
        <x-field name="reference" label="Référence / n° facture" value="{{ old('reference', $expense->reference ?? null) }}" />
    </div>
</div>
