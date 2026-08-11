<?php

namespace App\Console\Commands;

use App\Models\SchoolBusSubscription;
use App\Services\SchoolBus\SchoolBusNotificationService;
use Illuminate\Console\Command;

// Drivers report their live GPS every ~15s via OrderController::
// updateDriverLocation (into users.latitude/longitude) — this command runs
// on a short schedule (see Kernel) and checks every active subscription's
// stored home coordinates against its driver's current position. A parked
// or slow-moving bus would otherwise re-trigger the alert on every single
// ping while it sits inside the radius, so a subscription is skipped if it
// was already notified within the cooldown window; that window also lets
// the alert re-arm naturally for the next trip (e.g. afternoon drop-off
// after a morning pickup).
//
// This alert is the School Bus Premium paid add-on's entire benefit — only
// subscriptions with a currently-active school_bus_premium_subscriptions
// row get it (see SchoolBusPremiumService); everyone else's bus can drive
// right past without a peep.
class CheckSchoolBusProximity extends Command
{
    private const RADIUS_METERS = 50;
    private const COOLDOWN_MINUTES = 120;

    protected $signature = 'school-bus:check-proximity';

    protected $description = 'Notify parents when their school bus driver is within 50m of the student\'s address';

    public function __construct(private readonly SchoolBusNotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $subscriptions = SchoolBusSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($query) {
                $query->whereNull('last_proximity_notified_at')
                    ->orWhere('last_proximity_notified_at', '<', now()->subMinutes(self::COOLDOWN_MINUTES));
            })
            ->whereHas('premiumSubscriptions', function ($query) {
                $query->where('status', 'active')->where('expires_at', '>=', now());
            })
            ->with(['driver.user', 'parentUser'])
            ->get();

        $notified = 0;

        foreach ($subscriptions as $subscription) {
            $driverUser = $subscription->driver?->user;
            if ($driverUser === null || $driverUser->latitude === null || $driverUser->longitude === null) {
                continue;
            }

            $distanceMeters = $this->haversineDistanceMeters(
                (float) $driverUser->latitude,
                (float) $driverUser->longitude,
                (float) $subscription->latitude,
                (float) $subscription->longitude,
            );

            if ($distanceMeters > self::RADIUS_METERS) {
                continue;
            }

            $this->notifications->sendEvent($subscription, 'proximity_alert');
            $subscription->update(['last_proximity_notified_at' => now()]);
            $notified++;
        }

        $this->info("Sent {$notified} proximity alert(s).");

        return self::SUCCESS;
    }

    private function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusMeters = 6371000;

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }
}
