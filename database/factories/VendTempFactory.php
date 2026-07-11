<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\VendTemp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendTemp>
 */
class VendTempFactory extends Factory
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
            // Stored x10: -250..100 == -25.0°C..10.0°C.
            'value' => fake()->numberBetween(-250, 100),
            'type' => VendTemp::TYPE_CHAMBER,
            'is_keep' => false,
            // The (device_id, type, recorded_at) unique index means factories
            // that create many readings for one device/type need distinct
            // timestamps; each row gets a unique second-offset in the past.
            'recorded_at' => now()->subSeconds(fake()->unique()->numberBetween(0, 86_400)),
        ];
    }

    /**
     * The evaporator (T2) probe.
     */
    public function evaporator(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => VendTemp::TYPE_EVAPORATOR,
        ]);
    }

    /**
     * A reading flagged for long-term retention.
     */
    public function kept(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_keep' => true,
        ]);
    }

    /**
     * An invalid reading reported as the hardware error sentinel.
     */
    public function errored(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => VendTemp::TEMPERATURE_ERROR,
        ]);
    }
}
