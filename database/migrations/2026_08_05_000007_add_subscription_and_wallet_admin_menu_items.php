<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ITEMS = [
        ['title' => 'Subscription Plans', 'route' => 'admin.subscription-plans.index', 'icon' => 'voyager-list', 'order' => 30],
        ['title' => 'Driver Subscriptions', 'route' => 'admin.driver-subscriptions.index', 'icon' => 'voyager-credit-card', 'order' => 31],
        ['title' => 'Wallet', 'route' => 'admin.wallet.index', 'icon' => 'voyager-money', 'order' => 32],
        ['title' => 'Financial Reports', 'route' => 'admin.financial-reports.index', 'icon' => 'voyager-bar-chart', 'order' => 33],
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

        // Voyager caches the compiled menu for 30 days and only busts it via
        // its own model save/delete flow — see the 2026_07_26_000012
        // migration this one mirrors.
        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('route', array_column(self::ITEMS, 'route'))->delete();
        Cache::forget('voyager_menu_admin');
    }
};
