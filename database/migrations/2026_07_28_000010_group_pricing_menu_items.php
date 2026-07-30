<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Groups Pricing Zones, Intercity Routes, and Driver Route Prices under a
// single "Prices" parent item in the admin sidebar, instead of three flat
// top-level entries — mirrors Voyager's own built-in "Tools" parent (an
// empty-url/route item whose children set parent_id to it).
return new class extends Migration
{
    private array $childRoutes = [
        'voyager.pricing-zones.index',
        'voyager.intercity-routes.index',
        'voyager.driver-intercity-route-overrides.index',
    ];

    public function up(): void
    {
        if (DB::table('menu_items')->where('title', 'Prices')->where('menu_id', 1)->exists()) {
            return;
        }

        $menuOrder = (int) DB::table('menu_items')->where('menu_id', 1)->max('order') + 1;
        $parentId = DB::table('menu_items')->insertGetId([
            'menu_id' => 1,
            'title' => 'Prices',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-price-tag',
            'color' => null,
            'parent_id' => null,
            'order' => $menuOrder,
            'route' => '',
            'parameters' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')
            ->whereIn('route', $this->childRoutes)
            ->update(['parent_id' => $parentId]);

        // Voyager caches the rendered admin sidebar tree for 30 days.
        \Illuminate\Support\Facades\Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        $parent = DB::table('menu_items')->where('title', 'Prices')->where('menu_id', 1)->first();
        if (!$parent) {
            return;
        }

        DB::table('menu_items')
            ->where('parent_id', $parent->id)
            ->update(['parent_id' => null]);

        DB::table('menu_items')->where('id', $parent->id)->delete();
    }
};
