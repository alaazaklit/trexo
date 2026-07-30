<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\Reservation;
use App\Services\OrderOps\OrderOpsService;
use App\Services\ReservationOps\ReservationOpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderOpsController extends Controller
{
    public function __construct(
        private readonly OrderOpsService $orderOps,
        private readonly ReservationOpsService $reservationOps,
    ) {
    }

    public function candidateDrivers(Order $order): JsonResponse
    {
        return response()->json([
            'result' => true,
            'drivers' => $this->orderOps->candidateDrivers($order)->values(),
        ]);
    }

    public function reassign(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['driver_id' => 'required|integer|exists:users,id']);

        try {
            $this->orderOps->reassign($order, $data['driver_id']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('success', 'Order reassigned.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->orderOps->cancel($order, $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back()->with('success', 'Order canceled.');
    }

    public function reservations(Request $request): View
    {
        $query = Reservation::query()->with(['seller', 'driver'])->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('admin.reservations.index', [
            'pageTitle' => 'Reservations',
            'reservations' => $query->paginate(25)->withQueryString(),
            'drivers' => $this->reservationOps->candidateDrivers(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function reassignReservation(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate(['driver_id' => 'required|integer|exists:users,id']);

        try {
            $this->reservationOps->reassign($reservation, $data['driver_id']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['reservation' => $e->getMessage()]);
        }

        return back()->with('success', 'Reservation reassigned.');
    }

    public function cancelReservation(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->reservationOps->cancel($reservation, $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['reservation' => $e->getMessage()]);
        }

        return back()->with('success', 'Reservation canceled.');
    }
}
