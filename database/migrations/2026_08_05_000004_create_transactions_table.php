<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The immutable financial ledger — rows are created once by
// TransactionService when an order/reservation completes and are never
// edited afterward. Reports/dashboards read from here, never recompute
// from `orders`/`reservations` directly, so a later commission-rate or
// plan change can't silently corrupt historical figures.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // Exactly one of order_id/reservation_id is set (enforced in
            // TransactionService, not the schema) — kept as two plain
            // nullable FKs rather than a polymorphic pair since nothing
            // else in this codebase uses morphs and a plain FK lets admin
            // report queries just `->with('order')`/`->with('reservation')`.
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users');
            $table->enum('service_type', ['taxi', 'delivery', 'bus']);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('driver_earnings', 10, 2);
            $table->foreignId('driver_subscription_id')->nullable()->constrained('driver_subscriptions');
            $table->enum('status', ['completed'])->default('completed');
            $table->timestamps();

            // Idempotency guard: OrderController has two independent code
            // paths that can both write status=delivered for the same
            // order (manual status update + GPS auto-advance) — the second
            // attempt to post a transaction for the same order/reservation
            // must be a silent no-op, not a duplicate financial posting.
            $table->unique('order_id');
            $table->unique('reservation_id');
            $table->index(['driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
