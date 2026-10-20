<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Campaign;
use App\Models\Input;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'input_id' => Input::factory(),
            'farm_id' => fn (array $attributes) => Input::find($attributes['input_id'])->farm_id,
            'campaign_id' => null,
            'activity_id' => null,
            'type' => StockMovementType::Entry->value,
            'quantity_delta' => fake()->randomFloat(3, 1, 50),
            'occurred_at' => now(),
            'created_by' => User::factory(),
            'reason' => 'Approvisionnement',
            'corrects_movement_id' => null,
        ];
    }

    public function consumption(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StockMovementType::Consumption->value,
            'quantity_delta' => -abs(fake()->randomFloat(3, 0.1, 5)),
        ]);
    }

    public function exit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StockMovementType::Exit->value,
            'quantity_delta' => -abs(fake()->randomFloat(3, 0.1, 5)),
        ]);
    }

    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn (array $attributes) => [
            'farm_id' => $campaign->farm_id,
            'campaign_id' => $campaign->id,
        ]);
    }
}
