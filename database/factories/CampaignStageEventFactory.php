<?php

namespace Database\Factories;

use App\Enums\CropStage;
use App\Models\Campaign;
use App\Models\CampaignStageEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignStageEvent>
 */
class CampaignStageEventFactory extends Factory
{
    protected $model = CampaignStageEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => fn (array $attributes) => Campaign::query()
                ->findOrFail($attributes['campaign_id'])->farm_id,
            'campaign_id' => Campaign::factory(),
            'stage' => CropStage::Growth->value,
            'recorded_at' => now(),
            'notes' => null,
            'photo_path' => null,
            'recorded_by' => User::factory(),
        ];
    }

    public function stage(CropStage $stage): static
    {
        return $this->state(fn (array $attributes) => ['stage' => $stage->value]);
    }
}
