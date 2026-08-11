<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Groups several flat top-level sidebar entries into dropdown parents —
// mirrors the existing "Prices" parent (see
// 2026_07_28_000010_group_pricing_menu_items.php) rather than inventing a
// new pattern, since Voyager already renders any empty-url/route item with
// children as a dropdown.
return new class extends Migration
{
    private array $groups = [
        'Financial' => [
            'icon' => 'voyager-billing',
            'routes' => [
                'admin.subscription-plans.index',
                'admin.driver-subscriptions.index',
                'admin.wallet.index',
                'admin.financial-reports.index',
                'admin.commission-payments.index',
            ],
        ],
        // Named "Driver Ops"/"Fleet" rather than "Drivers"/"Vehicles" — those
        // exact titles already belong to pre-existing leaf items (Voyager's
        // own stock BREAD entries for the drivers/vehicles tables), and a
        // parent sharing its child's exact title breaks the route='' guard
        // below the moment either one is looked up by title alone.
        'Driver Ops' => [
            'icon' => 'voyager-person',
            'routes' => [
                'voyager.drivers.index',
                'admin.drivers.index',
                'admin.driver-applications.index',
                'voyager.driver-gallery-images.index',
                'admin.driver-simulator.index',
            ],
        ],
        'Fleet' => [
            'icon' => 'voyager-car',
            'routes' => [
                'voyager.vehicles.index',
                'admin.vehicle-categories.index',
            ],
        ],
        'Trips & Orders' => [
            'icon' => 'voyager-map',
            'routes' => [
                'voyager.trips.index',
                'voyager.orders.index',
                'admin.reservations.index',
                'voyager.schedules.index',
            ],
        ],
        'Trip Data' => [
            'icon' => 'voyager-list',
            'routes' => [
                'voyager.ratings.index',
                'voyager.addresses.index',
                'voyager.verification-codes.index',
            ],
        ],
        'School Bus' => [
            'icon' => 'voyager-bus',
            'routes' => [
                'admin.schools.index',
                'admin.school-bus-subscriptions.index',
            ],
        ],
        'People' => [
            'icon' => 'voyager-people',
            'routes' => [
                'voyager.users.index',
                'admin.customers.index',
            ],
        ],
    ];

    public function up(): void
    {
        $menuOrder = (int) DB::table('menu_items')->where('menu_id', 1)->max('order');

        foreach ($this->groups as $title => $group) {
            // Matching on title alone would collide with the pre-existing leaf
            // items literally titled "Drivers"/"Vehicles" (Voyager's own stock
            // BREAD entries) — route='' distinguishes an actual dropdown
            // parent (created below with an empty route) from those.
            if (DB::table('menu_items')->where('title', $title)->where('menu_id', 1)->where('route', '')->exists()) {
                continue;
            }

            $menuOrder++;
            $parentId = DB::table('menu_items')->insertGetId([
                'menu_id' => 1,
                'title' => $title,
                'url' => '',
                'target' => '_self',
                'icon_class' => $group['icon'],
                'color' => null,
                'parent_id' => null,
                'order' => $menuOrder,
                'route' => '',
                'parameters' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('menu_items')
                ->whereIn('route', $group['routes'])
                ->update(['parent_id' => $parentId]);
        }

        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        foreach (array_keys($this->groups) as $title) {
            $parent = DB::table('menu_items')->where('title', $title)->where('menu_id', 1)->where('route', '')->first();
            if (!$parent) {
                continue;
            }

            DB::table('menu_items')->where('parent_id', $parent->id)->update(['parent_id' => null]);
            DB::table('menu_items')->where('id', $parent->id)->delete();
        }

        Cache::forget('voyager_menu_admin');
    }
};
