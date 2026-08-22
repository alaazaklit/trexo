<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A driver's own distance-tiered price list — e.g. "0-5km costs 250,000 LBP
// total", "5-10km costs 400,000 LBP total" — built from the driver_price_
// brackets_page.dart screen (pick a zone hub, multi-select real destinations,
// let the app compute distance-from-hub, type one total price per tier).
// Once a driver has at least one row here, FareCalculator::bracketPricePerKm()
// takes over their real per-km rate resolution (both real trips in
// MatchesDriverSchedules::findMatchingDrivers() and the Test Price simulator
// in DriverProfileController::testPrice()) instead of the flat
// price_per_km_override — see those two call sites for the exact seam this
// slots into. A driver with zero rows here is entirely unaffected, same as
// before this table existed.
//
// Keyed by user_id (not drivers.id) to match how every other driver-owned
// override table in this app is keyed (driver_intercity_route_overrides).
// price_per_km is the only column FareCalculator ever reads — it's computed
// client-side (rounded to the nearest 1,000 LBP, then converted to USD) the
// same "driver-managed, unbounded, sanity-floor-only" way base_fare_override/
// price_per_km_override already are; tier_total_price/reference_text/
// anchor_distance_km are stored only so the builder screen can re-show what
// a bracket was based on when the driver comes back to edit it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_price_brackets', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('lower_km', 6, 2);
            $table->decimal('upper_km', 6, 2);
            $table->decimal('anchor_distance_km', 6, 2)->nullable();
            $table->string('reference_text')->nullable();
            $table->decimal('tier_total_price', 10, 2);
            $table->decimal('price_per_km', 10, 2);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_price_brackets');
    }
};
