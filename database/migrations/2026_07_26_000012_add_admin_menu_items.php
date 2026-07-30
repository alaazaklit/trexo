<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ITEMS = [
        ['title' => 'Audit Logs', 'route' => 'admin.audit-logs.index', 'icon' => 'voyager-list', 'order' => 22],
        ['title' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'voyager-person', 'order' => 23],
        ['title' => 'Driver Management', 'route' => 'admin.drivers.index', 'icon' => 'voyager-car', 'order' => 24],
        ['title' => 'Vehicle Categories', 'route' => 'admin.vehicle-categories.index', 'icon' => 'voyager-categories', 'order' => 25],
        ['title' => 'Reservations', 'route' => 'admin.reservations.index', 'icon' => 'voyager-calendar', 'order' => 26],
        ['title' => 'Driver Simulator', 'route' => 'admin.driver-simulator.index', 'icon' => 'voyager-map-marker', 'order' => 27],
    ];

    public function up(): void
    {
        foreach (self::ITEMS as $item) {
            if (DB::table('menu_items')->where('route', $item['route'])->exists()) {
                continue;
            }

            DB::table('menu_items')->insert([
                'menu_id' => 1,
                'title' => $item['title'],
                'url' => '',
                'target' => '_self',
                'icon_class' => $item['icon'],
                'color' => null,
                'parent_id' => null,
                'order' => $item['order'],
                'route' => $item['route'],
                'parameters' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Voyager caches the compiled menu for 30 days (Menu::get(),
        // vendor/tcg/voyager/src/Models/Menu.php:56) and only busts it via
        // its own model save/delete flow — a raw DB insert like this one
        // never touches that, so the new items would stay invisible in the
        // sidebar until the cache happened to expire on its own.
        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('route', array_column(self::ITEMS, 'route'))->delete();
        Cache::forget('voyager_menu_admin');
    }
};
