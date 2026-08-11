<?php

namespace App\Services\Wallet;

use App\Models\Driver;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Order;
use App\Reservation;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Posts one immutable ledger row per completed order/reservation and adds to
// the driver's owed-commission balance in the same DB transaction — this app
// is cash-to-driver (customer pays the driver directly), so completing a
// trip doesn't credit the driver anything Trexo owes; it increases what the
// driver owes Trexo out of the cash they already collected. Never called
// twice for the same order/reservation on purpose — the unique index on
// transactions.order_id/reservation_id makes a second attempt a silent
// no-op, since OrderController has two independent code paths that can both
// mark an order 'delivered' (manual status update + GPS auto-advance).
class TransactionService
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function recordForOrder(Order $order): ?Transaction
    {
        if ($order->driver_id === null || $order->price === null) {
            return null;
        }

        return $this->record(
            driverUserId: (int) $order->driver_id,
            customerId: (int) $order->user_id,
            orderKind: $order->order_kind,
            totalAmount: (float) $order->price,
            attach: ['order_id' => $order->id],
        );
    }

    public function recordForReservation(Reservation $reservation): ?Transaction
    {
        if ($reservation->driver_id === null || $reservation->price === null) {
            return null;
        }

        return $this->record(
            driverUserId: (int) $reservation->driver_id,
            customerId: (int) $reservation->seller_id,
            orderKind: $reservation->order_kind,
            totalAmount: (float) $reservation->price,
            attach: ['reservation_id' => $reservation->id],
        );
    }

    private function record(int $driverUserId, int $customerId, ?string $orderKind, float $totalAmount, array $attach): ?Transaction
    {
        $driver = Driver::where('user_id', $driverUserId)->first();
        if ($driver === null) {
            Log::error('TransactionService: no drivers row for user, skipping transaction', [
                'user_id' => $driverUserId,
                'attach' => $attach,
            ]);
            return null;
        }

        $serviceType = in_array($orderKind, ['taxi', 'delivery', 'bus'], true) ? $orderKind : 'delivery';
        $subscription = $this->subscriptions->currentSubscriptionFor($driver);
        $commissionPercentage = $this->subscriptions->commissionPercentageFor($driver);
        $commissionAmount = round($totalAmount * $commissionPercentage / 100, 2);
        $driverEarnings = round($totalAmount - $commissionAmount, 2);

        return DB::transaction(function () use ($driver, $customerId, $serviceType, $totalAmount, $commissionPercentage, $commissionAmount, $driverEarnings, $subscription, $attach) {
            // firstOrCreate on the unique order_id/reservation_id column is
            // the idempotency guard — a second call for the same
            // order/reservation (e.g. the manual status endpoint and the
            // GPS auto-advance both firing for the same delivery) reuses
            // the existing row instead of inserting a duplicate.
            $transaction = Transaction::firstOrCreate($attach, [
                'driver_id' => $driver->id,
                'customer_id' => $customerId,
                'service_type' => $serviceType,
                'total_amount' => $totalAmount,
                'commission_percentage' => $commissionPercentage,
                'commission_amount' => $commissionAmount,
                'driver_earnings' => $driverEarnings,
                'driver_subscription_id' => $subscription?->id,
                'status' => 'completed',
            ]);

            if ($transaction->wasRecentlyCreated) {
                Wallet::where('driver_id', $driver->id)->increment('commission_owed', $commissionAmount);
            }

            return $transaction;
        });
    }
}
