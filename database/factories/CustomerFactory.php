<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'code' => 'CLI-'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'type' => fake()->randomElement(CustomerType::cases())->value,
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
