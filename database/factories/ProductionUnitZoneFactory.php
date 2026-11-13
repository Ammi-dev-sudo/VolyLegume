<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionUnitZone>
 */
class ProductionUnitZoneFactory extends Factory
{
    protected $model = ProductionUnitZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'production_unit_id' => fn (array $attributes) => ProductionUnit::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'code' => 'Z'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Zone '.fake()->word(),
            'area_m2' => fake()->randomFloat(2, 10, 200),
            'soil_type' => null,
            'irrigation_type' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }
}
