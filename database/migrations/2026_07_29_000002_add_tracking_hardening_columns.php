<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Last reported GPS accuracy (metres, geolocator's
            // Position.accuracy) — drives the adaptive arrival radius.
            if (!Schema::hasColumn('users', 'location_accuracy_m')) {
                $table->decimal('location_accuracy_m', 8, 2)->nullable()->after('last_seen_at');
            }

            // Client-side capture timestamp (epoch ms) of the last location
            // ping actually applied — lets updateDriverLocation recognise
            // and ignore a stale resend from the offline queue (the same
            // reading retried after its earlier response was lost).
            if (!Schema::hasColumn('users', 'last_location_client_ts')) {
                $table->unsignedBigInteger('last_location_client_ts')->nullable()->after('location_accuracy_m');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            // Consecutive GPS pings landed inside the current leg's arrival
            // radius — picked_up/delivered only auto-fire once this reaches
            // the configured threshold, not on a single in-radius ping.
            if (!Schema::hasColumn('orders', 'arrival_confirmation_count')) {
                $table->unsignedInteger('arrival_confirmation_count')->default(0)->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_location_client_ts')) {
                $table->dropColumn('last_location_client_ts');
            }
            if (Schema::hasColumn('users', 'location_accuracy_m')) {
                $table->dropColumn('location_accuracy_m');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'arrival_confirmation_count')) {
                $table->dropColumn('arrival_confirmation_count');
            }
        });
    }
};
