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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            // Which hardware vendor this device belongs to. The string maps to
            // a concrete SensorVendorClient implementation (e.g. "jaalee").
            // Keeping this column makes it cheap to support a second
            // manufacturer later without a schema change.
            $table->string('vendor')->default('jaalee');

            // The identifier used to query the vendor's API for this unit.
            // Unique within the scope of a single vendor.
            $table->string('vendor_device_id');

            // Optional manufacturer / asset identifiers.
            $table->string('serial_number')->nullable();
            $table->string('asset_code')->nullable();
            $table->string('model')->nullable();

            $table->string('name');
            $table->string('location')->nullable();

            // A device always belongs to a customer (the cold-room owner) and
            // optionally to an operator (a service / logistics company).
            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('operator_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Polling control.
            $table->boolean('is_active')->default(true);

            // Per-device alert thresholds (nullable = no alert configured).
            $table->decimal('alert_low_temp', 6, 2)->nullable();
            $table->decimal('alert_high_temp', 6, 2)->nullable();

            // Notification recipients. Comma-separated list keeps things flat
            // for the foundation pass; a recipients table can come later if
            // routing logic grows.
            $table->string('alert_emails')->nullable();
            $table->string('alert_phones')->nullable();

            // Cached pointers for fast dashboards / "fairest next poll" logic.
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('last_reading_at')->nullable();

            $table->timestamps();

            // Indexes tuned for the dashboard and the polling scheduler:
            //  - (is_active, last_polled_at) lets the scheduler grab the next
            //    batch of devices to poll in a single index scan.
            //  - customer_id / operator_id support listing pages filtered by
            //    those entities.
            $table->index('is_active');
            $table->index(['is_active', 'last_polled_at']);
            $table->index('customer_id');
            $table->index('operator_id');

            // Vendor + vendor_device_id uniquely identify a physical unit
            // (and is the canonical lookup when reconciling API responses).
            $table->unique(['vendor', 'vendor_device_id']);
            $table->index('vendor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
