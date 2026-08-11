<?php

namespace App\Services\SchoolBus;

use App\Models\SchoolBusPremiumSubscription;
use App\Models\SchoolBusSubscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolBusPremiumService
{
    public function currentFor(SchoolBusSubscription $subscription): ?SchoolBusPremiumSubscription
    {
        return $subscription->premiumSubscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();
    }

    // The most recent request regardless of status — lets the parent-facing
    // status screen show "Pending"/"Rejected" too, not just a currently
    // active plan.
    public function latestFor(SchoolBusSubscription $subscription): ?SchoolBusPremiumSubscription
    {
        return $subscription->premiumSubscriptions()->latest('id')->first();
    }

    // No payment gateway exists in this app — same manual-review pattern as
    // the driver Basic/Plus plans (see SubscriptionService::subscribe):
    // parent uploads a receipt, row starts 'pending', an admin approves or
    // rejects it from the admin panel (see approve()/reject() below).
    public function subscribe(SchoolBusSubscription $subscription, User $parent, string $plan, UploadedFile $receipt): SchoolBusPremiumSubscription
    {
        if (!array_key_exists($plan, SchoolBusPremiumSubscription::PLANS)) {
            throw new \InvalidArgumentException("Invalid school bus premium plan: {$plan}");
        }

        $meta = SchoolBusPremiumSubscription::PLANS[$plan];

        $fileName = time().'_'.Str::uuid().'.'.$receipt->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('school_bus_premium_receipts', $receipt, $fileName);

        return $subscription->premiumSubscriptions()->create([
            'parent_user_id' => $parent->id,
            'plan' => $plan,
            'price' => $meta['price'],
            'receipt_path' => 'school_bus_premium_receipts/'.$fileName,
            'status' => 'pending',
        ]);
    }

    public function approve(SchoolBusPremiumSubscription $premium): void
    {
        $meta = SchoolBusPremiumSubscription::PLANS[$premium->plan];
        $startedAt = now();

        $premium->status = 'active';
        $premium->started_at = $startedAt;
        $premium->expires_at = $startedAt->copy()->addMonths($meta['months']);
        $premium->reviewed_by = Auth::id();
        $premium->reviewed_at = now();
        $premium->rejection_reason = null;
        $premium->save();
    }

    public function reject(SchoolBusPremiumSubscription $premium, ?string $reason): void
    {
        $premium->status = 'rejected';
        $premium->rejection_reason = $reason;
        $premium->reviewed_by = Auth::id();
        $premium->reviewed_at = now();
        $premium->save();
    }
}
