<?php

namespace Database\Factories;

use App\Enums\BaseUnit;
use App\Enums\InputCategory;
use App\Models\Farm;
use App\Models\Input;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Input>
 */
class InputFactory extends Factory
{
    protected $model = Input::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(InputCategory::cases());

        return [
            'farm_id' => Farm::factory(),
            'code' => strtoupper(fake()->unique()->bothify('IN-###')),
            'name' => ucfirst(fake()->words(2, true)),
            'category' => $category->value,
            'base_unit' => fake()->randomElement(BaseUnit::cases())->value,
            'quantity' => fake()->randomFloat(3, 0, 200),
            'minimum_stock' => fake()->randomFloat(3, 0, 20),
            'default_pre_harvest_days' => $category === InputCategory::Phytosanitary
                ? fake()->numberBetween(1, 30)
                : null,
            'delay_source' => $category === InputCategory::Phytosanitary
                ? 'Notice fabricant'
                : null,
            'is_active' => true,
        ];
    }

    public function phytosanitary(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => InputCategory::Phytosanitary->value,
            'default_pre_harvest_days' => fake()->numberBetween(3, 21),
            'delay_source' => 'Notice fabricant',
        ]);
    }

    public function seed(): static
    {
        return $this->state(fn (array $attributes) => ['category' => InputCategory::Seed->value]);
    }
}
