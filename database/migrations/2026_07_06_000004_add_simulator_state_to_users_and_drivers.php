<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('is_available');
            }

            if (!Schema::hasColumn('users', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (!Schema::hasColumn('users', 'heading')) {
                $table->decimal('heading', 6, 2)->nullable()->after('longitude');
            }

            if (!Schema::hasColumn('users', 'speed_kmh')) {
                $table->decimal('speed_kmh', 8, 2)->nullable()->after('heading');
            }

            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('speed_kmh');
            }

            if (!Schema::hasColumn('users', 'is_simulated')) {
                $table->boolean('is_simulated')->default(false)->after('last_seen_at');
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('rating');
            }

            if (!Schema::hasColumn('drivers', 'status')) {
                $table->string('status', 40)->default('offline')->after('is_online');
            }

            if (!Schema::hasColumn('drivers', 'vehicle_type')) {
                $table->string('vehicle_type', 50)->nullable()->after('status');
            }

            if (!Schema::hasColumn('drivers', 'speed_kmh')) {
                $table->decimal('speed_kmh', 8, 2)->default(0)->after('vehicle_type');
            }

            if (!Schema::hasColumn('drivers', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('speed_kmh');
            }

            if (!Schema::hasColumn('drivers', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (!Schema::hasColumn('drivers', 'heading')) {
                $table->decimal('heading', 6, 2)->nullable()->after('longitude');
            }

            if (!Schema::hasColumn('drivers', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('heading');
            }

            if (!Schema::hasColumn('drivers', 'ride_response_mode')) {
                $table->string('ride_response_mode', 20)->default('manual')->after('last_seen_at');
            }

            if (!Schema::hasColumn('drivers', 'workflow_state')) {
                $table->string('workflow_state', 40)->default('offline')->after('ride_response_mode');
            }

            if (!Schema::hasColumn('drivers', 'assigned_order_id')) {
                $table->unsignedBigInteger('assigned_order_id')->nullable()->after('workflow_state');
            }

            if (!Schema::hasColumn('drivers', 'assigned_trip_id')) {
                $table->unsignedBigInteger('assigned_trip_id')->nullable()->after('assigned_order_id');
            }

            if (!Schema::hasColumn('drivers', 'pickup_latitude')) {
                $table->decimal('pickup_latitude', 10, 7)->nullable()->after('assigned_trip_id');
            }

            if (!Schema::hasColumn('drivers', 'pickup_longitude')) {
                $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
            }

            if (!Schema::hasColumn('drivers', 'dropoff_latitude')) {
                $table->decimal('dropoff_latitude', 10, 7)->nullable()->after('pickup_longitude');
            }

            if (!Schema::hasColumn('drivers', 'dropoff_longitude')) {
                $table->decimal('dropoff_longitude', 10, 7)->nullable()->after('dropoff_latitude');
            }

            if (!Schema::hasColumn('drivers', 'route_points')) {
                $table->json('route_points')->nullable()->after('dropoff_longitude');
            }

            if (!Schema::hasColumn('drivers', 'route_cursor')) {
                $table->unsignedInteger('route_cursor')->default(0)->after('route_points');
            }

            if (!Schema::hasColumn('drivers', 'spawn_center_latitude')) {
                $table->decimal('spawn_center_latitude', 10, 7)->nullable()->after('route_cursor');
            }

            if (!Schema::hasColumn('drivers', 'spawn_center_longitude')) {
                $table->decimal('spawn_center_longitude', 10, 7)->nullable()->after('spawn_center_latitude');
            }

            if (!Schema::hasColumn('drivers', 'spawn_radius_km')) {
                $table->decimal('spawn_radius_km', 8, 2)->nullable()->after('spawn_center_longitude');
            }

            if (!Schema::hasColumn('drivers', 'is_simulated')) {
                $table->boolean('is_simulated')->default(false)->after('spawn_radius_km');
            }

            if (!Schema::hasColumn('drivers', 'simulation_notes')) {
                $table->text('simulation_notes')->nullable()->after('is_simulated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $columns = [
                'simulation_notes',
                'is_simulated',
                'spawn_radius_km',
                'spawn_center_longitude',
                'spawn_center_latitude',
                'route_cursor',
                'route_points',
                'dropoff_longitude',
                'dropoff_latitude',
                'pickup_longitude',
                'pickup_latitude',
                'assigned_trip_id',
                'assigned_order_id',
                'workflow_state',
                'ride_response_mode',
                'last_seen_at',
                'heading',
                'longitude',
                'latitude',
                'speed_kmh',
                'vehicle_type',
                'status',
                'is_online',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'is_simulated',
                'last_seen_at',
                'speed_kmh',
                'heading',
                'longitude',
                'latitude',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
