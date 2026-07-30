<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pickup_notified_at')) {
                $table->timestamp('pickup_notified_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('orders', 'destination_notified_at')) {
                $table->timestamp('destination_notified_at')->nullable()->after('pickup_notified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'destination_notified_at')) {
                $table->dropColumn('destination_notified_at');
            }

            if (Schema::hasColumn('orders', 'pickup_notified_at')) {
                $table->dropColumn('pickup_notified_at');
            }
        });
    }
};
