<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Adds sidebar entries for the two new admin screens, folded into their
// existing dropdown parents ("School Bus" / "Financial" — see
// 2026_08_05_000021_group_admin_sidebar_menu_items.php) rather than as new
// top-level items.
return new class extends Migration
{
    private const ITEMS = [
        ['title' => 'School Bus Routes', 'route' => 'admin.school-bus-routes.index', 'icon' => 'voyager-bus', 'parent_title' => 'School Bus'],
        ['title' => 'Requests Report', 'route' => 'admin.request-reports.index', 'icon' => 'voyager-list', 'parent_title' => 'Financial'],
    ];

    public function up(): void
    {
        $menuOrder = (int) DB::table('menu_items')->where('menu_id', 1)->max('order');

        foreach (self::ITEMS as $item) {
            if (DB::table('menu_items')->where('route', $item['route'])->exists()) {
                continue;
            }

            $parent = DB::table('menu_items')
                ->where('title', $item['parent_title'])
                ->where('menu_id', 1)
                ->where('route', '')
                ->first();

            $menuOrder++;
            DB::table('menu_items')->insert([
                'menu_id' => 1,
                'title' => $item['title'],
                'url' => '',
                'target' => '_self',
                'icon_class' => $item['icon'],
                'color' => null,
                'parent_id' => $parent->id ?? null,
                'order' => $menuOrder,
                'route' => $item['route'],
                'parameters' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('route', array_column(self::ITEMS, 'route'))->delete();
        Cache::forget('voyager_menu_admin');
    }
};
