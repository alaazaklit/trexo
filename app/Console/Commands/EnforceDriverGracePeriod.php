<?php

namespace App\Console\Commands;

use App\Models\Driver;
use Illuminate\Console\Command;

/**
 * Flips any driver whose 7-day grace period has expired without all 3
 * required documents (id_card/license/selfie) uploaded to
 * 'documents_required'. This is what makes the raw approval_status checks in
 * MatchesDriverSchedules/OrderOpsService/ReservationOpsService/
 * UsersController::updateAvailability correctly exclude a grace-expired
 * driver without each of them having to re-derive the expiry/document logic
 * themselves — see Driver::isVerificationLocked() for the single source of
 * truth this mirrors.
 */
class EnforceDriverGracePeriod extends Command
{
    protected $signature = 'drivers:enforce-grace-period';
    protected $description = 'Locks drivers whose document-upload grace period has expired without all required documents submitted';

    public function handle(): int
    {
        $expired = Driver::where('approval_status', 'grace_period')
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->get();

        $locked = 0;
        foreach ($expired as $driver) {
            if (!$driver->hasAllRequiredDocuments()) {
                $driver->approval_status = 'documents_required';
                $driver->save();
                $locked++;
            }
        }

        $this->info("Checked {$expired->count()} expired grace period(s), locked {$locked}.");

        return self::SUCCESS;
    }
}
