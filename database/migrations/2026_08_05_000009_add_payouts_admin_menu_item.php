<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ITEMS = [
        ['title' => 'Payout Requests', 'route' => 'admin.payouts.index', 'icon' => 'voyager-billing', 'order' => 34],
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

        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('route', array_column(self::ITEMS, 'route'))->delete();
        Cache::forget('voyager_menu_admin');
    }
};
