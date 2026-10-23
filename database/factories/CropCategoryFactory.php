<?php

namespace Database\Factories;

use App\Models\CropCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CropCategory>
 */
class CropCategoryFactory extends Factory
{
    protected $model = CropCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Catégorie '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
