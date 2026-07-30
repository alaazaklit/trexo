<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'driver_accepted_at')) {
                $table->timestamp('driver_accepted_at')->nullable()->after('status');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'late_cancellations_count')) {
                $table->unsignedInteger('late_cancellations_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'driver_accepted_at')) {
                $table->dropColumn('driver_accepted_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'late_cancellations_count')) {
                $table->dropColumn('late_cancellations_count');
            }
        });
    }
};
