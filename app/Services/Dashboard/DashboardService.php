<?php

namespace App\Services\Dashboard;

use App\Models\Driver;
use App\Models\DriverSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Order;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const IN_FLIGHT_STATUSES = ['pending_driver_selection', 'driver_assigned', 'picked_up', 'on_way'];
    private const CANCELLATION_STATUSES = ['canceled', 'driver_rejected', 'failed_delivery', 'request_expired'];
    private const RECENT_ACTIVITY_MINUTES = 15;
    private const VALID_RANGES = [1, 7, 30];

    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function payload(int $rangeDays): array
    {
        $rangeDays = in_array($rangeDays, self::VALID_RANGES, true) ? $rangeDays : 7;
        $since = Carbon::now()->subDays($rangeDays);

        return [
            'range_days' => $rangeDays,
            'active_drivers' => $this->activeDrivers(),
            'active_customers' => $this->activeCustomers(),
            'in_flight' => $this->inFlightOrders(),
            'completed_orders' => $this->completedOrders($since),
            'cancellations' => $this->cancellations($since),
            'volume_trend' => $this->volumeTrend($rangeDays),
            'subscriptions' => $this->subscriptionStats(),
            'revenue' => $this->revenue(),
            'top_drivers' => $this->topDrivers(),
            // This app is cash-to-driver — Trexo's revenue above is what
            // drivers *should* eventually pay in; this is what's actually
            // still outstanding across all of them right now.
            'commission_owed_total' => (float) Wallet::sum('commission_owed'),
        ];
    }

    /**
     * "Current plan" per driver is computed (SubscriptionService::
     * currentSubscriptionFor), not stored — this loops all drivers rather
     * than a single aggregate query. Fine at this app's current driver
     * count; would need a real aggregate query if that grows into the
     * thousands.
     */
    private function subscriptionStats(): array
    {
        $byPlan = SubscriptionPlan::all()->pluck('id', 'slug')->keys()->flip()->map(fn () => 0)->all();

        Driver::all()->each(function (Driver $driver) use (&$byPlan) {
            $slug = $this->subscriptions->currentSubscriptionFor($driver)?->plan?->slug ?? 'basic';
            $byPlan[$slug] = ($byPlan[$slug] ?? 0) + 1;
        });

        return [
            'by_plan' => $byPlan,
            'pending' => DriverSubscription::where('payment_status', 'pending')->count(),
            // A driver whose only paid subscription has an end_date in the
            // past and is currently sitting back on Basic (per the same
            // fallback rule SweepExpiredSubscriptions enforces) — "expired"
            // isn't a stored status, so this is the closest count to it.
            'lapsed' => DriverSubscription::where('payment_status', 'approved')
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', now()->toDateString())
                ->whereHas('plan', fn ($plan) => $plan->where('monthly_price', '>', 0))
                ->distinct('driver_id')
                ->count('driver_id'),
        ];
    }

    private function revenue(): array
    {
        return [
            'today' => (float) Transaction::whereDate('created_at', now()->toDateString())->sum('commission_amount'),
            'this_month' => (float) Transaction::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('commission_amount'),
            'all_time' => (float) Transaction::sum('commission_amount'),
        ];
    }

    private function topDrivers(int $limit = 5): array
    {
        return Transaction::select('driver_id')
            ->selectRaw('COUNT(*) as completed_orders')
            ->selectRaw('SUM(driver_earnings) as total_earnings')
            ->groupBy('driver_id')
            ->orderByDesc('total_earnings')
            ->limit($limit)
            ->with('driver.user')
            ->get()
            ->map(fn (Transaction $row) => [
                'driver_name' => $row->driver?->user?->name,
                'completed_orders' => (int) $row->completed_orders,
                'total_earnings' => (float) $row->total_earnings,
                'rating' => $row->driver?->rating !== null ? (float) $row->driver->rating : null,
            ])
            ->all();
    }

    private function activeDrivers(): int
    {
        return Driver::where('is_online', true)->count();
    }

    private function activeCustomers(): int
    {
        return User::where('type', 'seller')
            ->where('last_seen_at', '>=', Carbon::now()->subMinutes(self::RECENT_ACTIVITY_MINUTES))
            ->count();
    }

    private function inFlightOrders(): array
    {
        return Order::whereIn('status', self::IN_FLIGHT_STATUSES)
            ->select('status', 'order_kind', DB::raw('count(*) as total'))
            ->groupBy('status', 'order_kind')
            ->get()
            ->groupBy('order_kind')
            ->map(fn ($rows) => $rows->pluck('total', 'status'))
            ->all();
    }

    private function completedOrders(Carbon $since): array
    {
        return Order::where('status', 'delivered')
            ->where('created_at', '>=', $since)
            ->select('order_kind', DB::raw('count(*) as total'))
            ->groupBy('order_kind')
            ->pluck('total', 'order_kind')
            ->all();
    }

    private function cancellations(Carbon $since): array
    {
        return Order::whereIn('status', self::CANCELLATION_STATUSES)
            ->where('created_at', '>=', $since)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    private function volumeTrend(int $rangeDays): array
    {
        $rows = Order::where('created_at', '>=', Carbon::now()->subDays($rangeDays)->startOfDay())
            ->select(DB::raw('DATE(created_at) as day'), 'order_kind', DB::raw('count(*) as total'))
            ->groupBy('day', 'order_kind')
            ->orderBy('day')
            ->get();

        $days = collect(range(0, $rangeDays - 1))
            ->map(fn ($offset) => Carbon::now()->subDays($rangeDays - 1 - $offset)->toDateString());

        $byDay = $rows->groupBy('day');

        return $days->map(function (string $day) use ($byDay) {
            $rowsForDay = $byDay->get($day, collect());

            return [
                'day' => $day,
                'taxi' => (int) $rowsForDay->firstWhere('order_kind', 'taxi')?->total,
                'delivery' => (int) $rowsForDay->firstWhere('order_kind', 'delivery')?->total,
                'bus' => (int) $rowsForDay->firstWhere('order_kind', 'bus')?->total,
            ];
        })->values()->all();
    }
}
