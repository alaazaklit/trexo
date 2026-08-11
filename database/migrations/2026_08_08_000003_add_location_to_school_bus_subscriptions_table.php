<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Captured from the same map-pin picker used for order pickup/destination
// (not just typed text) so the proximity-notification check (see
// CheckSchoolBusProximity command) has real coordinates to compare the
// driver's live position against. last_proximity_notified_at debounces
// that check so a parked/slow-moving bus doesn't re-fire the "be ready"
// alert every ping.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_bus_subscriptions', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->timestamp('last_proximity_notified_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('school_bus_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'last_proximity_notified_at']);
        });
    }
};
