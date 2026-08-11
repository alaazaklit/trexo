<?php

namespace App\Services\Subscription;

use App\Models\Driver;
use App\Models\DriverSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubscriptionService
{
    /**
     * "Current plan" is computed, not stored: the latest approved row that
     * hasn't expired yet. A lapsed paid plan simply stops matching this
     * query and the driver's earlier (never-expiring) Basic row becomes the
     * latest qualifying one again — no expiry sweep needs to touch this.
     */
    public function currentSubscriptionFor(Driver $driver): ?DriverSubscription
    {
        return $driver->subscriptions()
            ->where('payment_status', 'approved')
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->orderByDesc('created_at')
            ->first();
    }

    public function commissionPercentageFor(Driver $driver): float
    {
        $current = $this->currentSubscriptionFor($driver);
        if ($current !== null && $current->commission_percentage_snapshot !== null) {
            return (float) $current->commission_percentage_snapshot;
        }

        // Only reachable if a driver somehow has no subscription history at
        // all (shouldn't happen — Driver::booted() backfills Basic on
        // creation) — fall back to Basic's live rate rather than crash.
        $basic = SubscriptionPlan::where('slug', 'basic')->first();
        return $basic !== null ? (float) $basic->commission_percentage : 15.0;
    }

    /**
     * A free plan (monthly_price == 0) has nothing to pay and nothing to
     * prove — asking for a Wish Money receipt to "renew" a $0 plan makes no
     * sense, so it's created already approved, no receipt needed. A paid
     * plan requires a receipt and stays pending until an admin reviews it.
     */
    public function subscribe(Driver $driver, SubscriptionPlan $plan, ?UploadedFile $receipt): DriverSubscription
    {
        if ((float) $plan->monthly_price <= 0) {
            $subscription = $driver->subscriptions()->create([
                'plan_id' => $plan->id,
                'payment_status' => 'pending',
                'payment_method' => 'wish_money',
            ]);

            $this->approve($subscription);

            return $subscription;
        }

        if ($receipt === null) {
            throw new \InvalidArgumentException('A payment receipt is required for a paid plan.');
        }

        $fileName = time().'_'.Str::uuid().'.'.$receipt->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('driver_subscription_receipts', $receipt, $fileName);

        return $driver->subscriptions()->create([
            'plan_id' => $plan->id,
            'payment_status' => 'pending',
            'payment_method' => 'wish_money',
            'receipt_path' => 'driver_subscription_receipts/'.$fileName,
        ]);
    }

    public function approve(DriverSubscription $subscription): void
    {
        $plan = $subscription->plan;
        $startDate = now();

        $subscription->commission_percentage_snapshot = $plan->commission_percentage;
        $subscription->payment_status = 'approved';
        $subscription->start_date = $startDate->toDateString();
        // A free plan never expires (matches Driver::booted()'s backfill) —
        // only a paid plan gets a real renewal cycle.
        $subscription->end_date = (float) $plan->monthly_price > 0
            ? $startDate->copy()->addMonth()->toDateString()
            : null;
        $subscription->renewal_date = $subscription->end_date;
        $subscription->reviewed_by = Auth::id();
        $subscription->reviewed_at = now();
        $subscription->rejection_reason = null;
        $subscription->save();
    }

    public function reject(DriverSubscription $subscription, ?string $reason): void
    {
        $subscription->payment_status = 'rejected';
        $subscription->rejection_reason = $reason;
        $subscription->reviewed_by = Auth::id();
        $subscription->reviewed_at = now();
        $subscription->save();
    }
}
