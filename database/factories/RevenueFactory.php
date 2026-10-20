<?php

namespace Database\Factories;

use App\Enums\RevenueCategory;
use App\Models\Farm;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revenue>
 */
class RevenueFactory extends Factory
{
    protected $model = Revenue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'campaign_id' => null,
            'harvest_id' => null,
            'category' => RevenueCategory::HarvestSale->value,
            'description' => 'Vente '.fake()->words(3, true),
            'amount' => fake()->randomFloat(2, 5000, 900000),
            'revenue_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'reference' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
