<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use TCG\Voyager\Models\Setting;

// Lets an admin flip which upstream provider MapProxyController::directions
// calls (Google vs Mapbox) without a new app build or deploy — see
// MapsConfigController. `places_provider` is stored alongside it for the
// same eventual purpose, but is intentionally NOT wired to anything on the
// backend yet: address.dart's address-composition logic (road/neighborhood
// merging, Lebanese "حارة" splitting, etc.) is tuned specifically against
// Google's address_components shape, and Mapbox's differently-structured
// geocoding data would need its own translation layer before it's safe to
// switch live. Kept at 'google' — MapsConfigController rejects any attempt
// to change it until that translation layer exists.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['maps.view', 'maps.manage'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where(['name' => 'Super Admin', 'guard_name' => 'web'])->first();
        $superAdmin?->givePermissionTo(['maps.view', 'maps.manage']);

        $operations = Role::where(['name' => 'Operations', 'guard_name' => 'web'])->first();
        $operations?->givePermissionTo(['maps.view', 'maps.manage']);

        $directionsProvider = Setting::firstOrNew(['key' => 'maps.directions_provider']);
        if (!$directionsProvider->exists) {
            $directionsProvider->fill([
                'display_name' => 'Directions Provider',
                // Matches the provider MapProxyController::directions already
                // called before this became switchable.
                'value' => 'mapbox',
                'details' => json_encode([
                    'options' => ['google' => 'Google', 'mapbox' => 'Mapbox'],
                    'default' => 'mapbox',
                ]),
                'type' => 'select_dropdown',
                'order' => 1,
                'group' => 'Maps',
            ])->save();
        }

        $placesProvider = Setting::firstOrNew(['key' => 'maps.places_provider']);
        if (!$placesProvider->exists) {
            $placesProvider->fill([
                'display_name' => 'Places / Geocoding Provider',
                'value' => 'google',
                'details' => json_encode([
                    'options' => ['google' => 'Google'],
                    'default' => 'google',
                ]),
                'type' => 'select_dropdown',
                'order' => 2,
                'group' => 'Maps',
            ])->save();
        }
    }

    public function down(): void
    {
        Setting::where('key', 'maps.directions_provider')->delete();
        Setting::where('key', 'maps.places_provider')->delete();

        Permission::where('name', 'maps.view')->delete();
        Permission::where('name', 'maps.manage')->delete();
    }
};
