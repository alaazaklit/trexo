<?php

namespace App\Services\ReservationOps;

use App\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationOpsService
{
    /**
     * Mirrors Api\ReservationController::updateReservationStatus's terminal
     * set — Reservation's status vocabulary is distinct from Order's
     * (completed/cancelled vs delivered/canceled), so this is kept separate
     * rather than sharing a constant with OrderOpsService.
     */
    private const TERMINAL_STATUSES = ['completed', 'cancelled'];

    public function candidateDrivers(): Collection
    {
        return DB::table('users')
            ->leftJoin('drivers', 'drivers.user_id', '=', 'users.id')
            ->where('users.type', 'driver')
            ->where('users.is_available', true)
            ->where(function ($q) {
                $q->whereNull('drivers.approval_status')->orWhere('drivers.approval_status', 'approved');
            })
            ->select('users.id', 'users.name', 'users.phone', 'drivers.rating')
            ->get();
    }

    /**
     * @throws \RuntimeException if the reservation is already in a terminal state.
     */
    public function reassign(Reservation $reservation, int $driverId): Reservation
    {
        if (in_array($reservation->status, self::TERMINAL_STATUSES, true)) {
            throw new \RuntimeException("Cannot reassign a reservation that is already {$reservation->status}.");
        }

        $reservation->driver_id = $driverId;
        $reservation->status = 'pending';
        $reservation->driver_accepted_at = null;
        $reservation->pickup_notified_at = null;
        $reservation->save();

        return $reservation;
    }

    /**
     * @throws \RuntimeException if the reservation is already in a terminal state.
     */
    public function cancel(Reservation $reservation, string $reason): Reservation
    {
        if (in_array($reservation->status, self::TERMINAL_STATUSES, true)) {
            throw new \RuntimeException("Cannot cancel a reservation that is already {$reservation->status}.");
        }

        $reservation->status = 'cancelled';
        $reservation->cancel_reason = $reason;
        $reservation->save();

        return $reservation;
    }
}
