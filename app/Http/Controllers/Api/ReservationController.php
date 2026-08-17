<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Traits\MatchesDriverSchedules;
use App\Services\Dispatch\DispatchLogService;
use App\Services\Firebase\FcmMessagingService;
use App\Services\Wallet\TransactionService;

class ReservationController extends Controller
{
    use MatchesDriverSchedules;

    // Mirrors OrderController::DRIVER_RESPONSE_WINDOW_SECONDS — how long a
    // driver has to respond once assigned before the seller can pick
    // someone else, and how long the seller's "waiting" progress bar runs.
    const DRIVER_RESPONSE_WINDOW_SECONDS = 60;

    // Mirrors OrderController::expireStaleOrderRequests. Reservations have
    // no distinct "request_expired" status (unlike orders) — a driver
    // assigned but not yet responded is just 'pending' with driver_id set,
    // so a stale request just clears driver_id and stays 'pending' instead
    // of moving to a new status.
    private function expireStaleReservationRequests(FcmMessagingService $Notification): void
    {
        $staleReservations = Reservation::where('status', 'pending')
            ->whereNotNull('driver_id')
            ->where('updated_at', '<=', now()->subSeconds(self::DRIVER_RESPONSE_WINDOW_SECONDS))
            ->get();

        foreach ($staleReservations as $reservation) {
            $staleDriverId = $reservation->driver_id;
            $reservation->driver_id = null;
            $reservation->save();

            (new DispatchLogService())->logOutcome('reservation', null, $reservation->id, $staleDriverId, 'expired');

            $this->notifyReservationParty(
                (int) $reservation->seller_id,
                $reservation->id,
                'pending',
                'انتهت مهلة الحجز',
                'لم يستجب السائق خلال المهلة المحددة، يرجى اختيار سائق آخر.',
                $Notification
            );
        }
    }

