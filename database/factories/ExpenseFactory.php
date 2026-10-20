<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'campaign_id' => null,
            'category' => fake()->randomElement(ExpenseCategory::cases())->value,
            'description' => ucfirst(fake()->words(4, true)),
            'amount' => fake()->randomFloat(2, 1000, 500000),
            'expense_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'reference' => null,
            'stock_movement_id' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
