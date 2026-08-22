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
        if (Schema::hasTable('otp_request_logs')) {
            return;
        }

        // Append-only audit trail of every OTP request attempt (sent,
        // blocked, or failed) — doubles as the source of truth for the
        // rolling-window rate limits in App\Services\OtpService, since
        // those need an exact count of recent attempts per phone/IP/device
        // rather than just the currently-valid code.
        Schema::create('otp_request_logs', function (Blueprint $table) {
            // The DB's default engine is MyISAM, which auto-commits every
            // write regardless of a surrounding transaction and has no row
            // locking — both fatal for App\Services\OtpService, which
            // relies on this table's writes rolling back with the rest of
            // a failed request and on MySQL's GET_LOCK for concurrency
            // safety. InnoDB is required.
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('phone', 32);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45);
            $table->string('device_id')->nullable();
            $table->string('type', 30);
            $table->string('status', 30);
            $table->text('provider_response')->nullable();
            $table->timestamps();

            $table->index(['phone', 'status', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['device_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_request_logs');
    }
};
