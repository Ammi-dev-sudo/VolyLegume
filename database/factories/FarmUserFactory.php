<?php

namespace Database\Factories;

use App\Enums\FarmRole;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmUser>
 */
class FarmUserFactory extends Factory
{
    protected $model = FarmUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'user_id' => User::factory(),
            'role' => FarmRole::Agent->value,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => FarmRole::Admin->value]);
    }

    public function responsable(): static
    {
        return $this->state(fn (array $attributes) => ['role' => FarmRole::Responsable->value]);
    }
}
