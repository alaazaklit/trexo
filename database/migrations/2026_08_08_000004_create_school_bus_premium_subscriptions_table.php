<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paid add-on gating the proximity "driver is nearby" alert (see
// CheckSchoolBusProximity) behind a Monthly/Yearly plan, scoped to one
// school_bus_subscription (one student/route) rather than the parent
// account as a whole — a parent with kids on different routes subscribes
// to each independently. There is no payment gateway anywhere in this app
// today, so "subscribing" just creates an active row directly (see
// SchoolBusPremiumService::subscribe) — no receipt upload, no admin
// approval step, unlike the driver Basic/Plus plans.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_bus_premium_subscriptions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            // Custom, short FK constraint names — the auto-generated ones
            // (table_column_foreign) exceed MySQL's 64-char identifier limit
            // on this table name.
            $table->foreignId('school_bus_subscription_id');
            $table->foreign('school_bus_subscription_id', 'sbps_subscription_fk')
                ->references('id')->on('school_bus_subscriptions')->onDelete('cascade');
            $table->foreignId('parent_user_id');
            $table->foreign('parent_user_id', 'sbps_parent_user_fk')
                ->references('id')->on('users')->onDelete('cascade');
            $table->enum('plan', ['monthly', 'yearly']);
            $table->decimal('price', 8, 2);
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['school_bus_subscription_id', 'status'], 'sbps_subscription_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_bus_premium_subscriptions');
    }
};
