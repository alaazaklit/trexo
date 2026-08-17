<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks the single Google Play review/demo account so it can be
     * identified and excluded from real-user reporting if ever needed —
     * it is a normal user row otherwise, isolated purely by owning none of
     * the data other users create (every API query scopes by user id).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_demo_account')) {
                $table->boolean('is_demo_account')->default(false)->after('is_simulated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_demo_account')) {
                $table->dropColumn('is_demo_account');
            }
        });
    }
};
