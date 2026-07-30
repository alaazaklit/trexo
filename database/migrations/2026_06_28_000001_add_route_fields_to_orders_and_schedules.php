<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'route_points')) {
                $table->json('route_points')->nullable()->after('status');
            }

            if (!Schema::hasColumn('orders', 'route_distance_km')) {
                $table->decimal('route_distance_km', 10, 3)->nullable()->after('route_points');
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'route_points')) {
                $table->json('route_points')->nullable()->after('destination_address');
            }

            if (!Schema::hasColumn('schedules', 'route_distance_km')) {
                $table->decimal('route_distance_km', 10, 3)->nullable()->after('route_points');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'route_distance_km')) {
                $table->dropColumn('route_distance_km');
            }

            if (Schema::hasColumn('orders', 'route_points')) {
                $table->dropColumn('route_points');
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'route_distance_km')) {
                $table->dropColumn('route_distance_km');
            }

            if (Schema::hasColumn('schedules', 'route_points')) {
                $table->dropColumn('route_points');
            }
        });
    }
};
