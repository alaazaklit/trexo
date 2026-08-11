<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One simple driver-level rate applied to every school this driver serves —
// no per-school configuration, per the product decision to keep this easy
// to manage. Total discount for a subscription = children_count * this
// rate (only once children_count >= 2), computed at submission time in
// SchoolBusSubscriptionService::submit().
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->decimal('school_bus_child_discount_percent', 5, 2)->nullable()->after('school_bus_status');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('school_bus_child_discount_percent');
        });
    }
};
