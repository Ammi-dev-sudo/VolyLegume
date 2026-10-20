@extends('layouts.app')

@section('title', $input->name)

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">{{ $input->name }}</h1>
            <p class="text-sm text-slate-500">{{ $input->code }} · {{ $input->category->label() }}</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('stock-movements.create', ['input_id' => $input->id]) }}"
               class="rounded bg-emerald-700 px-3 py-1.5 font-semibold text-white hover:bg-emerald-800">Mouvement de stock</a>
            @can('manage-referentials')
                <a href="{{ route('inputs.edit', $input) }}"
                   class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">Modifier</a>
            @endcan
        </div>
    </div>

    @if ($input->isCritical())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
            Stock critique : {{ number_format((float) $input->quantity, 3, ',', ' ') }} {{ $input->base_unit->value }}
            disponible(s) pour un seuil de {{ number_format((float) $input->minimum_stock, 3, ',', ' ') }}.
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs uppercase text-slate-500">Stock disponible</p>
            <p class="mt-1 text-2xl font-semibold {{ $input->isCritical() ? 'text-red-600' : 'text-slate-800' }}">
                {{ number_format((float) $input->quantity, 3, ',', ' ') }}
            </p>
            <p class="text-xs text-slate-500">{{ $input->base_unit->value }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase text-slate-500">Seuil d’alerte</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format((float) $input->minimum_stock, 3, ',', ' ') }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase text-slate-500">Délai avant récolte</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">
                {{ $input->default_pre_harvest_days !== null ? $input->default_pre_harvest_days . ' j' : '—' }}
            </p>
            <p class="text-xs text-slate-500">{{ $input->delay_source ?? 'sans source renseignée' }}</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase text-slate-500">État</p>
            <p class="mt-1 text-lg font-semibold {{ $input->is_active ? 'text-emerald-700' : 'text-slate-500' }}">
                {{ $input->is_active ? 'Actif' : 'Inactif' }}
            </p>
        </x-card>
    </div>

    <x-card class="mb-6" title="Historique des mouvements">
        @if ($movements->isEmpty())
            <p class="text-sm text-slate-600">Aucun mouvement enregistré.</p>
        @else
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
                    @foreach ($movements as $movement)
                        <tr>
                            <td class="py-2 pr-4">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4 text-xs">{{ $movement->type->label() }}</td>
                            <td class="py-2 pr-4 font-mono text-xs {{ $movement->quantity_delta >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                {{ $movement->quantity_delta >= 0 ? '+' : '' }}{{ number_format((float) $movement->quantity_delta, 3, ',', ' ') }}
                            </td>
                            <td class="py-2 pr-4 text-xs">{{ $movement->reason }}@if ($movement->campaign) · {{ $movement->campaign->name }}@endif</td>
                            <td class="py-2 text-xs">{{ $movement->createdBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $movements->links() }}</div>
        @endif
    </x-card>

    @if ($treatments->isNotEmpty())
        <x-card title="Derniers traitements avec ce produit">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($treatments as $treatment)
                    <li class="flex justify-between py-2">
                        <a href="{{ route('treatments.show', $treatment) }}" class="text-emerald-700 hover:underline">
                            {{ $treatment->campaign?->name }}
                        </a>
                        <span class="text-xs text-slate-500">
                            {{ $treatment->applied_at->format('d/m/Y') }} · récolte ≥ {{ $treatment->minimum_harvest_at->format('d/m/Y H:i') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
