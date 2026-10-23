<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ProduceMovementType;
use App\Enums\RevenueCategory;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientProduceStockException;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ProduceLot;
use App\Models\Sale;
use App\Models\User;
use App\Support\DashboardCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private ProduceStockService $stock) {}

    /**
     * @param  array{customer_id?: ?int, sale_date: string, discount_amount?: float|int|string|null, notes?: ?string, reference?: ?string}  $data
     * @param  list<array{produce_lot_id: int, quantity: float|int|string, unit_price: float|int|string}>  $items
     */
    public function create(int $farmId, array $data, array $items, User $actor): Sale
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Ajoutez au moins une ligne de vente.',
            ]);
        }

        $sale = DB::transaction(function () use ($farmId, $data, $items, $actor) {
            $saleDate = Carbon::parse($data['sale_date']);

            $customer = null;

            if (! empty($data['customer_id'])) {
                $customer = Customer::query()
                    ->where('farm_id', $farmId)
                    ->findOrFail($data['customer_id']);
            }

            $lotIds = collect($items)->pluck('produce_lot_id')->map(fn ($id): int => (int) $id)->unique()->values();
            $lots = ProduceLot::query()
                ->where('farm_id', $farmId)
                ->whereIn('id', $lotIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lots->count() !== $lotIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Un lot de récolte sélectionné n’appartient pas à cette exploitation.',
                ]);
            }

            $prepared = [];
            $subtotal = 0.0;

            foreach ($items as $index => $item) {
                $lot = $lots->get((int) $item['produce_lot_id']);
                $quantity = round((float) $item['quantity'], 3);
                $unitPrice = round((float) $item['unit_price'], 2);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => 'La quantité doit être strictement positive.',
                    ]);
                }

                if ($quantity > (float) $lot->available_quantity) {
                    throw new InsufficientProduceStockException($lot, $quantity, (float) $lot->available_quantity);
                }

                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal = round($subtotal + $lineTotal, 2);

                $prepared[] = [
                    'lot' => $lot,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = round((float) ($data['discount_amount'] ?? 0), 2);

            if ($discount < 0 || $discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'La remise doit être comprise entre 0 et le sous-total.',
                ]);
            }

            $total = round($subtotal - $discount, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Le total de la vente doit être strictement positif.',
                ]);
            }

            $reference = $data['reference'] ?? $this->nextReference($farmId, $saleDate);

            $sale = Sale::create([
                'farm_id' => $farmId,
                'customer_id' => $customer?->id,
                'reference' => $reference,
                'sale_date' => $saleDate->toDateString(),
                'status' => SaleStatus::Confirmed,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'amount_paid' => 0,
                'payment_status' => PaymentStatus::Unpaid,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $actor->id,
            ]);

            foreach ($prepared as $line) {
                $lot = $line['lot'];

                $sale->items()->create([
                    'farm_id' => $farmId,
                    'produce_lot_id' => $lot->id,
                    'description' => $lot->campaign->crop->name.' — lot '.$lot->code,
                    'quantity' => $line['quantity'],
                    'unit' => $lot->unit,
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                $this->stock->remove(
                    $lot,
                    $line['quantity'],
                    ProduceMovementType::Sale,
                    $actor,
                    'Vente '.$reference,
                    $sale,
                    $saleDate->copy()->setTimeFrom(now()),
                );
            }

            $campaignIds = $lots->pluck('campaign_id')->unique();
            $harvestIds = $lots->pluck('harvest_id')->unique();

            $sale->revenue()->create([
                'farm_id' => $farmId,
                'campaign_id' => $campaignIds->count() === 1 ? $campaignIds->first() : null,
                'harvest_id' => $harvestIds->count() === 1 ? $harvestIds->first() : null,
                'category' => RevenueCategory::HarvestSale,
                'description' => 'Vente '.$reference.($customer !== null ? ' — '.$customer->name : ''),
                'amount' => $total,
                'revenue_date' => $saleDate->toDateString(),
                'reference' => 'VTE-S'.$sale->id,
                'recorded_by' => $actor->id,
            ]);

            return $sale->load(['customer', 'items.produceLot', 'payments', 'revenue']);
        });

        DashboardCache::forget($sale->farm);

        return $sale;
    }

    /**
     * @param  array{amount: float|int|string, method: string, paid_at: string, reference?: ?string, notes?: ?string}  $data
     */
    public function recordPayment(Sale $sale, array $data, User $actor): Payment
    {
        $payment = DB::transaction(function () use ($sale, $data, $actor) {
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== SaleStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'amount' => 'Seule une vente confirmée peut recevoir un paiement.',
                ]);
            }

            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Le montant du paiement doit être strictement positif.',
                ]);
            }

            $alreadyPaid = round((float) $locked->payments()->sum('amount'), 2);

            if (round($alreadyPaid + $amount, 2) > (float) $locked->total_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Le total des paiements ne peut pas dépasser le montant de la vente.',
                ]);
            }

            $payment = $locked->payments()->create([
                'farm_id' => $locked->farm_id,
                'amount' => $amount,
                'method' => $data['method'],
                'paid_at' => $data['paid_at'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $actor->id,
            ]);

            $newPaid = round($alreadyPaid + $amount, 2);

            $locked->update([
                'amount_paid' => $newPaid,
                'payment_status' => $newPaid >= (float) $locked->total_amount
                    ? PaymentStatus::Paid
                    : PaymentStatus::Partial,
            ]);

            return $payment;
        });

        DashboardCache::forget($sale->farm);

        return $payment;
    }

    public function cancel(Sale $sale, User $actor): void
    {
        $farm = DB::transaction(function () use ($sale, $actor) {
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== SaleStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'sale' => 'Cette vente ne peut pas être annulée.',
                ]);
            }

            if ($locked->payments()->exists()) {
                throw ValidationException::withMessages([
                    'sale' => 'Cette vente a déjà reçu des paiements : enregistrez un remboursement avant de l’annuler.',
                ]);
            }

            $locked->load('items.produceLot');

            foreach ($locked->items as $item) {
                if ($item->produceLot === null) {
                    continue;
                }

                $this->stock->restore(
                    $item->produceLot,
                    (float) $item->quantity,
                    ProduceMovementType::Return,
                    $actor,
                    'Annulation de la vente '.$locked->reference,
                    $locked,
                );
            }

            $locked->revenue()->delete();

            $locked->update([
                'status' => SaleStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $locked->farm;
        });

        DashboardCache::forget($farm);
    }

    private function nextReference(int $farmId, Carbon $date): string
    {
        $prefix = 'VTE-'.$date->format('Ymd').'-';
        $sequence = Sale::query()
            ->where('farm_id', $farmId)
            ->where('reference', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $reference = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Sale::query()->where('farm_id', $farmId)->where('reference', $reference)->exists());

        return $reference;
    }
}
