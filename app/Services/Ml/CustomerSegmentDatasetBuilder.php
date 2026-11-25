<?php

namespace App\Services\Ml;

use App\Models\Customer;

/**
 * Construit les variables RFM de segmentation des clients.
 *
 * Récence : jours depuis le dernier achat confirmé (plafonnée pour les clients
 * sans achat). Fréquence : nombre de ventes confirmées. Montant : total acheté.
 */
class CustomerSegmentDatasetBuilder implements MlDatasetBuilder
{
    public const ID_COLUMN = 'sample_id';

    /**
     * @var list<string>
     */
    public const NUMERIC_FEATURES = [
        'recency_days',
        'frequency',
        'monetary',
    ];

    /**
     * @return list<string>
     */
    public function trainingHeader(): array
    {
        return array_merge([self::ID_COLUMN], self::NUMERIC_FEATURES);
    }

    /**
     * @return list<string>
     */
    public function predictionHeader(): array
    {
        return $this->trainingHeader();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trainingRows(): array
    {
        return $this->rows();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function predictionRows(): array
    {
        return $this->rows();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return Customer::query()
            ->with(['sales' => fn ($query) => $query->confirmed()->select(
                'id',
                'farm_id',
                'customer_id',
                'sale_date',
                'total_amount',
                'status',
            )])
            ->orderBy('id')
            ->get()
            ->map(function (Customer $customer): array {
                $sales = $customer->sales;
                $lastSaleDate = $sales->pluck('sale_date')->filter()->sortDesc()->first();
                $recency = $lastSaleDate !== null
                    ? max(0, (int) round($lastSaleDate->copy()->startOfDay()->diffInDays(today()->startOfDay(), false)))
                    : 3650;

                return [
                    self::ID_COLUMN => $customer->id,
                    'recency_days' => $recency,
                    'frequency' => $sales->count(),
                    'monetary' => round((float) $sales->sum('total_amount'), 2),
                ];
            })
            ->values()
            ->all();
    }
}
