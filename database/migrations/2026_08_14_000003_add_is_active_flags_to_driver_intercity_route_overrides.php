<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A driver can opt out of serving a specific long-distance route (per
// order kind — taxi and delivery independently) even though their own
// zone touches it, e.g. they don't want the full Saida<->Beirut trip
// despite being zoned in Saida. Both default to true so every existing
// row — and every driver who's never opened the Long-Distance Routes
// screen at all — keeps today's behavior (automatically eligible via
// zone membership) until they explicitly disable a route.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_intercity_route_overrides', function (Blueprint $table) {
            $table->boolean('is_active_taxi')->default(true)->after('fixed_fare_delivery_override');
            $table->boolean('is_active_delivery')->default(true)->after('is_active_taxi');
        });
    }

    public function down(): void
    {
        Schema::table('driver_intercity_route_overrides', function (Blueprint $table) {
            $table->dropColumn(['is_active_taxi', 'is_active_delivery']);
        });
    }
};
