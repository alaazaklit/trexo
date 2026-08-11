<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// The route this menu item pointed at (admin.payouts.index) no longer
// exists — renamed to admin.commission-payments.index as part of correcting
// the wallet/payout direction for this cash-to-driver app. Updates the
// existing menu_items row in place rather than inserting a new one, so the
// sidebar position/order this driver already occupies doesn't shift.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')
            ->where('route', 'admin.payouts.index')
            ->update(['title' => 'Commission Payments', 'route' => 'admin.commission-payments.index']);

        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('route', 'admin.commission-payments.index')
            ->update(['title' => 'Payout Requests', 'route' => 'admin.payouts.index']);

        Cache::forget('voyager_menu_admin');
    }
};
