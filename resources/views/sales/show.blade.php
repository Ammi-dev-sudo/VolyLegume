@extends('layouts.app')

@section('title', 'Vente '.$sale->reference)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Vente</p>
            <h1 class="text-xl font-semibold text-slate-800">{{ $sale->reference }}</h1>
            <p class="text-sm text-slate-500">
                {{ $sale->sale_date->format('d/m/Y') }} ·
                {{ $sale->customer?->name ?? 'Client de passage' }} ·
                enregistrée par {{ $sale->recordedBy?->name ?? '—' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="rounded-full px-3 py-1 text-xs font-semibold
                {{ $sale->status === App\Enums\SaleStatus::Confirmed ? 'bg-emerald-100 text-emerald-800' : ($sale->status === App\Enums\SaleStatus::Cancelled ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600') }}">
                {{ $sale->status->label() }}
            </span>
            <a href="{{ route('sales.index') }}" class="text-sm text-slate-500 underline">Retour aux ventes</a>
        </div>
    </div>

    @if ($sale->status === App\Enums\SaleStatus::Cancelled)
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Cette vente est annulée : le stock a été restauré et la recette supprimée.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Total</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format((float) $sale->total_amount, 0, ',', ' ') }} Ar</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Encaissé</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ number_format((float) $sale->amount_paid, 0, ',', ' ') }} Ar</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Reste à payer</p>
            <p class="mt-1 text-2xl font-semibold {{ $sale->balanceDue() > 0 ? 'text-amber-600' : 'text-emerald-700' }}">
                {{ number_format($sale->balanceDue(), 0, ',', ' ') }} Ar
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Paiement</p>
            <p class="mt-1 text-lg font-semibold text-slate-800">{{ $sale->payment_status->label() }}</p>
            <p class="text-xs text-slate-500">{{ $sale->items->count() }} ligne(s)</p>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
        <div class="space-y-6">
            <x-card title="Lignes de vente">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Produit / lot</th>
                                <th class="py-2 pr-4">Quantité</th>
                                <th class="py-2 pr-4">Prix unitaire</th>
                                <th class="py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sale->items as $item)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <p class="font-medium text-slate-800">{{ $item->description }}</p>
                                        @if ($item->produceLot)
                                            <a href="{{ route('produce-lots.show', $item->produceLot) }}" class="text-xs text-emerald-700 hover:underline">
                                                Lot {{ $item->produceLot->code }} — traçabilité
                                            </a>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4">{{ number_format((float) $item->quantity, 2, ',', ' ') }} {{ $item->unit }}</td>
                                    <td class="py-2 pr-4">{{ number_format((float) $item->unit_price, 0, ',', ' ') }} Ar</td>
                                    <td class="py-2 text-right font-semibold">{{ number_format((float) $item->line_total, 0, ',', ' ') }} Ar</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <dl class="mt-4 ml-auto max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Sous-total</dt><dd>{{ number_format((float) $sale->subtotal, 0, ',', ' ') }} Ar</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Remise</dt><dd>{{ number_format((float) $sale->discount_amount, 0, ',', ' ') }} Ar</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd>{{ number_format((float) $sale->total_amount, 0, ',', ' ') }} Ar</dd></div>
                </dl>
            </x-card>

            <x-card title="Paiements">
                @if ($sale->payments->isEmpty())
                    <p class="text-sm text-slate-500">Aucun paiement enregistré.</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Date</th>
                                <th class="py-2 pr-4">Montant</th>
                                <th class="py-2 pr-4">Moyen</th>
                                <th class="py-2 pr-4">Référence</th>
                                <th class="py-2">Par</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sale->payments as $payment)
                                <tr>
                                    <td class="py-2 pr-4 text-xs">{{ $payment->paid_at->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 font-semibold">{{ number_format((float) $payment->amount, 0, ',', ' ') }} Ar</td>
                                    <td class="py-2 pr-4 text-xs">{{ $payment->method->label() }}</td>
                                    <td class="py-2 pr-4 text-xs">{{ $payment->reference ?? '—' }}</td>
                                    <td class="py-2 text-xs">{{ $payment->recordedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            @if ($sale->notes)
                <x-card title="Notes">
                    <p class="whitespace-pre-line text-sm text-slate-600">{{ $sale->notes }}</p>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($sale->status === App\Enums\SaleStatus::Confirmed && $sale->balanceDue() > 0)
                @can('recordPayment', $sale)
                    <x-card title="Encaisser un paiement" subtitle="Reste dû : {{ number_format($sale->balanceDue(), 0, ',', ' ') }} Ar">
                        <form method="POST" action="{{ route('sales.payments.store', $sale) }}" class="space-y-4">
                            @csrf
                            <x-field name="amount" label="Montant" type="number" step="0.01" min="0.01" required
                                     value="{{ old('amount', $sale->balanceDue()) }}" />
                            <x-select-field name="method" label="Moyen de paiement" required
                                            :options="collect($paymentMethods)->mapWithKeys(fn ($method) => [$method->value => $method->label()])" />
                            <x-field name="paid_at" label="Date" type="date" required value="{{ old('paid_at', now()->toDateString()) }}" />
                            <x-field name="reference" label="Référence" value="{{ old('reference') }}" />
                            <x-textarea-field name="notes" label="Notes" rows="2" value="{{ old('notes') }}" />
                            <button type="submit" class="w-full rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                                Enregistrer le paiement
                            </button>
                        </form>
                    </x-card>
                @endcan
            @endif

            <x-card title="Client">
                @if ($sale->customer)
                    <p class="font-medium text-slate-800">{{ $sale->customer->name }}</p>
                    <p class="text-xs text-slate-500">{{ $sale->customer->type->label() }} · {{ $sale->customer->code }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ $sale->customer->phone ?? $sale->customer->email ?? '' }}</p>
                    <p class="text-sm text-slate-600">{{ $sale->customer->address ?? '' }}</p>
                @else
                    <p class="text-sm text-slate-500">Client de passage, non identifié.</p>
                @endif
            </x-card>

            <x-card title="Comptabilité">
                @if ($sale->revenue)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Recette</dt><dd class="font-mono text-xs">{{ $sale->revenue->reference }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Montant</dt><dd>{{ number_format((float) $sale->revenue->amount, 0, ',', ' ') }} Ar</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Aucune recette liée.</p>
                @endif
            </x-card>

            @if ($sale->canBeCancelled())
                @can('cancel', $sale)
                    <x-card title="Annulation">
                        <p class="mb-3 text-xs text-slate-500">
                            L’annulation restaure le stock des lots et supprime la recette liée.
                        </p>
                        <form method="POST" action="{{ route('sales.cancel', $sale) }}"
                              onsubmit="return confirm('Annuler cette vente et restaurer le stock ?');">
                            @csrf
                            <button type="submit" class="w-full rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Annuler la vente
                            </button>
                        </form>
                    </x-card>
                @endcan
            @endif
        </div>
    </div>
@endsection
