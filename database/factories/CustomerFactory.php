<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('CUST-####'),
            'name' => fake()->company(),
            'location' => fake()->city(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->e164PhoneNumber(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the customer is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
