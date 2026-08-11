<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Command;

// A lapsed paid (Plus) subscription doesn't block a driver from work — they
// fall back to Basic automatically (see SubscriptionService::
// currentSubscriptionFor). This command only handles the UX side of that:
// a driver who's still marked online/available after their paid plan
// lapsed gets flipped offline once, so they see they need to renew rather
// than silently earning at the lower Basic rate with no signal at all.
class SweepExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:sweep-expired';

    protected $description = 'Force offline any driver whose paid subscription lapsed without renewal';

    public function __construct(private readonly SubscriptionService $subscriptions)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $candidates = Driver::whereHas('subscriptions', function ($query) {
            $query->where('payment_status', 'approved')
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', now()->toDateString())
                ->whereHas('plan', fn ($plan) => $plan->where('monthly_price', '>', 0));
        })->with(['user'])->get();

        $forcedOffline = 0;

        foreach ($candidates as $driver) {
            if ($driver->user === null) {
                continue;
            }

            $current = $this->subscriptions->currentSubscriptionFor($driver);
            $stillOnBasic = $current === null || $current->plan?->slug === 'basic';
            if (!$stillOnBasic) {
                // Renewed since — their current subscription is a valid
                // (non-lapsed) paid plan again, nothing to do here.
                continue;
            }

            if ($driver->user->is_available || $driver->is_online) {
                $driver->user->update(['is_available' => false]);
                $driver->update(['is_online' => false]);
                $forcedOffline++;
            }
        }

        $this->info("Forced {$forcedOffline} driver(s) offline after subscription lapse.");

        return self::SUCCESS;
    }
}
