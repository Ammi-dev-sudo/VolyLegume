<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Farm;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'sale_id' => fn (array $attributes) => Sale::factory()
                ->create(['farm_id' => $attributes['farm_id']])->id,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'method' => fake()->randomElement(PaymentMethod::cases())->value,
            'paid_at' => now()->toDateString(),
            'reference' => null,
            'notes' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
