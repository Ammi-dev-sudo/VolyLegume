<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Input;
use App\Models\PhytosanitaryTreatment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhytosanitaryTreatment>
 */
class PhytosanitaryTreatmentFactory extends Factory
{
    protected $model = PhytosanitaryTreatment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'farm_id' => fn (array $attributes) => Campaign::find($attributes['campaign_id'])->farm_id,
            'input_id' => fn (array $attributes) => Input::factory()->phytosanitary()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'activity_id' => fn (array $attributes) => Activity::factory()->completed()
                ->create([
                    'campaign_id' => $attributes['campaign_id'],
                    'farm_id' => $attributes['farm_id'],
                ])->id,
            'stock_movement_id' => fn (array $attributes) => StockMovement::factory()->consumption()
                ->create([
                    'input_id' => $attributes['input_id'],
                    'farm_id' => $attributes['farm_id'],
                    'campaign_id' => $attributes['campaign_id'],
                ])->id,
            'applied_at' => now(),
            'dose_value' => fake()->randomFloat(3, 0.1, 5),
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => fake()->numberBetween(1, 21),
            'delay_source_snapshot' => 'Notice fabricant',
            'minimum_harvest_at' => now()->addDays(7),
            'applied_by' => User::factory(),
            'notes' => null,
        ];
    }
}
