<?php

namespace Database\Factories;

use App\Enums\ProductionUnitStatus;
use App\Models\Farm;
use App\Models\ProductionUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionUnit>
 */
class ProductionUnitFactory extends Factory
{
    protected $model = ProductionUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'code' => 'P'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Parcelle '.fake()->word(),
            'area_m2' => fake()->randomFloat(2, 50, 800),
            'location' => 'Rangée '.fake()->numberBetween(1, 25),
            'type' => 'plot',
            'status' => ProductionUnitStatus::Available->value,
        ];
    }
}
