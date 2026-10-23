<?php

namespace Database\Seeders;

use App\Enums\CustomerType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\ProduceLot;
use App\Models\Revenue;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CommercialDataSeeder extends Seeder
{
    public function run(): void
    {
        $sales = app(SaleService::class);

        Farm::query()->orderBy('id')->each(function (Farm $farm) use ($sales): void {
            $manager = $farm->manager;

            if ($manager === null) {
                return;
            }

            $customers = $this->ensureCustomers($farm);

            $lots = ProduceLot::query()
                ->forFarm($farm->id)
                ->available()
                ->whereDoesntHave('saleItems')
                ->with('campaign.crop')
                ->orderByDesc('harvested_at')
                ->limit(3)
                ->get();

            foreach ($lots as $index => $lot) {
                $quantity = round((float) $lot->available_quantity * 0.4, 3);

                if ($quantity <= 0) {
                    continue;
                }

                $price = (float) ($lot->campaign?->crop?->default_sale_price ?? 2500);

                Revenue::query()
                    ->where('farm_id', $farm->id)
                    ->where('harvest_id', $lot->harvest_id)
                    ->where('reference', 'like', 'DEMO-%-SALE')
                    ->delete();

                $sale = $sales->create(
                    $farm->id,
                    [
                        'customer_id' => $customers[$index % $customers->count()]?->id,
                        'sale_date' => $lot->harvested_at->copy()->addDay()->toDateString(),
                        'discount_amount' => 0,
                        'notes' => 'Vente de démonstration reliée au lot '.$lot->code.'.',
                    ],
                    [
                        [
                            'produce_lot_id' => $lot->id,
                            'quantity' => $quantity,
                            'unit_price' => $price,
                        ],
                    ],
                    $manager,
                );

                if ($index === 0) {
                    $this->recordPartialPayment($sales, $sale, $manager);
                }
            }
        });
    }

    /**
     * @return Collection<int, Customer>
     */
    private function ensureCustomers(Farm $farm)
    {
        $definitions = [
            [
                'code' => 'CLI-001',
                'name' => 'Marché Analakely',
                'type' => CustomerType::Retailer,
                'contact_name' => 'Ravaka',
                'phone' => '+261 34 12 345 67',
                'address' => 'Analakely, Antananarivo',
            ],
            [
                'code' => 'CLI-002',
                'name' => 'Restaurant Le Jardin',
                'type' => CustomerType::Restaurant,
                'contact_name' => 'Hery',
                'phone' => '+261 33 98 765 43',
                'address' => 'Ivandry, Antananarivo',
            ],
            [
                'code' => 'CLI-003',
                'name' => 'Coopérative Soa Miaraka',
                'type' => CustomerType::Cooperative,
                'contact_name' => 'Fanja',
                'phone' => '+261 32 45 678 90',
                'address' => 'Itasy',
            ],
        ];

        return collect($definitions)->map(fn (array $definition) => Customer::firstOrCreate(
            ['farm_id' => $farm->id, 'code' => $definition['code']],
            $definition + ['is_active' => true],
        ));
    }

    private function recordPartialPayment(SaleService $sales, Sale $sale, User $manager): void
    {
        if ($sale->payments()->exists()) {
            return;
        }

        $amount = round((float) $sale->total_amount * 0.4, 2);

        if ($amount <= 0) {
            return;
        }

        $sales->recordPayment($sale, [
            'amount' => $amount,
            'method' => PaymentMethod::MobileMoney->value,
            'paid_at' => $sale->sale_date->copy()->addDay()->toDateString(),
            'reference' => 'MM-'.$sale->reference,
            'notes' => 'Acompte de démonstration.',
        ], $manager);
    }
}
