<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
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
            // Last online/offline state observed by the scheduled status check
            // (devices:refresh-status). The device list derives the live badge
            // straight from last_reading_at, but persisting the observed state
            // lets the scheduler detect the offline transition — the hook point
            // for future offline alerts — without re-alerting every minute.
            $table->boolean('is_online')->default(false)->after('last_reading_at');

            // When the device most recently dropped offline; null while online.
            $table->timestamp('went_offline_at')->nullable()->after('is_online');
        });

        $this->backfillOnlineState();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'went_offline_at']);
        });
    }

    /**
     * Seed is_online from each device's current reading freshness so the first
     * scheduled run doesn't spuriously report every device as just-changed.
     */
    private function backfillOnlineState(): void
    {
        $cutoff = Carbon::now()->subSeconds((int) config('sensors.offline_after_seconds', 600));

        DB::table('devices')
            ->whereNotNull('last_reading_at')
            ->where('last_reading_at', '>=', $cutoff)
            ->update(['is_online' => true]);
    }
};
