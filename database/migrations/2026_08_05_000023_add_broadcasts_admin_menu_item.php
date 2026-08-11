<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('menu_items')->where('route', 'admin.broadcasts.index')->exists()) {
            return;
        }

        $menuOrder = (int) DB::table('menu_items')->where('menu_id', 1)->max('order') + 1;

        DB::table('menu_items')->insert([
            'menu_id' => 1,
            'title' => 'Broadcast',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-bell',
            'color' => null,
            'parent_id' => null,
            'order' => $menuOrder,
            'route' => 'admin.broadcasts.index',
            'parameters' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')->where('route', 'admin.broadcasts.index')->delete();
        Cache::forget('voyager_menu_admin');
    }
};
