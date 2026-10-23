<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Farm;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 1000, 500000);

        return [
            'farm_id' => Farm::factory(),
            'customer_id' => null,
            'reference' => 'VTE-'.fake()->unique()->bothify('########'),
            'sale_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'status' => SaleStatus::Confirmed->value,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total_amount' => $subtotal,
            'amount_paid' => 0,
            'payment_status' => PaymentStatus::Unpaid->value,
            'notes' => null,
            'recorded_by' => User::factory(),
            'cancelled_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_paid' => $attributes['total_amount'],
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }
}
