@extends('layouts.app')

@section('title', 'Nouvelle vente')

@section('content')
    @php
        $lotData = $lots->mapWithKeys(fn ($lot) => [
            $lot->id => [
                'price' => (float) ($lot->campaign?->crop?->default_sale_price ?? 0),
                'stock' => (float) $lot->available_quantity,
                'unit' => $lot->unit,
                'label' => $lot->code.' — '.($lot->campaign?->crop?->name ?? 'Culture'),
            ],
        ]);
        $oldItems = old('items', []);
        $rowCount = max(3, count($oldItems));
    @endphp

    <div class="mx-auto max-w-5xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Nouvelle vente</h1>
                <p class="text-sm text-slate-500">La validation déduit le stock des lots et crée la recette correspondante.</p>
            </div>
            <a href="{{ route('customers.create') }}" class="text-sm font-semibold text-emerald-700 hover:underline">Créer un client</a>
        </div>

        @if ($lots->isEmpty())
            <x-card>
                <p class="text-sm text-slate-600">Aucun lot disponible à la vente.</p>
                <a href="{{ route('harvests.create') }}" class="mt-3 inline-block rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Enregistrer une récolte
                </a>
            </x-card>
        @else
            <x-card>
                <form method="POST" action="{{ route('sales.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-select-field name="customer_id" label="Client" placeholder="Client de passage"
                                        :options="$customers->mapWithKeys(fn ($customer) => [$customer->id => $customer->name.' ('.$customer->code.')'])" />
                        <x-field name="sale_date" label="Date de vente" type="date" required value="{{ old('sale_date', now()->toDateString()) }}" />
                        <x-field name="discount_amount" label="Remise (Ar)" type="number" step="0.01" min="0" value="{{ old('discount_amount', 0) }}" />
                    </div>

                    @error('items')
                        <p class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="pb-2 pr-3">Lot de récolte</th>
                                    <th class="pb-2 pr-3">Quantité</th>
                                    <th class="pb-2 pr-3">Prix unitaire (Ar)</th>
                                    <th class="pb-2 pr-3 text-right">Total ligne</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @for ($i = 0; $i < $rowCount; $i++)
                                    @php
                                        $selected = old("items.$i.produce_lot_id", $i === 0 ? $selectedLot : null);
                                        $data = $selected ? ($lotData[$selected] ?? null) : null;
                                    @endphp
                                    <tr data-sale-row>
                                        <td class="py-2 pr-3">
                                            <select name="items[{{ $i }}][produce_lot_id]" data-lot-select
                                                    class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                                <option value="">— Choisir un lot —</option>
                                                @foreach ($lots as $lot)
                                                    <option value="{{ $lot->id }}"
                                                            data-price="{{ (float) ($lot->campaign?->crop?->default_sale_price ?? 0) }}"
                                                            data-stock="{{ (float) $lot->available_quantity }}"
                                                            data-unit="{{ $lot->unit }}"
                                                            @selected((string) $selected === (string) $lot->id)>
                                                        {{ $lot->code }} — {{ $lot->campaign?->crop?->name }}
                                                        ({{ number_format((float) $lot->available_quantity, 2, ',', ' ') }} {{ $lot->unit }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <p data-lot-stock class="mt-1 text-xs text-slate-500">
                                                @if ($data)
                                                    Disponible : {{ number_format($data['stock'], 2, ',', ' ') }} {{ $data['unit'] }}
                                                @endif
                                            </p>
                                            @error("items.$i.produce_lot_id")
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="py-2 pr-3">
                                            <input type="number" name="items[{{ $i }}][quantity]" data-quantity
                                                   step="0.001" min="0.001"
                                                   value="{{ old("items.$i.quantity") }}"
                                                   class="w-28 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                            @error("items.$i.quantity")
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="py-2 pr-3">
                                            <input type="number" name="items[{{ $i }}][unit_price]" data-unit-price
                                                   step="0.01" min="0"
                                                   value="{{ old("items.$i.unit_price", $data['price'] ?? null) }}"
                                                   class="w-32 rounded border border-slate-300 px-2 py-1.5 text-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                            @error("items.$i.unit_price")
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="py-2 pr-3 text-right font-semibold" data-line-total>0 Ar</td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>

                    <x-textarea-field name="notes" label="Notes" rows="2" value="{{ old('notes') }}" />

                    <div class="ml-auto max-w-xs space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Sous-total</span><span data-subtotal>0 Ar</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Remise</span><span data-discount>0 Ar</span></div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><span>Total</span><span data-total>0 Ar</span></div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Confirmer la vente
                        </button>
                        <a href="{{ route('sales.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                    </div>
                </form>
            </x-card>
        @endif
    </div>

    <script>
        const formatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
        const rows = Array.from(document.querySelectorAll('[data-sale-row]'));
        const discountInput = document.querySelector('input[name="discount_amount"]');

        function updateRow(row) {
            const select = row.querySelector('[data-lot-select]');
            const quantity = parseFloat(row.querySelector('[data-quantity]').value) || 0;
            const priceInput = row.querySelector('[data-unit-price]');
            const price = parseFloat(priceInput.value) || 0;
            const stockLabel = row.querySelector('[data-lot-stock]');
            const option = select.selectedOptions[0];

            if (option && option.dataset.stock !== undefined) {
                stockLabel.textContent = 'Disponible : ' + Number(option.dataset.stock).toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' ' + option.dataset.unit;
                if (!priceInput.dataset.touched && option.dataset.price) {
                    priceInput.value = option.dataset.price;
                }
            } else {
                stockLabel.textContent = '';
            }

            const total = quantity * (parseFloat(priceInput.value) || 0);
            row.querySelector('[data-line-total]').textContent = formatter.format(total) + ' Ar';
            return total;
        }

        function updateTotals() {
            const subtotal = rows.reduce((sum, row) => sum + updateRow(row), 0);
            const discount = parseFloat(discountInput?.value) || 0;
            const total = Math.max(subtotal - discount, 0);

            document.querySelector('[data-subtotal]').textContent = formatter.format(subtotal) + ' Ar';
            document.querySelector('[data-discount]').textContent = formatter.format(discount) + ' Ar';
            document.querySelector('[data-total]').textContent = formatter.format(total) + ' Ar';
        }

        rows.forEach((row) => {
            const priceInput = row.querySelector('[data-unit-price]');
            const select = row.querySelector('[data-lot-select]');

            priceInput.addEventListener('input', () => {
                priceInput.dataset.touched = '1';
                updateTotals();
            });
            row.querySelector('[data-quantity]').addEventListener('input', updateTotals);
            select.addEventListener('change', () => {
                priceInput.dataset.touched = '';
                updateTotals();
            });
        });

        discountInput?.addEventListener('input', updateTotals);
        updateTotals();
    </script>
@endsection
