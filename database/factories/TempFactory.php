<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Temp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Temp>
 */
class TempFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'temperature' => fake()->randomFloat(2, -25, 10),
            'humidity' => fake()->randomFloat(2, 30, 80),
            'pressure' => fake()->randomFloat(2, 990, 1030),
            'battery_level' => fake()->numberBetween(20, 100),
            'is_online' => true,
            // The (device_id, recorded_at) unique index means factories that
            // create many readings for one device need distinct timestamps.
            // Each row gets a unique second-offset in the recent past.
            'recorded_at' => now()->subSeconds(fake()->unique()->numberBetween(0, 86_400)),
            'raw_payload' => null,
        ];
    }

    /**
     * Indicate the device was offline when this sample was taken.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => false,
        ]);
    }
}
