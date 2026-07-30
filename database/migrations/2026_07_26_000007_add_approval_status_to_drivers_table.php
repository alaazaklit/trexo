<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'suspended', 'rejected'])->default('pending')->after('status');
        });

        // Existing drivers predate this workflow — backfill to approved so
        // nobody currently active gets silently locked out of going online.
        DB::table('drivers')->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }
};
