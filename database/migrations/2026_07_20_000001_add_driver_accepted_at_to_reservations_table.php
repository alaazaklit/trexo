<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Set the moment the driver actually accepts — distinct from
            // merely being assigned. Mirrors orders.driver_accepted_at: lets
            // the app tell "a driver was proposed" apart from "a driver
            // actually engaged" (e.g. whether call/chat should be offered),
            // and survives a later cancellation instead of being cleared.
            if (!Schema::hasColumn('reservations', 'driver_accepted_at')) {
                $table->timestamp('driver_accepted_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'driver_accepted_at')) {
                $table->dropColumn('driver_accepted_at');
            }
        });
    }
};
