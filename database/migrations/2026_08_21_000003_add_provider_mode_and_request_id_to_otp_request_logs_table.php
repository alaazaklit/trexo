<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('otp_request_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('otp_request_logs', 'provider_mode')) {
                // 'mock' or 'live' — lets an admin distinguish a real
                // WhatsApp send from a mocked/test one when auditing this
                // table. Null for blocked attempts, which never reach the
                // provider-dispatch step at all.
                $table->string('provider_mode', 10)->nullable()->after('status');
            }

            if (!Schema::hasColumn('otp_request_logs', 'request_id')) {
                // Correlates every log line this app writes for a single
                // requestOtp() call (App\Services\OtpService) without ever
                // needing the OTP code itself in the logs.
                $table->string('request_id', 36)->nullable()->after('provider_mode');
            }
        });

        Schema::table('otp_request_logs', function (Blueprint $table) {
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp_request_logs', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropColumn(['provider_mode', 'request_id']);
        });
    }
};
