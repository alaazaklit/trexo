<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Lets a driver charge more per km once a trip's destination leaves their
// own chosen working zone (drivers.pricing_zone_id) — same override pattern
// as base_fare_override/price_per_km_override: null means "use the admin
// default" (fare.out_of_zone_percent_default), a value means the driver's
// own choice, bounded server-side against fare.out_of_zone_percent_max
// (see MatchesDriverSchedules::getOverrideBounds).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'out_of_zone_percent_override')) {
                $table->decimal('out_of_zone_percent_override', 5, 2)->nullable()->after('reservation_multiplier_override');
            }
        });

        // Seeded as 'text' from the start — 'number' is a known Voyager
        // settings-page gotcha (no input renders for it at all), already hit
        // twice before by other fare.* keys (see 2026_08_05_000020 and
        // 2026_08_05_000025).
        $existing = DB::table('settings')->whereIn('key', [
            'fare.out_of_zone_percent_default',
            'fare.out_of_zone_percent_max',
        ])->pluck('key')->all();

        $rows = [
            [
                'key' => 'fare.out_of_zone_percent_default',
                'display_name' => 'Out-of-Zone Per-KM Increase, Default (%)',
                'value' => '20',
                'details' => 'Applied to a driver\'s per-km rate when a trip\'s destination is outside their chosen working zone and they haven\'t set their own percentage.',
                'type' => 'text',
                'order' => 10,
                'group' => 'Fare',
            ],
            [
                'key' => 'fare.out_of_zone_percent_max',
                'display_name' => 'Out-of-Zone Per-KM Increase, Max Allowed (%)',
                'value' => '100',
                'details' => 'The highest out-of-zone percentage a driver is allowed to set for themselves.',
                'type' => 'text',
                'order' => 11,
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
            if (Schema::hasColumn('drivers', 'out_of_zone_percent_override')) {
                $table->dropColumn('out_of_zone_percent_override');
            }
        });

        DB::table('settings')->whereIn('key', [
            'fare.out_of_zone_percent_default',
            'fare.out_of_zone_percent_max',
        ])->delete();
    }
};
