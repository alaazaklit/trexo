<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets a driver set their own fare for a specific intercity route, within
// an admin-controlled range around that route's fixed_fare_taxi/delivery
// (same ± fare.override_range_percent pattern as the other per-driver
// overrides on the `drivers` table) — a driver can nudge the price for a
// route they serve often, not set anything they like. Kept in its own
// table rather than more columns on `intercity_routes` since it's a
// one-to-many relationship (many drivers, each with their own value for
// the same route), and keyed by user_id (not drivers.id) to match how
// every other driver-identifying lookup in MatchesDriverSchedules works.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_intercity_route_overrides', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('intercity_route_id')->constrained('intercity_routes')->cascadeOnDelete();
            $table->decimal('fixed_fare_taxi_override', 8, 2)->nullable();
            $table->decimal('fixed_fare_delivery_override', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'intercity_route_id'], 'driver_route_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_intercity_route_overrides');
    }
};
