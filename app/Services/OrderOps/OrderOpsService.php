<?php

namespace App\Services\OrderOps;

use App\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderOpsService
{
    /**
     * Mirrors the terminal-status guard in
     * Api\OrderController::updateOrderStatus — kept as a separate constant
     * rather than a shared reference, per the decision to keep admin ops
     * logic independent from the live API controller.
     */
    private const TERMINAL_STATUSES = ['failed_delivery', 'canceled', 'delivered'];

    public function candidateDrivers(Order $order): Collection
    {
        $pickup = $order->startAddress;

        $query = DB::table('users')
            ->leftJoin('drivers', 'drivers.user_id', '=', 'users.id')
            ->where('users.type', 'driver')
            ->where('users.is_available', true)
            ->where(function ($q) {
                $q->whereNull('drivers.approval_status')->orWhere('drivers.approval_status', 'approved');
            })
            ->select('users.id', 'users.name', 'users.phone', 'drivers.rating', 'users.latitude', 'users.longitude');

        $drivers = $query->get();

        if ($pickup && $pickup->latitude !== null && $pickup->longitude !== null) {
            $drivers = $drivers->map(function ($driver) use ($pickup) {
                $driver->distance_km = ($driver->latitude !== null && $driver->longitude !== null)
                    ? $this->haversineDistance((float) $pickup->latitude, (float) $pickup->longitude, (float) $driver->latitude, (float) $driver->longitude)
                    : null;

                return $driver;
            })->sortBy('distance_km')->values();
        }

        return $drivers;
    }

    /**
     * @throws \RuntimeException if the order is already in a terminal state.
     */
    public function reassign(Order $order, int $driverId): Order
    {
        if (in_array($order->status, self::TERMINAL_STATUSES, true)) {
            throw new \RuntimeException("Cannot reassign an order that is already {$order->status}.");
        }

        $order->driver_id = $driverId;
        $order->status = 'waiting_driver_response';
        $order->driver_accepted_at = null;
        $order->pickup_notified_at = null;
        $order->destination_notified_at = null;
        $order->save();

        return $order;
    }

    /**
     * @throws \RuntimeException if the order is already in a terminal state.
     */
    public function cancel(Order $order, string $reason): Order
    {
        if (in_array($order->status, self::TERMINAL_STATUSES, true)) {
            throw new \RuntimeException("Cannot cancel an order that is already {$order->status}.");
        }

        $order->status = 'canceled';
        $order->cancel_reason = $reason;
        $order->save();

        return $order;
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
