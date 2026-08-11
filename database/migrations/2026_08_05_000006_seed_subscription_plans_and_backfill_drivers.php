<?php

use App\Models\Driver;
use App\Models\SubscriptionPlan;
use App\Models\Wallet;
use Illuminate\Database\Migrations\Migration;

// Seeds the two starter plans and gives every existing driver a Basic
// subscription + wallet row so nothing breaks for currently-active drivers
// once commission/earnings logic goes live. Run as a migration (not a
// manual `db:seed` step) so it happens automatically on every deploy,
// matching this repo's admin-menu-item migrations.
return new class extends Migration
{
    public function up(): void
    {
        $basic = SubscriptionPlan::firstOrCreate(
            ['slug' => 'basic'],
            ['name' => 'Basic', 'monthly_price' => 0, 'commission_percentage' => 15, 'is_active' => true]
        );

        SubscriptionPlan::firstOrCreate(
            ['slug' => 'plus'],
            ['name' => 'Plus', 'monthly_price' => 10, 'commission_percentage' => 10, 'is_active' => true]
        );

        Driver::whereDoesntHave('subscriptions')->each(function (Driver $driver) use ($basic) {
            $driver->subscriptions()->create([
                'plan_id' => $basic->id,
                'commission_percentage_snapshot' => $basic->commission_percentage,
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'payment_status' => 'approved',
                'reviewed_at' => now(),
            ]);
        });

        Driver::whereDoesntHave('wallet')->each(function (Driver $driver) {
            Wallet::create(['driver_id' => $driver->id, 'balance' => 0]);
        });
    }

    public function down(): void
    {
        // Per-driver backfill data isn't safely reversible (would delete
        // real subscription/wallet history for existing drivers), so this
        // migration is one-way — matching how other data-backfill
        // migrations in this repo (e.g. admin menu items) are additive only.
    }
};
