@extends('layouts.app')

@section('title', 'Nouvelle récolte')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Nouvelle récolte</h1>

        <div id="phi-notice" class="mb-4 rounded border px-4 py-3 text-sm {{ $minimumHarvestAt ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900' }}">
            @if ($minimumHarvestAt)
                Restriction : la récolte de cette campagne est autorisée à partir du
                <strong id="phi-bound">{{ $minimumHarvestAt->format('d/m/Y H:i') }}</strong>.
            @else
                Aucune restriction de délai pour cette campagne sélectionnée.
            @endif
        </div>

        <x-card>
            <form method="POST" action="{{ route('harvests.store') }}" class="space-y-4">
                @csrf

                <x-select-field name="campaign_id" label="Campagne" required
                                :value="old('campaign_id', $selectedCampaign)"
                                :options="$campaigns->mapWithKeys(fn ($campaign) => [$campaign->id => $campaign->name])"
                                placeholder="— Choisir —" />

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="quantity" label="Quantité récoltée" type="number" step="0.001" min="0.001" required
                             value="{{ old('quantity') }}" />
                    <x-field name="unit" label="Unité" value="{{ old('unit') }}" placeholder="kg (par défaut culture)" />
                    <x-field name="harvested_at" label="Date et heure" type="datetime-local" required
                             value="{{ old('harvested_at', now()->format('Y-m-d\TH:i')) }}" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select-field name="quality" label="Qualité" placeholder="— Non notée —"
                                    :value="old('quality')"
                                    :options=['standard' => 'Standard', 'bien' => 'Bonne', 'moyen' => 'Moyenne', 'refuse' => 'Refusée / non conforme'] />
                    <div></div>
                </div>

                <x-textarea-field name="notes" label="Observations" rows="3" value="{{ old('notes') }}" />

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer la récolte
                    </button>
                    <a href="{{ route('harvests.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>

    <script>
        const bounds = @json($campaigns->mapWithKeys(fn ($campaign) => [
            $campaign->id => $campaign->treatments()->max('minimum_harvest_at'),
        ]));
        const notice = document.getElementById('phi-notice');
        const select = document.querySelector('select[name="campaign_id"]');
        select.addEventListener('change', function () {
            const bound = bounds[this.value];
            if (bound) {
                const date = new Date(bound.replace(' ', 'T'));
                notice.className = 'mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900';
                notice.innerHTML = 'Restriction : la récolte de cette campagne est autorisée à partir du <strong>' +
                    date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR').slice(0, 5) + '</strong>.';
            } else {
                notice.className = 'mb-4 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900';
                notice.textContent = 'Aucune restriction de délai pour cette campagne sélectionnée.';
            }
        });
    </script>
@endsection
