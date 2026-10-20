<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\CalendarStep;
use App\Models\Crop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarStep>
 */
class CalendarStepFactory extends Factory
{
    protected $model = CalendarStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crop_id' => Crop::factory(),
            'farm_id' => fn (array $attributes) => Crop::find($attributes['crop_id'])->farm_id,
            'name' => fake()->word(),
            'activity_type' => fake()->randomElement(ActivityType::cases())->value,
            'offset_days' => fake()->numberBetween(0, 90),
            'position' => fake()->unique()->numberBetween(1, 9999),
            'description' => null,
            'is_active' => true,
        ];
    }
}
