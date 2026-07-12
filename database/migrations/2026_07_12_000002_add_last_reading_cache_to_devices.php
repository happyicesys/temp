<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Denormalised copy of the most recent reading's metrics, mirroring
            // the existing last_polled_at / last_reading_at cache pointers.
            // Lets the device list render the latest temperature and humidity
            // without a per-row subquery against the temps table.
            $table->decimal('last_temperature', 6, 2)->nullable()->after('last_reading_at');
            $table->decimal('last_humidity', 6, 2)->nullable()->after('last_temperature');
        });

        $this->backfillFromLatestReadings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['last_temperature', 'last_humidity']);
        });
    }

    /**
     * Seed the new cache columns from each device's most recent temps row so
     * existing devices show their latest reading immediately after deploy.
     */
    private function backfillFromLatestReadings(): void
    {
        DB::table('devices')
            ->select('id')
            ->orderBy('id')
            ->chunk(200, function ($devices): void {
                foreach ($devices as $device) {
                    $latest = DB::table('temps')
                        ->where('device_id', $device->id)
                        ->orderByDesc('recorded_at')
                        ->first(['temperature', 'humidity']);

                    if ($latest === null) {
                        continue;
                    }

                    DB::table('devices')
                        ->where('id', $device->id)
                        ->update([
                            'last_temperature' => $latest->temperature,
                            'last_humidity' => $latest->humidity,
                        ]);
                }
            });
    }
};