    // Pushes the FCM alert AND persists it into the `notifications` table
    // (mirrors OrderController::notifyOrderOwner) so reservation events show
    // up in the same in-app notifications inbox as order events, not just
    // as a transient system push.
    private function notifyReservationParty(
        int $userId,
        int $reservationId,
        string $status,
        string $title,
        string $message,
        FcmMessagingService $Notification
    ): void {
        $recipient = User::find($userId);
        if (!$recipient) {
            return;
        }

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $recipient->id,
            'ref_id' => $reservationId,
            'section' => 'reservations',
            'title' => $title,
            'message' => $message,
            'data' => json_encode([
                'reservation_id' => $reservationId,
                'status' => $status,
            ]),
            'is_read' => 0,
            'created_at' => now(),
        ]);

        if (!empty($recipient->fcm_token)) {
            $Notification->sendNotification([
                [
                    'fcm_token' => $recipient->fcm_token,
                    'user_id' => $recipient->id,
                    'ref_id' => $reservationId,
                    'notification_id' => $notificationId,
                ],
            ], $title, $message, 'reservations');
        }
    }

    public function createReservation(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'start_address' => 'required',
            'destination_address' => 'required',
            'start_date_time' => 'required|date',
            'end_date_time' => 'required|date|after:start_date_time',
            'order_kind' => 'nullable|string|in:taxi,delivery,bus',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
                'data' => []
            ], 400);
        }

        $reservation = Reservation::create([
            'seller_id' => $user->id,
            'pickup' => $request->input('start_address'),
            'destination' => $request->input('destination_address'),
            'route_points' => $request->input('route_points', []),
            'route_distance_km' => $request->input('route_distance_km'),
            'start_date_time' => $request->input('start_date_time'),
            'end_date_time' => $request->input('end_date_time'),
            'order_kind' => $request->input('order_kind', 'delivery'),
            'status' => 'pending',
        ]);

        $reservation->tracking_id = 'R' . Carbon::now()->format('Ymd') . $reservation->id;
        $reservation->save();

        return response()->json([
            'result' => true,
            'message' => 'Reservation created successfully',
            'data' => $reservation,
        ], 201);
    }

    public function getReservations(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $this->expireStaleReservationRequests($Notification);

        $query = Reservation::where('reservations.seller_id', $user->id)
            ->leftJoin('users as driver', 'driver.id', '=', 'reservations.driver_id')
            ->select(
                'reservations.*',
                'driver.name as driver_name',
                'driver.avatar as driver_avatar'
            )
            ->selectSub(function ($q) use ($user) {
                $q->selectRaw('count(*)')
                  ->from('reservation_messages')
                  ->whereColumn('reservation_messages.reservation_id', 'reservations.id')
                  ->where('reservation_messages.sender_id', '!=', $user->id)
                  ->where('reservation_messages.is_read', false);
            }, 'unread_chat_count')
            ->orderBy('reservations.start_date_time', 'desc');

        if ($request->input('status')) {
            $query->where('reservations.status', $request->input('status'));
        }

        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($q) use ($like) {
                $q->where('reservations.tracking_id', 'like', $like)
                  ->orWhere('driver.name', 'like', $like)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(reservations.pickup, '$.address')) LIKE ?", [$like])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(reservations.destination, '$.address')) LIKE ?", [$like]);
            });
        }

        $dateFrom = $request->input('date_from');
        if (!empty($dateFrom)) {
            $query->whereDate('reservations.start_date_time', '>=', $dateFrom);
        }

        $dateTo = $request->input('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('reservations.start_date_time', '<=', $dateTo);
        }

        $reservations = $query->get();

        return response()->json([
            'result' => true,
            'total_count' => $reservations->count(),
            'message' => 'All Records',
            'data' => $reservations,
        ], 201);
    }

    public function getReservationsForDriver(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $this->expireStaleReservationRequests($Notification);

        $query = Reservation::where('reservations.driver_id', $user->id)
            ->leftJoin('users as seller', 'seller.id', '=', 'reservations.seller_id')
            ->select(
                'reservations.*',
                'seller.name as seller_name',
                'seller.avatar as seller_avatar'
            )
            ->selectSub(function ($q) use ($user) {
                $q->selectRaw('count(*)')
                  ->from('reservation_messages')
                  ->whereColumn('reservation_messages.reservation_id', 'reservations.id')
                  ->where('reservation_messages.sender_id', '!=', $user->id)
                  ->where('reservation_messages.is_read', false);
            }, 'unread_chat_count')
            ->orderBy('reservations.start_date_time', 'desc');

        if ($request->input('status')) {
            $query->where('reservations.status', $request->input('status'));
        }

        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($q) use ($like) {
                $q->where('reservations.tracking_id', 'like', $like)
                  ->orWhere('seller.name', 'like', $like)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(reservations.pickup, '$.address')) LIKE ?", [$like])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(reservations.destination, '$.address')) LIKE ?", [$like]);
            });
        }

        $dateFrom = $request->input('date_from');
        if (!empty($dateFrom)) {
            $query->whereDate('reservations.start_date_time', '>=', $dateFrom);
        }

        $dateTo = $request->input('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('reservations.start_date_time', '<=', $dateTo);
        }

        $reservations = $query->get();

        return response()->json([
            'result' => true,
            'total_count' => $reservations->count(),
            'message' => 'All Records',
            'data' => $reservations,
        ], 201);
    }

    // Single-reservation lookup — mirrors OrderController::getOrder. Used to
    // refresh a reservation's details/card in place (after either party
    // changes its status, or a new chat message arrives) without reloading
    // the whole list. Reachable by whichever side is asking — the seller
    // who booked it or the driver currently assigned to it.
    public function getReservation($reservationId, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // Expire this reservation's stale "waiting for driver" request here
        // too — not just in the list endpoints — so a single-reservation
        // refresh (e.g. the seller's progress bar polling right as its 60s
        // countdown ends) reflects the expiry immediately.
        $this->expireStaleReservationRequests($Notification);

        $reservation = Reservation::where('reservations.id', $reservationId)
            ->where(function ($query) use ($user) {
                $query->where('reservations.seller_id', $user->id)
                      ->orWhere('reservations.driver_id', $user->id);
            })
            ->leftJoin('users as driver', 'driver.id', '=', 'reservations.driver_id')
            ->leftJoin('users as seller', 'seller.id', '=', 'reservations.seller_id')
            ->select(
                'reservations.*',
                'driver.name as driver_name',
                'driver.avatar as driver_avatar',
                'seller.name as seller_name',
                'seller.avatar as seller_avatar'
            )
            ->selectSub(function ($q) use ($user) {
                $q->selectRaw('count(*)')
                  ->from('reservation_messages')
                  ->whereColumn('reservation_messages.reservation_id', 'reservations.id')
                  ->where('reservation_messages.sender_id', '!=', $user->id)
                  ->where('reservation_messages.is_read', false);
            }, 'unread_chat_count')
            ->first();

        if (!$reservation) {
            return response()->json([
                'result' => false,
                'message' => 'Reservation not found or unauthorized access',
            ], 404);
        }

        return response()->json([
            'result' => true,
            'message' => 'Reservation found',
            'data' => $reservation,
        ], 200);
    }

    public function getReservationDrivers(Request $request, $reservationId)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $genderFilter = in_array($request->query('gender'), ['male', 'female'], true)
            ? $request->query('gender')
            : null;

        $reservation = Reservation::where('seller_id', $user->id)
            ->where('id', $reservationId)
            ->first();

        if (!$reservation) {
            return response()->json([
                'result' => false,
                'message' => 'Reservation not found'
            ], 404);
        }

        $pickup = $reservation->pickup ?? [];
        $destination = $reservation->destination ?? [];

        $passengerPickup = [
            'lat' => (float) ($pickup['lat'] ?? 0),
            'lng' => (float) ($pickup['lng'] ?? 0),
        ];
        $passengerDestination = [
            'lat' => (float) ($destination['lat'] ?? 0),
            'lng' => (float) ($destination['lng'] ?? 0),
        ];

        $passengerRoute = $this->decodeRoutePoints($reservation->route_points ?? null);
        if (empty($passengerRoute)) {
            $passengerRoute = [$passengerPickup, $passengerDestination];
        }

        $passengerDistanceKm = $this->resolveTripDistanceKm(
            $passengerPickup,
            $passengerDestination,
            $reservation->route_distance_km
                ? (float) $reservation->route_distance_km
                : $this->calculatePolylineDistance($passengerRoute)
        );

        $drivers = $this->findMatchingDrivers(
            $passengerRoute,
            $passengerPickup,
            $passengerDestination,
            $passengerDistanceKm,
            0,
            true, // isReservation — bills the round trip (×fare.reservation_multiplier)
            $pickup['city'] ?? null,
            $pickup['region'] ?? null,
            $destination['city'] ?? null,
            $destination['region'] ?? null,
            $genderFilter
        );

        return response()->json([
            'result' => true,
            'message' => 'Reservation found',
            'data' => $drivers
        ], 200);
    }

    public function ChooseReservationDriver(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $driverId = $request->input('driver_id');
        $reservationId = $request->input('reservation_id');
        // The fare shown to the seller for this candidate driver on the
        // "choose driver" screen (computed by
        // MatchesDriverSchedules::findMatchingDrivers) — persisted here,
        // same convention as OrderController::ChooseDriver, so it's the
        // same number shown everywhere afterwards (including the Transaction
        // posted on completion) rather than recomputed later against
        // possibly-changed driver settings.
        $price = $request->input('price');

        $reservation = Reservation::where('id', $reservationId)
            ->where('seller_id', $user->id)
            ->first();

        if (!$reservation) {
            return response()->json(['error' => 'Reservation not found or unauthorized access'], 404);
        }

        // Assigning a driver only proposes them for the reservation — it stays
        // 'pending' until the driver actually accepts via updateReservationStatus,
        // mirroring how orders wait for the driver's response before confirming.
        $reservation->driver_id = $driverId;
        $reservation->status = 'pending';
        if (is_numeric($price)) {
            $reservation->price = round((float) $price, 2);
        }
        // Clear any acceptance left over from a previous driver — the app
        // shows chat/call to the seller based on this timestamp being set,
        // and a newly (re)assigned driver hasn't accepted anything yet.
        $reservation->driver_accepted_at = null;
        $reservation->pickup_notified_at = null;
        $reservation->save();

        (new DispatchLogService())->logSent('reservation', (int) $user->id, (int) $driverId, null, $reservation->id, $reservation->order_kind, $reservation->price);

        $this->notifyReservationParty(
            (int) $driverId,
            $reservation->id,
            'pending',
            'حجز جديد',
            'لديك حجز جديد بانتظار موافقتك.',
            $Notification
        );

        return response()->json([
            'result' => true,
            'message' => 'Driver assigned successfully',
            'data' => $reservation
        ], 201);
    }

    public function getReservationTracking($reservationId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // Reachable by whichever side is asking — the seller who booked it
        // or the driver currently assigned to it (mirrors getReservation).
        $reservation = Reservation::where('id', $reservationId)
            ->where(function ($query) use ($user) {
                $query->where('seller_id', $user->id)
                      ->orWhere('driver_id', $user->id);
            })
            ->first();

        if (!$reservation) {
            return response()->json([
                'result' => false,
                'message' => 'Reservation not found or unauthorized access',
            ], 404);
        }

        // Fetched unconditionally (not just while actively trackable) so the
        // reservation details map can always show a pickup/destination
        // route preview, with live driver tracking layered on top only once
        // the trip is actually underway.
        $pickup = $reservation->pickup ?? [];
        $destination = $reservation->destination ?? [];
        $routeCoordinates = [
            'start_address' => $pickup['address'] ?? null,
            'destination_address' => $destination['address'] ?? null,
            'start_lat' => isset($pickup['lat']) ? (float) $pickup['lat'] : null,
            'start_lng' => isset($pickup['lng']) ? (float) $pickup['lng'] : null,
            'destination_lat' => isset($destination['lat']) ? (float) $destination['lat'] : null,
            'destination_lng' => isset($destination['lng']) ? (float) $destination['lng'] : null,
        ];

        if (!$this->isReservationTrackableNow($reservation)) {
            return response()->json([
                'result' => true,
                'trackable' => false,
                'status' => $reservation->status,
                ...$routeCoordinates,
            ], 200);
        }

        $driver = User::find($reservation->driver_id);
        if (!$driver || $driver->latitude === null || $driver->longitude === null || empty($pickup)) {
            return response()->json([
                'result' => true,
                'trackable' => false,
                'status' => $reservation->status,
                'message' => 'Driver location not available yet',
                ...$routeCoordinates,
            ], 200);
        }

        $distanceKm = $this->haversineDistance(
            (float) $driver->latitude,
            (float) $driver->longitude,
            (float) ($pickup['lat'] ?? 0),
            (float) ($pickup['lng'] ?? 0)
        );
        $speedKmh = max((float) ($driver->speed_kmh ?? 0), 15.0);
        $etaMinutes = max(1, (int) round(($distanceKm / $speedKmh) * 60));

        return response()->json([
            'result' => true,
            'trackable' => true,
            'target' => 'pickup',
            'status' => $reservation->status,
            'target_lat' => (float) ($pickup['lat'] ?? 0),
            'target_lng' => (float) ($pickup['lng'] ?? 0),
            'driver_lat' => (float) $driver->latitude,
            'driver_lng' => (float) $driver->longitude,
            'driver_heading' => $driver->heading !== null ? (float) $driver->heading : null,
            'driver_speed_kmh' => $driver->speed_kmh !== null ? (float) $driver->speed_kmh : null,
            'driver_last_seen_at' => optional($driver->last_seen_at)->toIso8601String(),
            'distance_remaining_km' => round($distanceKm, 3),
            'eta_minutes' => $etaMinutes,
            ...$routeCoordinates,
        ], 200);
    }

    private function isReservationTrackableNow(Reservation $reservation): bool
    {
        if ($reservation->status !== 'accepted' || !$reservation->driver_id) {
            return false;
        }

        return $this->isWithinTrackingWindow($reservation->start_date_time, $reservation->end_date_time);
    }

    public function updateReservationStatus(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $status = $request->input('status');
        $reservationId = $request->input('reservation_id');

        $reservation = Reservation::where('id', $reservationId)->first();

        if (!$reservation) {
            return response()->json(['error' => 'Reservation not found'], 404);
        }

        if ($reservation->driver_id != null && $reservation->driver_id != $user->id && $reservation->seller_id != $user->id) {
            return response()->json([
                'result' => false,
                'message' => 'Not allowed to update this reservation',
                'data' => $reservation,
            ], 201);
        }

        $terminalStatuses = ['completed', 'cancelled'];
        if (in_array($reservation->status, $terminalStatuses, true) && $reservation->status !== $status) {
            return response()->json([
                'result' => false,
                'message' => 'لا يمكن تعديل الحجز بعد وصوله إلى الحالة النهائية',
                'data' => $reservation,
            ], 201);
        }

        // Driver rejecting frees the reservation back up for the seller to
        // assign someone else, mirroring the order flow's driver_rejected.
        if ($status === 'rejected' && (int) $reservation->driver_id === (int) $user->id) {
            $rejectingDriverId = $reservation->driver_id;
            $reservation->status = 'pending';
            $reservation->driver_id = null;
            $reservation->save();

            (new DispatchLogService())->logOutcome('reservation', null, $reservation->id, $rejectingDriverId, 'rejected');

            $this->notifyReservationParty(
                (int) $reservation->seller_id,
                $reservation->id,
                'pending',
                'تم رفض الحجز',
                'قام السائق برفض الحجز، يرجى اختيار سائق آخر.',
                $Notification
            );

            return response()->json([
                'result' => true,
                'message' => 'Reservation marked as rejected by driver',
                'data' => $reservation,
            ], 201);
        }

        $previousStatus = $reservation->status;
        $reservation->status = $status;
        if ($status === 'accepted' && !$reservation->driver_accepted_at) {
            $reservation->driver_accepted_at = now();
        }
        if ($status === 'cancelled') {
            $reservation->cancel_reason = trim((string) $request->input('reason', '')) ?: null;
        }
        $reservation->save();

        if ($status === 'accepted' && $previousStatus === 'pending') {
            (new DispatchLogService())->logOutcome('reservation', null, $reservation->id, $reservation->driver_id, 'accepted');
        }

        if ($status === 'cancelled' && $previousStatus === 'pending') {
            (new DispatchLogService())->logOutcome('reservation', null, $reservation->id, $reservation->driver_id, 'canceled');
        }

        if ($status === 'completed') {
            $this->recordReservationTransaction($reservation);
        }

        if ($status === 'accepted' && (int) $user->id === (int) $reservation->driver_id) {
            $this->notifyReservationParty(
                (int) $reservation->seller_id,
                $reservation->id,
                'accepted',
                'تم قبول الحجز',
                'قام السائق بقبول الحجز.',
                $Notification
            );
        }

        return response()->json([
            'result' => true,
            'message' => 'Reservation status has been updated',
            'data' => $reservation
        ], 201);
    }

    // Mirrors OrderController::recordOrderTransaction — best-effort so a
    // bug here never blocks the reservation-completion response, since the
    // reservation is already saved as completed by the time this runs.
    private function recordReservationTransaction(Reservation $reservation): void
    {
        try {
            app(TransactionService::class)->recordForReservation($reservation);
        } catch (\Throwable $e) {
            Log::error('Failed to record transaction for completed reservation', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
