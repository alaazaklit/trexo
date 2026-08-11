<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Separate from `approval_status` (taxi/delivery) on purpose — a driver can
// already be an approved taxi driver and still need independent vetting
// before running a school bus. `null` means "hasn't opted into school bus".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->enum('school_bus_status', ['pending', 'approved', 'suspended', 'rejected'])->nullable()->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('school_bus_status');
        });
    }
};
