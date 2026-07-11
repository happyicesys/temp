<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor' => 'jaalee',
            'vendor_device_id' => fake()->unique()->numerify('JAALEE-#######'),
            'serial_number' => fake()->bothify('SN-####??'),
            'asset_code' => fake()->bothify('B-UFI2-####'),
            'model' => 'UFI2',
            'name' => fake()->words(2, true),
            'location' => fake()->city(),
            'customer_id' => Customer::factory(),
            'operator_id' => null,
            'is_active' => true,
            'alert_low_temp' => -25.00,
            'alert_high_temp' => -15.00,
            'alert_emails' => fake()->safeEmail(),
            'alert_phones' => null,
            'last_polled_at' => null,
            'last_reading_at' => null,
        ];
    }

    /**
     * Indicate that the device is deactivated (skipped by the scheduler).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Attach an operator to this device.
     */
    public function withOperator(?Operator $operator = null): static
    {
        return $this->state(fn (array $attributes) => [
            'operator_id' => $operator?->getKey() ?? Operator::factory(),
        ]);
    }
}
