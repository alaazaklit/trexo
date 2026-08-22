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
        if (Schema::hasColumn('users', 'otp_counter_reset_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Set to now() every time this user successfully verifies an
            // OTP — App\Services\OtpService only counts requests after this
            // timestamp toward the per-day cap, so a legitimate user who
            // verifies successfully gets a fresh daily budget instead of
            // staying penalized for the rest of the day by requests that
            // led to a successful login/reset/deletion-confirmation.
            $table->timestamp('otp_counter_reset_at')->nullable()->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('otp_counter_reset_at');
        });
    }
};
