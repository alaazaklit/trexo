<?php

namespace App\Services\Dispatch;

use App\Models\RequestDispatchLog;
use Illuminate\Support\Facades\Log;

// Records every driver-dispatch attempt for an Order or Reservation, purely
// for admin reporting (see RequestReportController). Additive only — never
// throws, never affects the calling transition's own result, so a logging
// failure can't break the live booking flow it's attached to.
class DispatchLogService
{
    public function logSent(
        string $requestType,
        int $sellerId,
        int $driverId,
        ?int $orderId,
        ?int $reservationId,
        ?string $orderKind,
        $price
    ): void {
        try {
            RequestDispatchLog::create([
                'request_type' => $requestType,
                'order_id' => $orderId,
                'reservation_id' => $reservationId,
                'seller_id' => $sellerId,
                'driver_id' => $driverId,
                'order_kind' => $orderKind,
                'price' => $price,
                'outcome' => 'pending',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('DispatchLogService::logSent failed', ['error' => $e->getMessage()]);
        }
    }

    public function logOutcome(
        string $requestType,
        ?int $orderId,
        ?int $reservationId,
        ?int $driverId,
        string $outcome
    ): void {
        if (!$driverId) {
            return;
        }

        try {
            $query = RequestDispatchLog::where('request_type', $requestType)
                ->where('driver_id', $driverId)
                ->where('outcome', 'pending');

            $query = $orderId
                ? $query->where('order_id', $orderId)
                : $query->where('reservation_id', $reservationId);

            $log = $query->latest('id')->first();

            if ($log) {
                $log->update(['outcome' => $outcome, 'responded_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('DispatchLogService::logOutcome failed', ['error' => $e->getMessage()]);
        }
    }
}
