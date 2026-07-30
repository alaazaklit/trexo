<?php

namespace App\Services\Dashboard;

use App\Models\Driver;
use App\Models\User;
use App\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const IN_FLIGHT_STATUSES = ['pending_driver_selection', 'driver_assigned', 'picked_up', 'on_way'];
    private const CANCELLATION_STATUSES = ['canceled', 'driver_rejected', 'failed_delivery', 'request_expired'];
    private const RECENT_ACTIVITY_MINUTES = 15;
    private const VALID_RANGES = [1, 7, 30];

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
        ];
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
            ];
        })->values()->all();
    }
}
