@php
    $selectedUnit = old('production_unit_id', $issue->production_unit_id ?? null);
    $selectedCampaign = old('campaign_id', $issue->campaign_id ?? null);
    $selectedType = old('type', optional($issue->type ?? null)->value);
    $selectedSeverity = old('severity', optional($issue->severity ?? null)->value);
    $selectedStatus = old('status', optional($issue->status ?? null)->value);
    $selectedAssignee = old('assigned_to', $issue->assigned_to ?? null);
@endphp

<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="production_unit_id" label="Parcelle concernée" required
                        :value="$selectedUnit"
                        :options="$units->mapWithKeys(fn ($unit) => [$unit->id => $unit->code . ' — ' . $unit->name])"
                        placeholder="— Choisir —" />
        <x-select-field name="campaign_id" label="Culture / campagne" placeholder="— Non rattachée —"
                        :value="$selectedCampaign"
                        :options="$campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $campaign->name . ' · ' . ($campaign->crop?->name ?? 'culture') . ' · ' . ($campaign->productionUnit?->code ?? '')])" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="type" label="Type de problème" required
                        :value="$selectedType"
                        :options="$types->mapWithKeys(fn ($type) => [$type->value => $type->label()])" />
        <x-select-field name="severity" label="Gravité" required
                        :value="$selectedSeverity"
                        :options="$severities->mapWithKeys(fn ($severity) => [$severity->value => $severity->label()])"
                        hint="Le niveau critique déclenche une alerte sur le tableau de bord." />
    </div>

    <x-field name="title" label="Titre du problème" required value="{{ old('title', $issue->title ?? null) }}"
             placeholder="Mildiou sur les feuilles basses…" />

    <x-textarea-field name="description" label="Description détaillée" rows="4" required
                      value="{{ old('description', $issue->description ?? null) }}"
                      placeholder="Symptômes observés, localisation, évolution…" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-field name="observed_at" label="Date d’observation" type="date" required
                 value="{{ old('observed_at', isset($issue) && $issue->observed_at ? $issue->observed_at->toDateString() : now()->toDateString()) }}" />
        <x-field name="affected_area_m2" label="Surface affectée (m²)" type="number" step="0.01" min="0"
                 value="{{ old('affected_area_m2', $issue->affected_area_m2 ?? null) }}" />
        <x-select-field name="status" label="Statut" required
                        :value="$selectedStatus"
                        :options="$statuses->mapWithKeys(fn ($status) => [$status->value => $status->label()])" />
    </div>

    <x-select-field name="assigned_to" label="Responsable du suivi" placeholder="— Non assigné —"
                    :value="$selectedAssignee"
                    :options="$members->mapWithKeys(fn ($member) => [$member->id => $member->name])" />

    <x-textarea-field name="recommended_actions" label="Actions recommandées" rows="3"
                      value="{{ old('recommended_actions', $issue->recommended_actions ?? null) }}"
                      placeholder="Retirer les feuilles atteintes, appliquer un fongicide autorisé…" />

    @unless (isset($issue) && $issue->exists)
        <div>
            <label for="photo" class="block text-sm font-medium text-slate-700">Photographie de constat (facultatif)</label>
            <input id="photo" name="photo" type="file" accept="image/*"
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">Les images sont compressées et conservées sur le stockage configuré.</p>
            @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endunless

    @if (isset($issue) && $issue->exists)
        <x-textarea-field name="resolution_notes" label="Notes de résolution" rows="3"
                          value="{{ old('resolution_notes', $issue->resolution_notes ?? null) }}"
                          placeholder="Résultat obtenu, validation terrain…" />
    @endif
</div>
