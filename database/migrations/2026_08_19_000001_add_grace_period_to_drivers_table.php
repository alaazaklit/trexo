<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->timestamp('grace_period_ends_at')->nullable()->after('approval_status');
        });

        // 'grace_period' — a newly registered driver, allowed online without
        // documents until grace_period_ends_at. 'documents_required' — grace
        // period expired and the driver still hasn't uploaded all 3 required
        // documents (id_card/license/selfie); enforced by the
        // drivers:enforce-grace-period scheduled command, not set here.
        DB::statement("ALTER TABLE drivers MODIFY approval_status ENUM('pending', 'approved', 'suspended', 'rejected', 'grace_period', 'documents_required') DEFAULT 'pending'");

        // 'selfie' — the third required verification document (live selfie),
        // alongside the pre-existing 'id_card' and 'license' types.
        DB::statement("ALTER TABLE driver_documents MODIFY document_type ENUM('license', 'id_card', 'vehicle_registration', 'insurance', 'selfie') NOT NULL");
    }

    public function down(): void
    {
        DB::table('drivers')->where('approval_status', 'grace_period')->update(['approval_status' => 'pending']);
        DB::table('drivers')->where('approval_status', 'documents_required')->update(['approval_status' => 'pending']);
        DB::table('driver_documents')->where('document_type', 'selfie')->delete();

        DB::statement("ALTER TABLE driver_documents MODIFY document_type ENUM('license', 'id_card', 'vehicle_registration', 'insurance') NOT NULL");
        DB::statement("ALTER TABLE drivers MODIFY approval_status ENUM('pending', 'approved', 'suspended', 'rejected') DEFAULT 'pending'");

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('grace_period_ends_at');
        });
    }
};
