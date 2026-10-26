<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\IrrigationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IrrigationEvent>
 */
class IrrigationEventFactory extends Factory
{
    protected $model = IrrigationEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'farm_id' => fn (array $attributes) => Campaign::find($attributes['campaign_id'])->farm_id,
            'production_unit_id' => fn (array $attributes) => Campaign::find($attributes['campaign_id'])->production_unit_id,
            'irrigated_at' => now()->subDays(fake()->numberBetween(0, 14)),
            'duration_hours' => fake()->randomFloat(2, 0.5, 4),
            'volume_m3' => fake()->randomFloat(3, 0.5, 15),
            'method' => fake()->randomElement(['aspersion', 'goutte-à-goutte', 'gravitaire', 'arrosage manuel']),
            'source' => fake()->randomElement(['forage', 'puits', 'réservoir', 'réseau']),
            'notes' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
