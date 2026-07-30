<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets a driver pick which pricing zone they primarily work in, purely so
// their own "My Pricing" screen can show a relevant default/range to set
// their override against (a Sidon-based driver shouldn't be shown
// Beirut-centric numbers as "the default"). This is NOT what the actual
// fare charged on a given ride uses — that's still resolved from the
// order's real pickup location every time (see MatchesDriverSchedules::
// findPricingZone), since a driver can end up picking up anywhere
// regardless of which zone they registered under.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('pricing_zone_id')
                ->nullable()
                ->after('reservation_multiplier_override')
                ->constrained('pricing_zones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_zone_id');
        });
    }
};
