<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crop>
 */
class CropFactory extends Factory
{
    protected $model = Crop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->unique()->randomElement([
                'Tomate', 'Pomme de terre', 'Carotte', 'Pois', 'Laitue', 'Concombre',
                'Aubergine', 'Chou', 'Oignon', 'Haricot', 'Betterave', 'Poivron',
            ]),
            'variety' => fake()->word(),
            'production_unit' => 'kg',
            'estimated_cycle_days' => fake()->numberBetween(45, 120),
            'is_active' => true,
        ];
    }
}
