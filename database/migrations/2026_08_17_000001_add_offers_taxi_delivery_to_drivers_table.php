<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Per-service capability, set by the driver from their own Pricing
            // screen. Defaults to true (not nullable) so every existing driver
            // keeps receiving exactly the taxi/delivery orders they already
            // get today — nobody goes invisible just because this column
            // exists now. The matching query (MatchesDriverSchedules) also
            // treats a NULL value the same as true, for a driver row created
            // before this migration ran on their account.
            if (! Schema::hasColumn('drivers', 'offers_taxi')) {
                $table->boolean('offers_taxi')->default(true)->after('reservation_multiplier_override');
            }
            if (! Schema::hasColumn('drivers', 'offers_delivery')) {
                $table->boolean('offers_delivery')->default(true)->after('offers_taxi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            foreach (['offers_delivery', 'offers_taxi'] as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
