<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Nullable — null means "use the global fare.* setting". Lets an
            // individual driver be priced differently (e.g. a premium/luxury
            // vehicle) without touching the global rates everyone else uses.
            if (!Schema::hasColumn('drivers', 'base_fare_override')) {
                $table->decimal('base_fare_override', 8, 2)->nullable()->after('rating');
            }
            if (!Schema::hasColumn('drivers', 'price_per_km_override')) {
                $table->decimal('price_per_km_override', 8, 2)->nullable()->after('base_fare_override');
            }
            if (!Schema::hasColumn('drivers', 'detour_surcharge_override')) {
                $table->decimal('detour_surcharge_override', 8, 2)->nullable()->after('price_per_km_override');
            }
            if (!Schema::hasColumn('drivers', 'reservation_multiplier_override')) {
                $table->decimal('reservation_multiplier_override', 8, 2)->nullable()->after('detour_surcharge_override');
            }
        });

        // New global fare settings, alongside the existing fare.* rows —
        // same table/shape so they show up in whatever admin screen already
        // manages base_taxi/per_km_taxi/etc.
        $existing = DB::table('settings')->whereIn('key', [
            'fare.detour_surcharge_per_km',
            'fare.reservation_multiplier',
        ])->pluck('key')->all();

        $rows = [
            [
                'key' => 'fare.detour_surcharge_per_km',
                'display_name' => 'Detour Surcharge Per Km',
                'value' => '0.25',
                'details' => '',
                'type' => 'number',
                'order' => 7,
                'group' => 'Fare',
            ],
            [
                'key' => 'fare.reservation_multiplier',
                'display_name' => 'Reservation Round-Trip Multiplier',
                'value' => '2.00',
                'details' => '',
                'type' => 'number',
                'order' => 8,
                'group' => 'Fare',
            ],
        ];

        foreach ($rows as $row) {
            if (!in_array($row['key'], $existing, true)) {
                DB::table('settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            foreach ([
                'reservation_multiplier_override',
                'detour_surcharge_override',
                'price_per_km_override',
                'base_fare_override',
            ] as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::table('settings')->whereIn('key', [
            'fare.detour_surcharge_per_km',
            'fare.reservation_multiplier',
        ])->delete();
    }
};
