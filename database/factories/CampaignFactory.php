<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Enums\ReferenceType;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\ProductionUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-4 months', 'now');

        return [
            'farm_id' => Farm::factory(),
            'production_unit_id' => fn (array $attributes) => ProductionUnit::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'crop_id' => fn (array $attributes) => Crop::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'name' => 'Campagne '.fake()->unique()->bothify('C-###'),
            'start_date' => $start->format('Y-m-d'),
            'reference_type' => fake()->randomElement(ReferenceType::cases())->value,
            'expected_end_date' => (clone $start)->modify('+90 days')->format('Y-m-d'),
            'actual_end_date' => null,
            'cultivated_area_m2' => fake()->randomFloat(2, 20, 400),
            'status' => CampaignStatus::Ongoing->value,
            'notes' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CampaignStatus::Planned->value]);
    }
}
