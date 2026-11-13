<?php

namespace Database\Factories;

use App\Enums\IssuePhotoKind;
use App\Models\CropIssue;
use App\Models\CropIssuePhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropIssuePhoto>
 */
class CropIssuePhotoFactory extends Factory
{
    protected $model = CropIssuePhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => fn (array $attributes) => CropIssue::query()
                ->findOrFail($attributes['crop_issue_id'])->farm_id,
            'crop_issue_id' => CropIssue::factory(),
            'photo_path' => 'issue-photos/'.fake()->uuid().'.jpg',
            'kind' => IssuePhotoKind::Observation->value,
            'caption' => null,
            'taken_at' => now(),
            'uploaded_by' => User::factory(),
        ];
    }

    public function before(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => IssuePhotoKind::Before->value]);
    }

    public function after(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => IssuePhotoKind::After->value]);
    }
}
