<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Temp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSeeder extends Seeder
{
    /**
     * Seed a handful of devices with 24h of readings so the device list and
     * temperature charts have something to show. Idempotent: skips if any
     * device already exists.
     */
    public function run(): void
    {
        if (Device::query()->exists()) {
            return;
        }

        $customer = Customer::factory()->create(['name' => 'Demo Cold Chain']);

        Device::factory()
            ->count(4)
            ->for($customer)
            ->create()
            ->each(fn (Device $device) => $this->seedReadings($device));
    }

    /**
     * Generate 24 hours of readings at 15-minute intervals, wandering around a
     * realistic cold-chain setpoint, and cache the latest sample on the device.
     */
    private function seedReadings(Device $device): void
    {
        $temperatureSetpoint = -18.0;
        $humiditySetpoint = 55.0;

        $temperature = $temperatureSetpoint;
        $humidity = $humiditySetpoint;

        $rows = [];
        $now = Carbon::now();

        for ($step = 96; $step >= 0; $step--) {
            $temperature += random_int(-8, 8) / 10;
            $temperature = max($temperatureSetpoint - 4.0, min($temperatureSetpoint + 4.0, $temperature));

            $humidity += random_int(-15, 15) / 10;
            $humidity = max($humiditySetpoint - 10.0, min($humiditySetpoint + 10.0, $humidity));

            $rows[] = [
                'device_id' => $device->id,
                'temperature' => round($temperature, 2),
                'humidity' => round($humidity, 2),
                'is_online' => true,
                'recorded_at' => $now->copy()->subMinutes($step * 15),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Temp::query()->insert($rows);

        $latest = end($rows);

        $device->update([
            'last_reading_at' => $latest['recorded_at'],
            'last_temperature' => $latest['temperature'],
            'last_humidity' => $latest['humidity'],
        ]);
    }
}
