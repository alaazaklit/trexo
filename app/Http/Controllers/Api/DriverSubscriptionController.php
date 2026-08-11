<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class DriverSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function plans()
    {
        JWTAuth::parseToken()->authenticate();

        return response()->json([
            'result' => true,
            'message' => 'Subscription plans loaded',
            'data' => SubscriptionPlan::where('is_active', true)
                ->orderBy('monthly_price')
                ->get(['id', 'name', 'slug', 'monthly_price', 'commission_percentage', 'features']),
        ]);
    }

    public function current()
    {
        $user = JWTAuth::parseToken()->authenticate();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $current = $this->subscriptions->currentSubscriptionFor($driver);
        // The most recent request regardless of outcome — lets the driver
        // see "your renewal is pending review" even while a still-valid
        // older approved subscription (e.g. Basic) is the one actually in
        // effect right now.
        $latestRequest = $driver->subscriptions()->with('plan')->latest('id')->first();

        return response()->json([
            'result' => true,
            'message' => 'Subscription loaded',
            'data' => [
                'current' => $current ? $this->formatSubscription($current) : null,
                'latest_request' => $latestRequest ? $this->formatSubscription($latestRequest) : null,
            ],
        ]);
    }

    public function subscribe(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $plan = SubscriptionPlan::find($request->input('plan_id'));

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            // A free plan needs no proof of payment — see SubscriptionService::subscribe().
            'receipt' => ($plan !== null && (float) $plan->monthly_price > 0)
                ? 'required|file|mimes:jpg,jpeg,png,pdf|max:10240'
                : 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $subscription = $this->subscriptions->subscribe($driver, $plan, $request->file('receipt'));

        return response()->json([
            'result' => true,
            'message' => (float) $plan->monthly_price > 0
                ? 'Subscription request submitted, pending review'
                : 'Subscribed successfully',
            'data' => $this->formatSubscription($subscription->load('plan')),
        ], 201);
    }

    private function formatSubscription(\App\Models\DriverSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan?->name,
            'plan_slug' => $subscription->plan?->slug,
            'commission_percentage' => $subscription->commission_percentage_snapshot ?? $subscription->plan?->commission_percentage,
            'payment_status' => $subscription->payment_status,
            'payment_method' => $subscription->payment_method,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'renewal_date' => $subscription->renewal_date?->toDateString(),
            'rejection_reason' => $subscription->rejection_reason,
            'created_at' => $subscription->created_at?->toIso8601String(),
        ];
    }
}
