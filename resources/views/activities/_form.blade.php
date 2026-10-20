<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="campaign_id" label="Campagne" required
                        :value="old('campaign_id', $activity->campaign_id ?? null)"
                        :options="$campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $campaign->name])"
                        placeholder="— Choisir —" />
        <x-select-field name="type" label="Type d’activité" required
                        :value="old('type', optional($activity->type ?? null)->value)"
                        :options="$types->mapWithKeys(fn ($type) => [$type->value => $type->label()])" />
    </div>

    <x-field name="title" label="Intitulé" required value="{{ old('title', $activity->title ?? null) }}"
             placeholder="Désherbage rangée A, irrigation…" />

    <x-textarea-field name="description" label="Description / consignes" rows="3"
                      value="{{ old('description', $activity->description ?? null) }}" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="planned_date" label="Date prévue" type="date" required
                 value="{{ old('planned_date', isset($activity) && $activity->planned_date ? $activity->planned_date->toDateString() : now()->toDateString()) }}" />
        <x-select-field name="status" label="Statut" required
                        :value="old('status', optional($activity->status ?? null)->value ?? \App\Enums\ActivityStatus::Planned->value)"
                        :options="$statuses->mapWithKeys(fn ($status) => [$status->value => $status->label()])" />
        <x-select-field name="assigned_to" label="Assignée à" placeholder="— Non assignée —"
                        :value="old('assigned_to', $activity->assigned_to ?? null)"
                        :options="$members->mapWithKeys(fn ($member) => [$member->id => $member->name])" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="estimated_cost" label="Coût estimé (Ar)" type="number" step="0.01" min="0"
                 value="{{ old('estimated_cost', $activity->estimated_cost ?? null) }}" />
        <x-field name="performed_at" label="Date de réalisation" type="datetime-local"
                 hint="À renseigner si le statut est « Réalisée »."
                 value="{{ old('performed_at', isset($activity) && $activity->performed_at ? $activity->performed_at->format('Y-m-d\TH:i') : null) }}" />
    </div>
</div>
