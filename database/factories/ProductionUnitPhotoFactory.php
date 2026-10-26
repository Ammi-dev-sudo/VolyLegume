<?php

namespace Database\Factories;

use App\Models\ProductionUnit;
use App\Models\ProductionUnitPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionUnitPhoto>
 */
class ProductionUnitPhotoFactory extends Factory
{
    protected $model = ProductionUnitPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_unit_id' => ProductionUnit::factory(),
            'farm_id' => fn (array $attributes) => ProductionUnit::find($attributes['production_unit_id'])->farm_id,
            'campaign_id' => null,
            'activity_id' => null,
            'photo_path' => 'production-unit-photos/'.fake()->uuid().'.jpg',
            'caption' => fake()->sentence(),
            'taken_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'uploaded_by' => User::factory(),
        ];
    }
}
