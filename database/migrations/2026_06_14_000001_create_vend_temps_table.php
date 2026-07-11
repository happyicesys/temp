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
        Schema::create('vend_temps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')
                ->constrained()
                ->cascadeOnDelete();

            // Temperature stored as an integer scaled by ten (135 == 13.5°C).
            // Integer keeps the column compact and the index selective.
            $table->integer('value');

            // Probe type: 1 = chamber (T1), 2 = evaporator (T2), 3, 4.
            $table->unsignedTinyInteger('type')->default(1);

            // Flags a reading that should be retained past the normal purge.
            $table->boolean('is_keep')->default(false);

            // Device-reported sample time. Every chart query filters / orders
            // by this column, so it backs the composite index below.
            $table->timestamp('recorded_at');

            $table->timestamps();

            // Backs the canonical chart query:
            //   WHERE device_id = ? AND recorded_at BETWEEN ? AND ?
            //   ORDER BY recorded_at
            $table->index(['device_id', 'recorded_at']);
            $table->index('type');

            // Idempotency: re-ingesting the same device/type sample at the same
            // instant must not create duplicates.
            $table->unique(['device_id', 'type', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vend_temps');
    }
};
