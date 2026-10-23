@extends('layouts.app')

@section('title', 'Lot '.$lot->code)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Traçabilité du lot</p>
            <h1 class="text-xl font-semibold text-slate-800">{{ $lot->code }} — {{ $lot->campaign?->crop?->name }}</h1>
            <p class="text-sm text-slate-500">
                Récolté le {{ $lot->harvested_at->format('d/m/Y H:i') }} ·
                {{ $lot->campaign?->name }} · {{ $lot->campaign?->productionUnit?->name }}
            </p>
        </div>
        <a href="{{ route('produce-lots.index') }}" class="text-sm text-slate-500 underline">Retour au stock</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Disponible</p>
            <p class="mt-1 text-2xl font-semibold {{ (float) $lot->available_quantity > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                {{ number_format((float) $lot->available_quantity, 2, ',', ' ') }} {{ $lot->unit }}
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Quantité initiale</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
                {{ number_format((float) $lot->initial_quantity, 2, ',', ' ') }} {{ $lot->unit }}
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Vendu</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
                {{ number_format((float) $lot->saleItems->sum('quantity'), 2, ',', ' ') }} {{ $lot->unit }}
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Statut</p>
            <p class="mt-1 text-lg font-semibold text-slate-800">{{ $lot->status->label() }}</p>
            @if ($lot->quality)
                <p class="text-xs text-slate-500">Qualité : {{ $lot->quality }}</p>
            @endif
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
        <div class="space-y-6">
            <x-card title="Chaîne de traçabilité" subtitle="De la parcelle à la vente">
                <ol class="relative space-y-5 border-l border-emerald-200 pl-5">
                    <li>
                        <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-600"></span>
                        <p class="text-sm font-semibold text-slate-800">Parcelle {{ $lot->campaign?->productionUnit?->code }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $lot->campaign?->productionUnit?->name }} ·
                            {{ number_format((float) $lot->campaign?->cultivated_area_m2, 0, ',', ' ') }} m² cultivés
                        </p>
                    </li>
                    <li>
                        <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-600"></span>
                        <p class="text-sm font-semibold text-slate-800">Campagne {{ $lot->campaign?->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $lot->campaign?->crop?->name }} {{ $lot->campaign?->crop?->variety }} ·
                            du {{ $lot->campaign?->start_date?->format('d/m/Y') }} au {{ $lot->campaign?->expected_end_date?->format('d/m/Y') }}
                        </p>
                    </li>
                    <li>
                        <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-emerald-600"></span>
                        <p class="text-sm font-semibold text-slate-800">Opérations et traitements</p>
                        <p class="text-xs text-slate-500">
                            {{ $lot->campaign?->activities->count() ?? 0 }} activité(s) ·
                            {{ $lot->campaign?->treatments->count() ?? 0 }} traitement(s)
                        </p>
                        @if ($lot->campaign?->treatments->isNotEmpty())
                            <ul class="mt-1 list-inside list-disc text-xs text-slate-500">
                                @foreach ($lot->campaign->treatments as $treatment)
                                    <li>{{ $treatment->input?->name }} — {{ $treatment->applied_at?->format('d/m/Y') }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                    <li>
                        <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-amber-500"></span>
                        <p class="text-sm font-semibold text-slate-800">Récolte du {{ $lot->harvested_at->format('d/m/Y H:i') }}</p>
                        <p class="text-xs text-slate-500">
                            {{ number_format((float) $lot->initial_quantity, 2, ',', ' ') }} {{ $lot->unit }} ·
                            enregistrée par {{ $lot->harvest->recordedBy?->name ?? '—' }}
                        </p>
                        <a href="{{ route('harvests.show', $lot->harvest) }}" class="text-xs font-semibold text-emerald-700 hover:underline">
                            Voir la récolte
                        </a>
                    </li>
                    <li>
                        <span class="absolute -left-2 mt-1 h-4 w-4 rounded-full border-2 border-white bg-sky-500"></span>
                        <p class="text-sm font-semibold text-slate-800">Ventes</p>
                        @if ($lot->saleItems->isEmpty())
                            <p class="text-xs text-slate-500">Aucune vente pour ce lot.</p>
                        @else
                            <ul class="mt-1 space-y-1 text-xs">
                                @foreach ($lot->saleItems as $item)
                                    <li>
                                        <a href="{{ route('sales.show', $item->sale) }}" class="text-emerald-700 hover:underline">
                                            {{ $item->sale->reference }}
                                        </a>
                                        — {{ number_format((float) $item->quantity, 2, ',', ' ') }} {{ $item->unit }}
                                        à {{ number_format((float) $item->unit_price, 0, ',', ' ') }} Ar
                                        @if ($item->sale->customer)
                                            · {{ $item->sale->customer->name }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                </ol>
            </x-card>

            <x-card title="Mouvements du lot">
                @if ($lot->movements->isEmpty())
                    <p class="text-sm text-slate-500">Aucun mouvement.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="py-2 pr-4">Date</th>
                                    <th class="py-2 pr-4">Type</th>
                                    <th class="py-2 pr-4">Variation</th>
                                    <th class="py-2 pr-4">Motif</th>
                                    <th class="py-2">Par</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($lot->movements->sortBy('occurred_at') as $movement)
                                    <tr>
                                        <td class="py-2 pr-4 text-xs whitespace-nowrap">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                                        <td class="py-2 pr-4 text-xs">{{ $movement->type->label() }}</td>
                                        <td class="py-2 pr-4 font-semibold {{ (float) $movement->quantity_delta >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                            {{ (float) $movement->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float) $movement->quantity_delta, 2, ',', ' ') }} {{ $lot->unit }}
                                        </td>
                                        <td class="py-2 pr-4 text-xs">{{ $movement->reason ?? '—' }}</td>
                                        <td class="py-2 text-xs">{{ $movement->createdBy?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @can('adjust', $lot)
                <x-card title="Ajuster le stock" subtitle="Perte, don, autoconsommation ou correction">
                    <form method="POST" action="{{ route('produce-lots.movements.store', $lot) }}" class="space-y-4">
                        @csrf
                        <x-select-field name="type" label="Type de mouvement" required
                                        :options="$movementTypes->mapWithKeys(fn ($type) => [$type->value => $type->label()])" />
                        <x-field name="quantity" label="Quantité" type="number" step="0.001" required
                                 hint="Négative pour une correction sortante, positive pour une correction entrante." />
                        <x-field name="reason" label="Motif" required />
                        <button type="submit" class="w-full rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
                            Enregistrer le mouvement
                        </button>
                    </form>
                </x-card>
            @endcan

            <x-card title="Informations">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Code lot</dt><dd class="font-mono text-xs">{{ $lot->code }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Emplacement</dt><dd>{{ $lot->storage_location ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Qualité</dt><dd>{{ $lot->quality ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Grade</dt><dd>{{ $lot->harvest->grade ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Pertes déclarées</dt><dd>{{ number_format((float) $lot->harvest->loss_quantity, 2, ',', ' ') }} {{ $lot->unit }}</dd></div>
                </dl>
                <a href="{{ route('sales.create', ['produce_lot_id' => $lot->id]) }}"
                   class="mt-4 block rounded bg-emerald-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-800">
                    Vendre ce lot
                </a>
            </x-card>
        </div>
    </div>
@endsection
