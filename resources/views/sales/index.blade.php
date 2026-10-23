@extends('layouts.app')

@section('title', 'Ventes')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Ventes</h1>
            <p class="text-sm text-slate-500">Sorties de stock, encaissements et recettes générées automatiquement.</p>
        </div>
        @can('create', App\Models\Sale::class)
            <a href="{{ route('sales.create') }}"
               class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                Nouvelle vente
            </a>
        @endcan
    </div>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Chiffre d’affaires confirmé</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($total, 0, ',', ' ') }} Ar</p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Reste à encaisser</p>
            <p class="mt-1 text-2xl font-semibold {{ $outstanding > 0 ? 'text-amber-600' : 'text-emerald-700' }}">
                {{ number_format($outstanding, 0, ',', ' ') }} Ar
            </p>
        </x-card>
        <x-card>
            <p class="text-xs uppercase tracking-wide text-slate-500">Ventes affichées</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $sales->total() }}</p>
        </x-card>
    </div>

    <form method="GET" action="{{ route('sales.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div>
            <label for="customer_id" class="block text-xs font-medium text-slate-600">Client</label>
            <select id="customer_id" name="customer_id" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-xs font-medium text-slate-600">Statut</label>
            <select id="status" name="status" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment_status" class="block text-xs font-medium text-slate-600">Paiement</label>
            <select id="payment_status" name="payment_status" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
                <option value="">Tous</option>
                @foreach ($paymentStatuses as $paymentStatus)
                    <option value="{{ $paymentStatus->value }}" @selected(request('payment_status') === $paymentStatus->value)>{{ $paymentStatus->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="block text-xs font-medium text-slate-600">Du</label>
            <input type="date" id="from" name="from" value="{{ request('from') }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-slate-600">Au</label>
            <input type="date" id="to" name="to" value="{{ request('to') }}" class="mt-1 rounded border border-slate-300 px-2 py-1.5">
        </div>
        <button type="submit" class="rounded bg-slate-700 px-3 py-2 text-white hover:bg-slate-800">Filtrer</button>
        <a href="{{ route('sales.index') }}" class="pb-2 text-slate-500 underline">Réinitialiser</a>
    </form>

    <x-card>
        @if ($sales->isEmpty())
            <p class="text-sm text-slate-600">Aucune vente enregistrée.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Référence</th>
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Client</th>
                            <th class="py-2 pr-4">Lignes</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Payé</th>
                            <th class="py-2 pr-4">Paiement</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sales as $sale)
                            <tr>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $sale->reference }}</td>
                                <td class="py-2 pr-4 text-xs whitespace-nowrap">{{ $sale->sale_date->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ $sale->customer?->name ?? 'Client de passage' }}</td>
                                <td class="py-2 pr-4 text-xs">{{ $sale->items_count }}</td>
                                <td class="py-2 pr-4 font-semibold">{{ number_format((float) $sale->total_amount, 0, ',', ' ') }} Ar</td>
                                <td class="py-2 pr-4 text-xs">{{ number_format((float) $sale->amount_paid, 0, ',', ' ') }} Ar</td>
                                <td class="py-2 pr-4">
                                    <span class="rounded px-2 py-0.5 text-xs
                                        {{ $sale->payment_status === App\Enums\PaymentStatus::Paid ? 'bg-emerald-100 text-emerald-800' : ($sale->payment_status === App\Enums\PaymentStatus::Partial ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $sale->payment_status->label() }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-xs">{{ $sale->status->label() }}</td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-emerald-700 hover:underline">Ouvrir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $sales->links() }}</div>
        @endif
    </x-card>
@endsection
