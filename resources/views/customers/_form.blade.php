@php
    $isEdit = $customer->exists;
@endphp

<form method="POST" action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="code" label="Code client" value="{{ $customer->code }}" placeholder="Généré automatiquement" />
        <x-field name="name" label="Nom du client" required value="{{ $customer->name }}" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-select-field name="type" label="Type de client" required :value="$customer->type?->value"
                        :options="$types->mapWithKeys(fn ($type) => [$type->value => $type->label()])" />
        <x-field name="contact_name" label="Personne de contact" value="{{ $customer->contact_name }}" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="phone" label="Téléphone" value="{{ $customer->phone }}" />
        <x-field name="email" label="E-mail" type="email" value="{{ $customer->email }}" />
    </div>

    <x-field name="address" label="Adresse" value="{{ $customer->address }}" />
    <x-textarea-field name="notes" label="Notes" rows="3" value="{{ $customer->notes }}" />
    <x-checkbox-field name="is_active" label="Client actif" :checked="$customer->is_active" />

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le client' }}
        </button>
        <a href="{{ route('customers.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
    </div>
</form>
