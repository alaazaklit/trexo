<?php

use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\Setting;

// Every driver account that already exists as of the moment this migration
// runs stays grandfathered in — automatically eligible for every long-distance
// route their zone touches, same as before this cutoff existed. Any driver
// created from this instant onward must explicitly enable each route
// themselves from their app's Long-Distance Routes screen. See
// MatchesDriverSchedules::getLongDistanceOptInCutoff() for how this value is
// read, and DriverProfileController's intercity-routes endpoint for the
// driver-facing default it drives.
return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::firstOrNew(['key' => 'driver.long_distance_opt_in_cutoff']);
        if (! $setting->exists) {
            $setting->fill([
                'display_name' => 'Long-Distance Opt-In Cutoff',
                'value' => now()->toDateTimeString(),
                'details' => 'Drivers created before this date/time stay auto-eligible for every long-distance route. Drivers created after must explicitly enable each route themselves. Clear this value to turn the requirement off entirely (back to auto-eligible for everyone).',
                'type' => 'text',
                'order' => 1,
                'group' => 'Drivers',
            ])->save();
        }
    }

    public function down(): void
    {
        Setting::where('key', 'driver.long_distance_opt_in_cutoff')->delete();
    }
};
