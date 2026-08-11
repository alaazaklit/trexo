<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolBusPremiumSubscription;
use App\Models\SchoolBusSubscription;
use App\Services\SchoolBus\SchoolBusPremiumService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SchoolBusPremiumController extends Controller
{
    public function __construct(private readonly SchoolBusPremiumService $premium)
    {
    }

    // Parent: latest premium request for one of their school-bus requests
    // (whatever its status — pending/active/rejected/expired), plus the
    // plans on offer — shown on the "View Plan" details screen (never in
    // the requests list itself, which only gets a static teaser).
    public function status(SchoolBusSubscription $subscription)
    {
        $parent = JWTAuth::parseToken()->authenticate();
        if ($subscription->parent_user_id !== $parent->id) {
            return response()->json(['result' => false, 'message' => 'Subscription not found'], 404);
        }

        $latest = $this->premium->latestFor($subscription);

        return response()->json([
            'result' => true,
            'message' => 'Premium status loaded',
            'data' => [
                'is_active' => $latest !== null && $latest->isCurrentlyActive(),
                'current' => $latest ? $this->formatPremium($latest) : null,
                'plans' => $this->formatPlans(),
            ],
        ]);
    }

    // Parent: "Subscribe Now" — uploads a payment receipt, submits it as
    // 'pending'. No payment gateway exists anywhere in this app, so an
    // admin manually reviews the receipt and approves/rejects it (same
    // workflow as the driver Basic/Plus plans) — see SchoolBusPremiumService.
    public function subscribe(Request $request, SchoolBusSubscription $subscription)
    {
        $parent = JWTAuth::parseToken()->authenticate();
        if ($subscription->parent_user_id !== $parent->id) {
            return response()->json(['result' => false, 'message' => 'Subscription not found'], 404);
        }

        if ($subscription->status !== 'active') {
            return response()->json(['result' => false, 'message' => 'This school bus request is not active yet'], 422);
        }

        $pending = $this->premium->latestFor($subscription);
        if ($pending !== null && $pending->status === 'pending') {
            return response()->json(['result' => false, 'message' => 'You already have a pending Premium request awaiting review'], 422);
        }

        $validator = Validator::make($request->all(), [
            'plan' => 'required|in:monthly,yearly',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $premiumSubscription = $this->premium->subscribe(
            $subscription,
            $parent,
            $request->input('plan'),
            $request->file('receipt'),
        );

        return response()->json([
            'result' => true,
            'message' => 'Premium request submitted for review',
            'data' => [
                'is_active' => false,
                'current' => $this->formatPremium($premiumSubscription),
                'plans' => $this->formatPlans(),
            ],
        ], 201);
    }

    private function formatPremium(SchoolBusPremiumSubscription $premium): array
    {
        return [
            'plan' => $premium->plan,
            'price' => (float) $premium->price,
            'status' => $premium->status,
            'rejection_reason' => $premium->rejection_reason,
            'started_at' => $premium->started_at?->toIso8601String(),
            'expires_at' => $premium->expires_at?->toIso8601String(),
        ];
    }

    private function formatPlans(): array
    {
        return collect(SchoolBusPremiumSubscription::PLANS)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'price' => $meta['price'],
                'months' => $meta['months'],
                'badge' => $meta['badge'],
            ])
            ->values()
            ->all();
    }
}
