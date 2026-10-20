<?php

namespace Database\Factories;

use App\Enums\FarmStatus;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    protected $model = Farm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Exploitation '.fake()->city(),
            'location' => fake()->city().', Madagascar',
            'type' => 'market_gardening',
            'area_m2' => fake()->randomFloat(2, 500, 5000),
            'manager_id' => User::factory(),
            'status' => FarmStatus::Active->value,
            'currency' => 'MGA',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => FarmStatus::Draft->value]);
    }
}
