<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Device;
use App\Models\VendTemp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSeeder extends Seeder
{
    /**
     * Seed a handful of devices with 24h of T1/T2 readings so the device list
     * and temperature charts have something to show. Idempotent: skips if any
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
     * Generate 24 hours of readings at 15-minute intervals for the chamber
     * (T1) and evaporator (T2) probes, wandering around realistic setpoints.
     */
    private function seedReadings(Device $device): void
    {
        $baselines = [
            VendTemp::TYPE_CHAMBER => -180,    // -18.0°C
            VendTemp::TYPE_EVAPORATOR => -230, // -23.0°C
        ];

        $rows = [];
        $now = Carbon::now();

        foreach ($baselines as $type => $baseline) {
            $value = $baseline;

            for ($step = 96; $step >= 0; $step--) {
                $value += random_int(-8, 8);
                $value = max($baseline - 40, min($baseline + 40, $value));

                $rows[] = [
                    'device_id' => $device->id,
                    'type' => $type,
                    'value' => $value,
                    'is_keep' => false,
                    'recorded_at' => $now->copy()->subMinutes($step * 15),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        VendTemp::query()->insert($rows);

        $device->update(['last_reading_at' => $now]);
    }
}
