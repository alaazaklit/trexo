<?php

use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\Setting;

// The original 'maps.places_provider' Setting (see
// 2026_08_19_000001_add_maps_provider_config.php) was seeded with its
// Voyager /admin/settings dropdown deliberately offering only "Google" —
// switching it to Mapbox wasn't safe yet because MapProxyController's
// Places/Geocoding endpoints had no Mapbox implementation at all. Now that
// MapboxService exists and MapProxyController dispatches on this Setting,
// this just widens the dropdown's own options; the Setting's *value* is
// left untouched (still 'google') so nothing changes behavior until an
// admin actually flips it.
return new class extends Migration
{
    public function up(): void
    {
        Setting::where('key', 'maps.places_provider')->update([
            'details' => json_encode([
                'options' => ['google' => 'Google', 'mapbox' => 'Mapbox'],
                'default' => 'google',
            ]),
        ]);
    }

    public function down(): void
    {
        Setting::where('key', 'maps.places_provider')->update([
            'details' => json_encode([
                'options' => ['google' => 'Google'],
                'default' => 'google',
            ]),
            'value' => 'google',
        ]);
    }
};
