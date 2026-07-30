<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A fixed fare for a specific pickup-zone <-> destination-zone pair (e.g.
// Beirut & Mount Lebanon <-> Sidon = $32), for long trips where a straight
// base_fare + distance_km * per_km calculation overshoots what's actually
// a well-known, competitively-priced route. Matched in both directions —
// admins only need to register each city-pair once. When a match exists
// for the ride's kind, it replaces the base_fare + per-km distance
// component entirely (detour surcharge and the reservation round-trip
// multiplier still apply on top — see MatchesDriverSchedules).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intercity_routes', function (Blueprint $table) {
            // The DB's default engine is MyISAM, which doesn't support real
            // foreign keys — from_zone_id/to_zone_id reference pricing_zones.
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('from_zone_id')->constrained('pricing_zones')->cascadeOnDelete();
            $table->foreignId('to_zone_id')->constrained('pricing_zones')->cascadeOnDelete();
            $table->decimal('fixed_fare_taxi', 8, 2)->nullable();
            $table->decimal('fixed_fare_delivery', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intercity_routes');
    }
};
