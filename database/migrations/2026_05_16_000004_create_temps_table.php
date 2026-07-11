<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('temps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')
                ->constrained()
                ->cascadeOnDelete();

            // Core metrics shared by every supported vendor / device model.
            // Anything more exotic (CO2, PM2.5, UV, formaldehyde, etc.) is
            // preserved in the raw_payload JSON column so adding support for
            // a new device type doesn't require a schema change.
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('humidity', 6, 2)->nullable();
            $table->decimal('pressure', 9, 2)->nullable();

            // 0-100 — Jaalee's "power" field. Common across IoT vendors.
            $table->unsignedTinyInteger('battery_level')->nullable();

            // Whether the device was online at the time of sampling. Inferred
            // when the upstream API does not report it explicitly.
            $table->boolean('is_online')->default(true);

            // Device-reported timestamp. Every chart query filters / orders by
            // this column, so it gets its own index plus a composite with
            // device_id below.
            $table->timestamp('recorded_at');

            // Untouched API payload — keeps device-type-specific metrics
            // accessible without bloating the schema.
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            // Composite index supports the canonical chart query:
            //   WHERE device_id = ? AND recorded_at BETWEEN ? AND ?
            //   ORDER BY recorded_at
            $table->index(['device_id', 'recorded_at']);
            $table->index('recorded_at');

            // Idempotency: re-running the cron must not double-insert the
            // same device sample.
            $table->unique(['device_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temps');
    }
};
