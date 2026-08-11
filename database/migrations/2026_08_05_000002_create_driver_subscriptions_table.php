<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Append-only history: one row per subscribe/renew request. Never mutated
// after creation except by the single approve/reject action. "What plan is
// this driver currently on" is deliberately NOT a stored pointer anywhere —
// it's computed as the latest row with payment_status=approved and
// (end_date is null OR end_date >= now), see SubscriptionService::
// currentSubscriptionFor(). That makes a lapsed paid plan self-heal back to
// Basic (end_date null, never expires) with zero extra bookkeeping.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans');
            // Copied from subscription_plans.commission_percentage at the
            // moment this row is approved — a later edit to the plan's live
            // percentage must not retroactively change historical earnings
            // splits for transactions already billed under this row.
            $table->decimal('commission_percentage_snapshot', 5, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->enum('payment_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('payment_method', ['wish_money'])->default('wish_money');
            $table->string('receipt_path')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_subscriptions');
    }
};
