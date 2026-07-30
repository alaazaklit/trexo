<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')
            ->where('route', 'voyager.dashboard')
            ->update(['route' => 'admin.dashboard']);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('route', 'admin.dashboard')
            ->update(['route' => 'voyager.dashboard']);
    }
};
