<?php

use App\Models\Driver;
use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\Setting;

return new class extends Migration
{
    // Follows the same Setting::firstOrNew(...)->fill([...])->save() pattern
    // as the other one-off settings migrations (e.g.
    // 2026_08_17_000003_seed_long_distance_opt_in_cutoff_setting.php) —
    // shows up automatically under Admin > Settings > Drivers, no admin
    // panel code needed beyond this.
    public function up(): void
    {
        $enabled = Setting::firstOrNew(['key' => 'driver.grace_period_enabled']);
        if (!$enabled->exists) {
            $enabled->fill([
                'display_name' => 'Enable Driver Document Grace Period',
                // Defaults on, matching the feature as built: a new driver
                // can go online immediately and gets N days to upload
                // documents before being locked. Admin can flip this off
                // at any time to go back to the old behavior (new drivers
                // start 'pending', locked until an admin approves them).
                'value' => '1',
                'details' => '',
                'type' => 'checkbox',
                'order' => 1,
                'group' => 'Drivers',
            ])->save();
        }

        $days = Setting::firstOrNew(['key' => 'driver.grace_period_days']);
        if (!$days->exists) {
            $days->fill([
                'display_name' => 'Grace Period Length (days)',
                'value' => (string) Driver::GRACE_PERIOD_DAYS,
                'details' => 'How many days a new driver may go online/accept orders before uploading ID card, license, and selfie. Only applies while the toggle above is on.',
                'type' => 'text',
                'order' => 2,
                'group' => 'Drivers',
            ])->save();
        }
    }

    public function down(): void
    {
        Setting::where('key', 'driver.grace_period_enabled')->delete();
        Setting::where('key', 'driver.grace_period_days')->delete();
    }
};
