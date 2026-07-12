<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switch the device-reported sample columns from TIMESTAMP to DATETIME.
 *
 * MySQL's TIMESTAMP type is timezone-sensitive: it stores values in UTC and
 * silently converts them to/from the *session* time zone on every read and
 * write. Because the queue worker, the web app, and any DB GUI can each carry
 * a different session zone, the same row appeared to "jump" by whole-hour
 * offsets depending on who read it. DATETIME stores the exact wall-clock value
 * with no implicit conversion, so every client sees the identical instant.
 *
 * Readings are always persisted in UTC (see AbstractSensorVendorClient::
 * parseTimestamp), and the app timezone is UTC, so the stored wall-clock is
 * the true UTC instant. Localisation to the viewer's zone happens in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temps', function (Blueprint $table) {
            $table->dateTime('recorded_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('temps', function (Blueprint $table) {
            $table->timestamp('recorded_at')->change();
        });
    }
};
