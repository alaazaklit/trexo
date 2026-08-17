<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Append-only log of every driver-dispatch attempt for an Order or
// Reservation. Orders/Reservations only ever hold the *current* driver and
// status — once a request is rejected/expired and reassigned to a new
// driver, the previous driver's outcome is overwritten (and Reservations
// don't even persist a distinct "expired" status). This table exists purely
// for admin reporting, so every attempt (and its final outcome) survives
// reassignment.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_dispatch_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->enum('request_type', ['order', 'reservation']);
            // Plain nullable columns, no DB-level FK — mirrors
            // transactions.order_id/reservation_id (2026_08_05_000004):
            // orders.id is a legacy `int unsigned` column while reservations.id
            // (and every column here) is `bigint unsigned`, so a real
            // foreignId()->constrained() on order_id fails with a
            // width-mismatch error (MySQL #3780).
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_kind')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('outcome', ['pending', 'accepted', 'rejected', 'expired', 'canceled'])->default('pending');
            $table->timestamp('sent_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'outcome']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_dispatch_logs');
    }
};
