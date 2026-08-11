<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Snapshotted at submission time rather than derived from the route's
// current monthly_price on read — so a subscription's price stays fixed
// even if the driver later edits the route price or their discount rate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_bus_subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('children_count')->default(1)->after('notes');
            $table->decimal('base_price', 10, 2)->nullable()->after('children_count');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('base_price');
            $table->decimal('total_price', 10, 2)->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('school_bus_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['children_count', 'base_price', 'discount_percent', 'total_price']);
        });
    }
};
