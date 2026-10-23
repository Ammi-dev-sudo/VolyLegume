<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\ProduceLot;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 20);
        $unitPrice = fake()->randomFloat(2, 500, 5000);

        return [
            'farm_id' => Farm::factory(),
            'sale_id' => fn (array $attributes) => Sale::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'produce_lot_id' => fn (array $attributes) => ProduceLot::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'description' => 'Lot de démonstration',
            'quantity' => $quantity,
            'unit' => 'kg',
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ];
    }
}
