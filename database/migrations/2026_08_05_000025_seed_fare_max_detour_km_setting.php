<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Models\Setting;

// Caps MatchesDriverSchedules::matchPassengerRouteToDriverRoute()'s detour
// surcharge distance — without this, a candidate driver whose registered
// route was nowhere near the trip could turn an ordinary few-dollar
// intra-city fare into $20-$30+ (detour_km scaling unbounded with however
// far that driver's route happened to be from the pickup/destination).
//
// Also fixes 'fare.detour_surcharge_per_km' / 'fare.reservation_multiplier'
// from type 'number' to 'text' — Voyager's settings page only renders an
// editable input for a fixed set of types and 'number' isn't one of them
// (same gotcha as 2026_08_05_000020_fix_wallet_debt_limit_setting_type.php),
// so both have been silently un-editable admin knobs since they were added.
return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::firstOrNew(['key' => 'fare.max_detour_km']);
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => 'Max Detour Distance (km)',
                'value' => '5',
                'details' => 'Caps how many km of route-deviation surcharge a single candidate driver can be charged for on one trip.',
                'type' => 'text',
                'order' => 9,
                'group' => 'Fare',
            ])->save();
        }

        DB::table('settings')
            ->whereIn('key', ['fare.detour_surcharge_per_km', 'fare.reservation_multiplier'])
            ->update(['type' => 'text']);
    }

    public function down(): void
    {
        Setting::where('key', 'fare.max_detour_km')->delete();

        DB::table('settings')
            ->whereIn('key', ['fare.detour_surcharge_per_km', 'fare.reservation_multiplier'])
            ->update(['type' => 'number']);
    }
};
