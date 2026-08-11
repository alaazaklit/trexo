<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\SchoolBusRoute;
use App\Models\SchoolBusSubscription;
use App\Models\User;
use App\Services\SchoolBus\SchoolBusNotificationService;
use App\Services\SchoolBus\SchoolBusSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SchoolBusSubscriptionController extends Controller
{
    public function __construct(
        private readonly SchoolBusSubscriptionService $subscriptions,
        private readonly SchoolBusNotificationService $notifications,
    ) {
    }

    private function authenticatedDriver(): ?Driver
    {
        $user = JWTAuth::parseToken()->authenticate();

        return Driver::where('user_id', $user->id)->first();
    }

    // Parent: submit a subscription request for a chosen route.
    public function submit(Request $request)
    {
        $parent = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:school_bus_routes,id',
            'student_name' => 'required|string|max:150',
            'parent_name' => 'required|string|max:150',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'notes' => 'nullable|string|max:1000',
            'children_count' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $route = SchoolBusRoute::where('is_active', true)->find($request->input('route_id'));
        if (!$route) {
            return response()->json(['result' => false, 'message' => 'Route not found'], 404);
        }

        $subscription = $this->subscriptions->submit($route, $parent, $request->only([
            'student_name', 'parent_name', 'phone', 'address', 'latitude', 'longitude', 'notes', 'children_count',
        ]));

        return response()->json([
            'result' => true,
            'message' => 'Subscription request submitted',
            'data' => $this->formatSubscription($subscription),
        ], 201);
    }

    // Parent: own request/subscription history.
    public function mine(Request $request)
    {
        $parent = JWTAuth::parseToken()->authenticate();

        $this->syncLanguage($parent, $request->query('language'));

        $subscriptions = SchoolBusSubscription::with(['route.school', 'driver.user'])
            ->where('parent_user_id', $parent->id)
            ->latest('id')
            ->get();

        return response()->json([
            'result' => true,
            'message' => 'Subscriptions loaded',
            'data' => $subscriptions->map(fn (SchoolBusSubscription $s) => $this->formatSubscription($s)),
        ]);
    }

    // Driver: requests (pending) + active students, optionally filtered.
    public function driverIndex(Request $request)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $query = SchoolBusSubscription::with(['route.school', 'parentUser'])
            ->where('driver_id', $driver->id);

        $status = $request->query('status');
        if (in_array($status, SchoolBusSubscription::STATUSES, true)) {
            $query->where('status', $status);
        }

        $subscriptions = $query->latest('id')->get();

        return response()->json([
            'result' => true,
            'message' => 'Subscriptions loaded',
            'data' => $subscriptions->map(fn (SchoolBusSubscription $s) => $this->formatSubscription($s)),
        ]);
    }

    // Driver: single request/subscription, for the notification-tap details
    // page (deep-links by subscription id regardless of its current status,
    // unlike driverIndex() which is a status-filtered list).
    public function show(SchoolBusSubscription $subscription)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver || $subscription->driver_id !== $driver->id) {
            return response()->json(['result' => false, 'message' => 'Subscription not found'], 404);
        }

        $subscription->load(['route.school', 'parentUser']);

        return response()->json([
            'result' => true,
            'message' => 'Subscription loaded',
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    // Driver: lightweight pending/active counts for badges — cheaper than
    // fetching driverIndex()'s full record lists just to read .length.
    public function counts(Request $request)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        if ($driver->user !== null) {
            $this->syncLanguage($driver->user, $request->query('language'));
        }

        return response()->json([
            'result' => true,
            'message' => 'Counts loaded',
            'data' => [
                'pending' => SchoolBusSubscription::where('driver_id', $driver->id)->where('status', 'pending')->count(),
                'active' => SchoolBusSubscription::where('driver_id', $driver->id)->where('status', 'active')->count(),
            ],
        ]);
    }

    // The backend can't otherwise know a user's app language when composing
    // a push notification asynchronously (a parent submitting a request, or
    // a driver accepting one) — opportunistically refreshed here since this
    // is hit every time the driver's dashboard loads. See also mine() below
    // for the parent-side equivalent, and SchoolBusRouteController::
    // syncLanguage() for the driver's other sync point.
    private function syncLanguage(User $user, ?string $language): void
    {
        if (empty($language) || $user->language === $language) {
            return;
        }

        $user->update(['language' => $language]);
    }

    public function accept(SchoolBusSubscription $subscription)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver || $subscription->driver_id !== $driver->id) {
            return response()->json(['result' => false, 'message' => 'Subscription not found'], 404);
        }

        $this->subscriptions->accept($subscription, $driver->user?->name ?? 'the driver');

        return response()->json([
            'result' => true,
            'message' => 'Subscription accepted',
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    public function reject(Request $request, SchoolBusSubscription $subscription)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver || $subscription->driver_id !== $driver->id) {
            return response()->json(['result' => false, 'message' => 'Subscription not found'], 404);
        }

        $this->subscriptions->reject($subscription, $request->input('reason'));

        return response()->json([
            'result' => true,
            'message' => 'Subscription rejected',
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    // Driver quick-action buttons — "bus on the way", "arrived", etc.
    public function event(Request $request, SchoolBusSubscription $subscription)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver || $subscription->driver_id !== $driver->id) {
            return response()->json(['result' => false, 'message' => 'Subscription not found'], 404);
        }

        if ($subscription->status !== 'active') {
            return response()->json(['result' => false, 'message' => 'Subscription is not active'], 422);
        }

        $event = $request->input('event');
        if (!array_key_exists($event, SchoolBusNotificationService::EVENTS)) {
            return response()->json(['result' => false, 'message' => 'Invalid event'], 422);
        }

        $this->notifications->sendEvent($subscription, $event);

        return response()->json(['result' => true, 'message' => 'Notification sent']);
    }

    private function formatSubscription(SchoolBusSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'rejection_reason' => $subscription->rejection_reason,
            'student_name' => $subscription->student_name,
            'parent_name' => $subscription->parent_name,
            'phone' => $subscription->phone,
            'address' => $subscription->address,
            'notes' => $subscription->notes,
            'school_name' => $subscription->route?->school?->name,
            'pickup_area' => $subscription->route?->pickup_area,
            'monthly_price' => $subscription->route !== null ? (float) $subscription->route->monthly_price : null,
            'children_count' => $subscription->children_count,
            'base_price' => $subscription->base_price !== null ? (float) $subscription->base_price : null,
            'discount_percent' => (float) $subscription->discount_percent,
            'total_price' => $subscription->total_price !== null ? (float) $subscription->total_price : null,
            'driver_name' => $subscription->relationLoaded('driver') ? $subscription->driver?->user?->name : null,
            'accepted_at' => $subscription->accepted_at?->toIso8601String(),
            'created_at' => $subscription->created_at?->toIso8601String(),
        ];
    }
}
