<?php

namespace App\Services\SchoolBus;

use App\Models\SchoolBusRoute;
use App\Models\SchoolBusSubscription;
use App\Models\User;

class SchoolBusSubscriptionService
{
    public function __construct(private readonly SchoolBusNotificationService $notifications)
    {
    }

    /**
     * @param array{student_name: string, parent_name: string, phone: string, address: string, latitude?: float|string|null, longitude?: float|string|null, notes?: string|null, children_count?: int} $data
     */
    public function submit(SchoolBusRoute $route, User $parent, array $data): SchoolBusSubscription
    {
        $childrenCount = max(1, (int) ($data['children_count'] ?? 1));
        $pricing = $this->calculatePricing($route, $childrenCount);

        $subscription = SchoolBusSubscription::create([
            'route_id' => $route->id,
            'driver_id' => $route->driver_id,
            'parent_user_id' => $parent->id,
            'student_name' => $data['student_name'],
            'parent_name' => $data['parent_name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'children_count' => $childrenCount,
            'base_price' => $pricing['base_price'],
            'discount_percent' => $pricing['discount_percent'],
            'total_price' => $pricing['total_price'],
        ]);

        $this->notifications->notifyDriverNewRequest(
            $subscription,
            $data['parent_name'],
            $data['student_name'],
            $route->pickup_area,
        );

        return $subscription;
    }

    /**
     * The sibling discount is a single driver-level rate (% per child) —
     * no per-school configuration, per product decision to keep this easy
     * to manage. It only applies from the 2nd child onward: a single-child
     * subscription is always full price.
     *
     * @return array{base_price: float, discount_percent: float, total_price: float}
     */
    public function calculatePricing(SchoolBusRoute $route, int $childrenCount): array
    {
        $basePrice = round((float) $route->monthly_price * $childrenCount, 2);

        $discountPercent = 0.0;
        if ($childrenCount >= 2) {
            $ratePerChild = (float) ($route->driver?->school_bus_child_discount_percent ?? 0);
            $discountPercent = min(100.0, $childrenCount * $ratePerChild);
        }

        $totalPrice = round($basePrice * (1 - $discountPercent / 100), 2);

        return [
            'base_price' => $basePrice,
            'discount_percent' => $discountPercent,
            'total_price' => $totalPrice,
        ];
    }

    public function accept(SchoolBusSubscription $subscription, string $driverName): void
    {
        $subscription->status = 'active';
        $subscription->accepted_at = now();
        $subscription->rejection_reason = null;
        $subscription->save();

        $this->notifications->notifyParentAccepted($subscription, $driverName);
    }

    public function reject(SchoolBusSubscription $subscription, ?string $reason): void
    {
        $subscription->status = 'rejected';
        $subscription->rejection_reason = $reason;
        $subscription->save();

        $this->notifications->notifyParent(
            $subscription,
            'rejected',
            'Subscription rejected',
            $reason ?: 'Your school bus subscription request has been rejected.'
        );
    }
}
