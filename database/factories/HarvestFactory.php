<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Harvest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Harvest>
 */
class HarvestFactory extends Factory
{
    protected $model = Harvest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'farm_id' => fn (array $attributes) => Campaign::find($attributes['campaign_id'])->farm_id,
            'harvested_at' => now(),
            'quantity' => fake()->randomFloat(3, 1, 400),
            'unit' => 'kg',
            'quality' => fake()->randomElement(['standard', 'bien', 'moyen']),
            'recorded_by' => User::factory(),
            'notes' => null,
        ];
    }
}
