<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\School;
use App\Models\SchoolBusPremiumSubscription;
use App\Models\SchoolBusRoute;
use App\Models\SchoolBusSubscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives the Google Play demo account (DEMO_ACCOUNT_PHONE) a genuinely
 * active School Bus Premium subscription, so a reviewer can see the
 * app's paid content without a real cash payment — there is no payment
 * gateway in this app; premium is normally unlocked by a parent uploading
 * a receipt and an admin approving it (see SchoolBusPremiumService).
 *
 * Everything this creates (driver, school, route) is synthetic and
 * invisible to real users: the school and route are both `is_active =
 * false` and the driver's `school_bus_status` is never 'approved', so
 * none of it can surface in the real school-browsing or driver-listing
 * endpoints (both filter on those flags). The demo parent's own "my
 * subscriptions" view fetches by parent_user_id directly, so it sees this
 * regardless.
 *
 * Safe to re-run — every row is looked up by a stable key first.
 *
 *   php artisan db:seed --class=DemoSchoolBusPremiumSeeder
 */
class DemoSchoolBusPremiumSeeder extends Seeder
{
    private const DEMO_DRIVER_PHONE = '00000001';

    public function run(): void
    {
        $parentPhone = User::normalizePhone(config('services.demo_account.phone'));

        if ($parentPhone === '') {
            $this->command?->warn('DEMO_ACCOUNT_PHONE is not set — skipping demo premium seed.');
            return;
        }

        $parent = User::where('phone', $parentPhone)->first();

        if (!$parent) {
            $this->command?->warn('Demo account does not exist yet — run DemoAccountSeeder first.');
            return;
        }

        $driverUser = User::updateOrCreate(
            ['phone' => self::DEMO_DRIVER_PHONE],
            [
                'name' => 'Demo School Bus Driver',
                'type' => 'driver',
                'is_demo_account' => true,
                'is_verified' => true,
                'account_status' => 'active',
            ]
        );

        $driver = Driver::firstOrCreate(
            ['user_id' => $driverUser->id],
            [
                // Deliberately left un-approved on both fronts — keeps this
                // driver out of real taxi/delivery matching AND out of the
                // real "approved drivers for school X" listing.
                'approval_status' => 'pending',
                'school_bus_status' => 'pending',
                'status' => 'offline',
            ]
        );

        $school = School::firstOrCreate(
            ['name' => 'Demo School (Internal Test Only)'],
            ['area' => 'Demo', 'is_active' => false]
        );

        $route = SchoolBusRoute::firstOrCreate(
            ['driver_id' => $driver->id, 'school_id' => $school->id],
            ['pickup_area' => 'Demo Pickup Area', 'monthly_price' => 10.00, 'is_active' => false]
        );

        $subscription = SchoolBusSubscription::updateOrCreate(
            ['route_id' => $route->id, 'parent_user_id' => $parent->id],
            [
                'driver_id' => $driver->id,
                'student_name' => 'Demo Student',
                'parent_name' => $parent->name ?: config('services.demo_account.name', 'Google Play Reviewer'),
                'phone' => $parentPhone,
                'address' => 'Demo Address, Demo Area',
                'status' => 'active',
                'accepted_at' => now(),
                'children_count' => 1,
                'base_price' => 10.00,
                'discount_percent' => 0,
                'total_price' => 10.00,
            ]
        );

        SchoolBusPremiumSubscription::updateOrCreate(
            ['school_bus_subscription_id' => $subscription->id, 'parent_user_id' => $parent->id],
            [
                'plan' => 'yearly',
                'price' => SchoolBusPremiumSubscription::PLANS['yearly']['price'],
                'receipt_path' => null,
                'status' => 'active',
                'started_at' => now(),
                // Five years out — this is a system-granted review record,
                // not a real payment, so it should simply never lapse.
                'expires_at' => now()->addYears(5),
                'rejection_reason' => null,
                'reviewed_at' => now(),
            ]
        );

        $this->command?->info("Demo School Bus Premium subscription ready for parent id={$parent->id} (subscription id={$subscription->id}).");
    }
}
