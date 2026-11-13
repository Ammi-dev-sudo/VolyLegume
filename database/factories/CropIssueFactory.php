<?php

namespace Database\Factories;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Campaign;
use App\Models\CropIssue;
use App\Models\Farm;
use App\Models\ProductionUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropIssue>
 */
class CropIssueFactory extends Factory
{
    protected $model = CropIssue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'production_unit_id' => fn (array $attributes) => ProductionUnit::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'campaign_id' => null,
            'reference' => 'PB-'.fake()->unique()->numerify('########-###'),
            'type' => fake()->randomElement(IssueType::cases())->value,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'observed_at' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'severity' => fake()->randomElement(IssueSeverity::cases())->value,
            'affected_area_m2' => null,
            'status' => IssueStatus::Open->value,
            'recommended_actions' => null,
            'resolution_notes' => null,
            'resolved_at' => null,
            'reported_by' => User::factory(),
            'assigned_to' => null,
        ];
    }

    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn (array $attributes) => [
            'farm_id' => $campaign->farm_id,
            'production_unit_id' => $campaign->production_unit_id,
            'campaign_id' => $campaign->id,
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => ['severity' => IssueSeverity::Critical->value]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueStatus::Resolved->value,
            'resolved_at' => now(),
        ]);
    }
}
