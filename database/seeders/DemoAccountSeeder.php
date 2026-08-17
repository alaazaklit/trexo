<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates (or repairs) the single Google Play review/demo account from
 * DEMO_ACCOUNT_PHONE / DEMO_ACCOUNT_NAME. Safe to re-run at any time —
 * updateOrCreate() means it never duplicates the account, it just makes
 * sure the row matches config. Run with:
 *
 *   php artisan db:seed --class=DemoAccountSeeder
 *
 * See docs/demo-account.md.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $phone = User::normalizePhone(config('services.demo_account.phone'));

        if ($phone === '') {
            $this->command?->warn('DEMO_ACCOUNT_PHONE is not set — skipping demo account seed.');
            return;
        }

        $user = User::updateOrCreate(
            ['phone' => $phone],
            [
                'name' => config('services.demo_account.name', 'Google Play Reviewer'),
                'type' => 'seller',
                'is_demo_account' => true,
                'is_verified' => true,
                'account_status' => 'active',
            ]
        );

        $this->command?->info("Demo account ready: id={$user->id}, phone={$phone}");
    }
}
