<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'farm_id' => fn (array $attributes) => Campaign::find($attributes['campaign_id'])->farm_id,
            'calendar_step_id' => null,
            'type' => fake()->randomElement(ActivityType::cases())->value,
            'title' => ucfirst(fake()->words(3, true)),
            'description' => null,
            'planned_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'performed_at' => null,
            'status' => ActivityStatus::Planned->value,
            'assigned_to' => null,
            'created_by' => User::factory(),
            'performed_by' => null,
            'estimated_cost' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActivityStatus::Completed->value,
            'performed_at' => now(),
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActivityStatus::Planned->value,
            'planned_date' => now()->subWeek()->format('Y-m-d'),
        ]);
    }
}
