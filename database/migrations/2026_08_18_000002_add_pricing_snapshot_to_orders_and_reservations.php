<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Every order/reservation already freezes its final `price` at ChooseDriver/
// ChooseReservationDriver time (a later driver-pricing change never touches
// it) — but nothing about *how* that price was built (which zone, which
// rate, whether an out-of-zone surcharge applied) was ever kept. These
// columns are that breakdown, snapshotted the same way and at the same
// moment `price` already is: no pricing_zones/drivers FK, since this must
// keep reading correctly even if that zone or the driver's own settings
// change or are deleted later — it's a historical record, not a live
// reference.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'base_fare')) {
                $table->decimal('base_fare', 8, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('orders', 'per_km_rate')) {
                $table->decimal('per_km_rate', 8, 2)->nullable()->after('base_fare');
            }
            if (!Schema::hasColumn('orders', 'effective_per_km_rate')) {
                $table->decimal('effective_per_km_rate', 8, 2)->nullable()->after('per_km_rate');
            }
            if (!Schema::hasColumn('orders', 'out_of_zone_percent')) {
                $table->decimal('out_of_zone_percent', 5, 2)->nullable()->after('effective_per_km_rate');
            }
            if (!Schema::hasColumn('orders', 'is_out_of_zone')) {
                $table->boolean('is_out_of_zone')->default(false)->after('out_of_zone_percent');
            }
            if (!Schema::hasColumn('orders', 'pricing_zone_id')) {
                $table->unsignedBigInteger('pricing_zone_id')->nullable()->after('is_out_of_zone');
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'base_fare')) {
                $table->decimal('base_fare', 8, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('reservations', 'per_km_rate')) {
                $table->decimal('per_km_rate', 8, 2)->nullable()->after('base_fare');
            }
            if (!Schema::hasColumn('reservations', 'effective_per_km_rate')) {
                $table->decimal('effective_per_km_rate', 8, 2)->nullable()->after('per_km_rate');
            }
            if (!Schema::hasColumn('reservations', 'out_of_zone_percent')) {
                $table->decimal('out_of_zone_percent', 5, 2)->nullable()->after('effective_per_km_rate');
            }
            if (!Schema::hasColumn('reservations', 'is_out_of_zone')) {
                $table->boolean('is_out_of_zone')->default(false)->after('out_of_zone_percent');
            }
            if (!Schema::hasColumn('reservations', 'pricing_zone_id')) {
                $table->unsignedBigInteger('pricing_zone_id')->nullable()->after('is_out_of_zone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'pricing_zone_id',
                'is_out_of_zone',
                'out_of_zone_percent',
                'effective_per_km_rate',
                'per_km_rate',
                'base_fare',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            foreach ([
                'pricing_zone_id',
                'is_out_of_zone',
                'out_of_zone_percent',
                'effective_per_km_rate',
                'per_km_rate',
                'base_fare',
            ] as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
