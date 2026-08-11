<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "Subscribe Now" was instantly activating with no payment check at all —
// this adds the same manual receipt-upload + admin-approval workflow the
// driver Basic/Plus plans already use (see DriverSubscription/
// SubscriptionService::approve), instead of self-activating. New rows now
// start 'pending' and only become 'active' once an admin approves the
// uploaded receipt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_bus_premium_subscriptions', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('price');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_reason')
                ->constrained('users', 'id', 'sbps_reviewed_by_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        // Raw SQL rather than Blueprint::change() for these — Doctrine DBAL
        // (used under the hood for ->change()) doesn't have MySQL's native
        // TIMESTAMP type registered in this project's setup and throws
        // "Unknown column type timestamp" on introspection.
        DB::statement("ALTER TABLE school_bus_premium_subscriptions MODIFY status ENUM('pending', 'active', 'rejected', 'expired', 'cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE school_bus_premium_subscriptions MODIFY started_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE school_bus_premium_subscriptions MODIFY expires_at TIMESTAMP NULL');
    }

    public function down(): void
    {
        Schema::table('school_bus_premium_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['receipt_path', 'rejection_reason', 'reviewed_at']);
        });

        DB::statement("ALTER TABLE school_bus_premium_subscriptions MODIFY status ENUM('active', 'expired', 'cancelled') NOT NULL DEFAULT 'active'");
        DB::statement('ALTER TABLE school_bus_premium_subscriptions MODIFY started_at TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE school_bus_premium_subscriptions MODIFY expires_at TIMESTAMP NOT NULL');
    }
};
