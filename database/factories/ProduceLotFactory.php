<?php

namespace Database\Factories;

use App\Enums\ProduceLotStatus;
use App\Models\Campaign;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\ProduceLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProduceLot>
 */
class ProduceLotFactory extends Factory
{
    protected $model = ProduceLot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 5, 500);

        return [
            'farm_id' => Farm::factory(),
            'campaign_id' => fn (array $attributes) => Campaign::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'harvest_id' => fn (array $attributes) => Harvest::factory()
                ->create([
                    'farm_id' => $attributes['farm_id'],
                    'campaign_id' => $attributes['campaign_id'],
                    'quantity' => $quantity,
                ])->id,
            'code' => 'LOT-'.fake()->unique()->numerify('######'),
            'initial_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit' => 'kg',
            'quality' => 'bien',
            'harvested_at' => now()->subDay(),
            'storage_location' => 'Hangar',
            'status' => ProduceLotStatus::Available->value,
            'notes' => null,
        ];
    }
}
