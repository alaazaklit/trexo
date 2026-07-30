<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ITEMS = [
        ['title' => 'Driver Applications', 'route' => 'admin.driver-applications.index', 'icon' => 'voyager-list', 'order' => 28],
        ['title' => 'Contact Messages', 'route' => 'admin.contact-messages.index', 'icon' => 'voyager-mail', 'order' => 29],
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

        // Same 30-day compiled-menu cache as the earlier admin-menu migration
        // (see 2026_07_26_000012_add_admin_menu_items.php) — must be busted
        // manually after a raw DB insert.
        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('route', array_column(self::ITEMS, 'route'))->delete();
        Cache::forget('voyager_menu_admin');
    }
};
