<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Nests under the existing "School Bus" dropdown parent (see
// 2026_08_05_000021_group_admin_sidebar_menu_items.php) rather than sitting
// flat at the top level — same technique that migration uses to re-parent
// items after insert.
return new class extends Migration
{
    private const ROUTE = 'admin.school-bus-premium-subscriptions.index';

    public function up(): void
    {
        if (!DB::table('menu_items')->where('route', self::ROUTE)->exists()) {
            $order = (int) DB::table('menu_items')->where('menu_id', 1)->max('order') + 1;

            DB::table('menu_items')->insert([
                'menu_id' => 1,
                'title' => 'School Bus Premium',
                'url' => '',
                'target' => '_self',
                'icon_class' => 'voyager-diamond',
                'color' => null,
                'parent_id' => null,
                'order' => $order,
                'route' => self::ROUTE,
                'parameters' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $parent = DB::table('menu_items')->where('title', 'School Bus')->where('menu_id', 1)->where('route', '')->first();
        if ($parent) {
            DB::table('menu_items')->where('route', self::ROUTE)->update(['parent_id' => $parent->id]);
        }

        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')->where('route', self::ROUTE)->delete();
        Cache::forget('voyager_menu_admin');
    }
};
