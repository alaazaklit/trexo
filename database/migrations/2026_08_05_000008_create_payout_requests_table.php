<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A driver's request to withdraw wallet balance. The requested amount is
// deducted from the wallet the moment the request is created (see
// PayoutService::request — locks the wallet row so two simultaneous
// requests can't both succeed against the same balance), not when an admin
// later approves it — approval just means "we actually paid this out via
// Wish Money"; rejection refunds the held amount back to the wallet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('payment_method', ['wish_money'])->default('wish_money');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
