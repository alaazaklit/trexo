<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A zone's "hub" — the reference point the driver-facing bracket-pricing
// builder (driver_price_brackets_page.dart) measures every candidate
// destination's driving distance from, e.g. Sidon's hub sitting at Nejmeh
// Square. Nullable: a zone with no hub configured yet simply can't power
// that screen for drivers registered to it (validated with a clear error at
// the API level), same "admin hasn't finished configuring this zone" gap
// that already exists implicitly for base_fare_taxi/per_km_taxi being
// nullable. Deliberately not reused for anything else — this is not the
// same thing as a driver's live GPS position.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_zones', 'hub_lat')) {
                $table->decimal('hub_lat', 10, 7)->nullable()->after('keywords');
            }
            if (!Schema::hasColumn('pricing_zones', 'hub_lng')) {
                $table->decimal('hub_lng', 10, 7)->nullable()->after('hub_lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pricing_zones', function (Blueprint $table) {
            $table->dropColumn(['hub_lat', 'hub_lng']);
        });
    }
};
