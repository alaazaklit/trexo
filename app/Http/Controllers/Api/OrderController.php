<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Schedule;
use App\Address;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Order;
use App\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Models\Setting;
use App\Services\Firebase\FcmMessagingService;
use App\Services\Tracking\OrderTrackingRules;
use App\Services\Wallet\TransactionService;
use App\Traits\MatchesDriverSchedules;


class OrderController extends Controller
{
    use MatchesDriverSchedules;


// Seconds a driver has to respond to a request before it's considered
// unanswered — mirrors `_DriverResponseActionBar.responseWindowSeconds` on
// the Flutter side (orders.dart), which shows the same 60s countdown.
const DRIVER_RESPONSE_WINDOW_SECONDS = 60;

// Once a driver accepts and sets off, the seller can still cancel — but a
// cancellation after this many seconds counts as "late" (the driver has
// likely already covered real distance/fuel) and gets flagged on the
// seller's account rather than silently costing the driver for nothing.
const CANCELLATION_GRACE_SECONDS = 90;

// Statuses where the driver is actively en route — cancelling here is what
// actually wastes their time/fuel, as opposed to cancelling while still
// `waiting_driver_response` (no one has committed yet).
const ACTIVE_DRIVER_STATUSES = ['on_way', 'picked_up', 'in_transit'];

// Posts the driver-earnings/Trexo-commission ledger row for a delivered
// order. Called from both places that can write status='delivered'
// (updateOrderStatus's manual transition and maybeAdvanceOrderStatus's GPS
// auto-advance) — TransactionService's own unique-index guard makes
// whichever one runs second a no-op rather than a duplicate posting.
// Deliberately best-effort: a bug here must not block the order-completion
// response itself, since the order is already saved as delivered by the
// time this runs.
private function recordOrderTransaction(Order $order): void
{
    try {
        app(TransactionService::class)->recordForOrder($order);
    } catch (\Throwable $e) {
        Log::error('Failed to record transaction for delivered order', [
            'order_id' => $order->id,
            'error' => $e->getMessage(),
        ]);
    }
}

// Catches requests nobody ever responded to — the driver may have never
// opened the app, so the client-side countdown alone can't be relied on.
// Run opportunistically from the endpoints the app already polls
// frequently (the seller's and the driver's own order lists) rather than
// requiring a real cron job to be set up on this environment.
private function expireStaleOrderRequests(FcmMessagingService $Notification): void
{
    $staleOrders = Order::where('status', 'waiting_driver_response')
        ->where('updated_at', '<=', now()->subSeconds(self::DRIVER_RESPONSE_WINDOW_SECONDS))
        ->get();

    foreach ($staleOrders as $order) {
        $order->status = 'request_expired';
        $order->driver_id = null;
        $order->save();

        $this->notifyOrderOwner(
            (int) $order->user_id,
            $order->id,
            'request_expired',
            $this->orderKindText($order, 'انتهت مهلة الرحلة', 'انتهت مهلة الطلب'),
            'لم يستجب السائق خلال المهلة المحددة، يرجى اختيار سائق آخر.',
            $Notification
        );
    }
}

function getOrders(Request $request, FcmMessagingService $Notification)
{   $user = JWTAuth::parseToken()->authenticate();

    $this->expireStaleOrderRequests($Notification);

    $page = max(1, (int) $request->input('page', 1));
    $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

    $query = Order::where('orders.user_id', $user->id)
        ->join('addresses as address_from', 'address_from.order_id', '=', 'orders.id')
        ->where('address_from.direction', 'start_address')
        ->join('addresses as address_to', 'address_to.order_id', '=', 'orders.id')
        ->where('address_to.direction', 'destination_address')
        ->join('users', 'users.id', '=', 'orders.user_id')
        ->leftJoin('users as driver', 'driver.id', '=', 'orders.driver_id');

    $status = $request->input('status');
    if (!empty($status)) {
        $query->where('orders.status', $status);
    }

    $keyword = trim((string) $request->input('keyword', ''));
    if ($keyword !== '') {
        $query->where(function ($q) use ($keyword) {
            $like = '%' . $keyword . '%';
            $q->where('orders.tracking_id', 'like', $like)
              ->orWhere('address_from.address_line1', 'like', $like)
              ->orWhere('address_to.address_line1', 'like', $like)
              ->orWhere('driver.name', 'like', $like);
        });
    }

    // date_from/date_to are full UTC timestamps (see orders.dart's
    // _dateRangeParams) marking an exclusive [from, to) instant range —
    // not bare calendar dates — since created_at is stored in UTC while
    // the range is chosen based on the device's local calendar day.
    $dateFrom = $request->input('date_from');
    if (!empty($dateFrom)) {
        $query->where('orders.created_at', '>=', $dateFrom);
    }

    $dateTo = $request->input('date_to');
    if (!empty($dateTo)) {
        $query->where('orders.created_at', '<', $dateTo);
    }

    $totalCount = (clone $query)->count();

    $schedules = $query
        ->select(
            'orders.*',
            'driver.name as driver_name',
            'driver.avatar as driver_avatar',
            'users.name as username',
            'orders.created_at as date',
            'users.avatar as profileImage',
            'orders.id as order_id',
            'address_from.address_line1 as start_address',
            'address_to.address_line1 as destination_address',
            'address_from.location_note as start_location_note',
            'address_to.location_note as destination_location_note'
        )
        ->selectSub(function ($q) use ($user) {
            $q->selectRaw('count(*)')
              ->from('order_messages')
              ->whereColumn('order_messages.order_id', 'orders.id')
              ->where('order_messages.sender_id', '!=', $user->id)
              ->where('order_messages.is_read', false);
        }, 'unread_chat_count')
        ->selectSub(function ($q) {
            // ScheduleController::store puts the picked date in `day`
            // instead of `date` for delivery-type (type=1) schedules — the
            // "Jadwal" popup's schedules are always type=1 — so both
            // columns have to be checked, not just `date`.
            $q->selectRaw('COALESCE(date, day)')
              ->from('schedules')
              ->whereColumn('schedules.order_id', 'orders.id')
              ->latest('id')
              ->limit(1);
        }, 'scheduled_date')
        ->selectSub(function ($q) {
            $q->select('time_from')
              ->from('schedules')
              ->whereColumn('schedules.order_id', 'orders.id')
              ->latest('id')
              ->limit(1);
        }, 'scheduled_time_from')
        ->selectSub(function ($q) {
            $q->select('time_to')
              ->from('schedules')
              ->whereColumn('schedules.order_id', 'orders.id')
              ->latest('id')
              ->limit(1);
        }, 'scheduled_time_to')
        ->orderBy('orders.created_at', 'desc')
        ->forPage($page, $perPage)
        ->get();

    return response()->json([
        'result' => true,
        'total_count' => $totalCount,
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => ($page * $perPage) < $totalCount,
        'message' => 'All Records',
        'data' =>$schedules
    ], 201);

}

// Single-order lookup used by the app to refresh an order's details/card in
// place (after the seller or the driver changes its status) without having
// to reload the whole list. Reachable by whichever side of the order is
// asking — the owning seller or the currently assigned driver.
function getOrder($order_id, FcmMessagingService $Notification)
{
    $user = JWTAuth::parseToken()->authenticate();

    // Expire this order's stale "waiting for driver" request here too —
    // not just in the list endpoints — so a single-order refresh (e.g. the
    // seller's card polling right as its 60s countdown ends) reflects the
    // expiry immediately instead of waiting for the next full list reload.
    $this->expireStaleOrderRequests($Notification);

    $order = Order::where('orders.id', $order_id)
        ->where(function ($query) use ($user) {
            $query->where('orders.user_id', $user->id)
                  ->orWhere('orders.driver_id', $user->id);
        })
        ->join('addresses as address_from', 'address_from.order_id', '=', 'orders.id')
        ->where('address_from.direction', 'start_address')
        ->join('addresses as address_to', 'address_to.order_id', '=', 'orders.id')
        ->where('address_to.direction', 'destination_address')
        ->join('users', 'users.id', '=', 'orders.user_id')
        ->leftJoin('users as driver', 'driver.id', '=', 'orders.driver_id')
        ->select(
            'orders.*',
            'driver.name as driver_name',
            'driver.avatar as driver_avatar',
            'users.name as username',
            'orders.created_at as date',
            'users.avatar as profileImage',
            'orders.id as order_id',
            'address_from.address_line1 as start_address',
            'address_to.address_line1 as destination_address',
            'address_from.location_note as start_location_note',
            'address_to.location_note as destination_location_note'
        )
        ->selectSub(function ($q) use ($user) {
            $q->selectRaw('count(*)')
              ->from('order_messages')
              ->whereColumn('order_messages.order_id', 'orders.id')
              ->where('order_messages.sender_id', '!=', $user->id)
              ->where('order_messages.is_read', false);
        }, 'unread_chat_count')
        ->selectSub(function ($q) {
            // ScheduleController::store puts the picked date in `day`
            // instead of `date` for delivery-type (type=1) schedules — the
            // "Jadwal" popup's schedules are always type=1 — so both
            // columns have to be checked, not just `date`.
            $q->selectRaw('COALESCE(date, day)')
              ->from('schedules')
              ->whereColumn('schedules.order_id', 'orders.id')
              ->latest('id')
              ->limit(1);
        }, 'scheduled_date')
        ->selectSub(function ($q) {
            $q->select('time_from')
              ->from('schedules')
              ->whereColumn('schedules.order_id', 'orders.id')
              ->latest('id')
              ->limit(1);
        }, 'scheduled_time_from')
        ->selectSub(function ($q) {
            $q->select('time_to')
              ->from('schedules')
              ->whereColumn('schedules.order_id', 'orders.id')
              ->latest('id')
              ->limit(1);
        }, 'scheduled_time_to')
        ->first();

    if (!$order) {
        return response()->json([
            'result' => false,
            'message' => 'Order not found or unauthorized access',
        ], 404);
    }

    return response()->json([
        'result' => true,
        'message' => 'Order found',
        'data' => $order,
    ], 200);
}

function ChooseDriver(Request $request, FcmMessagingService $Notification)
{
    // Step 1: Authenticate the user using JWT
    $user = JWTAuth::parseToken()->authenticate();

  
    
    // Step 2: Get driver_id and order_id from the request
    $driver_id = $request->input('driver_id');  // or $request->driver_id if coming from a form
    $order_id = $request->input('order_id');
    // The fare shown to the seller for this candidate driver on the "choose
    // driver" screen (computed by MatchesDriverSchedules::findMatchingDrivers)
    // — persisted here so it's the same number shown everywhere afterwards,
    // rather than recomputed later against different live driver settings.
    $price = $request->input('price');

    // Step 3: Find the order based on the order_id and user_id
    $order = Order::where('id', $order_id)
                  ->where('user_id', $user->id)  // Ensure the user owns the order
                  ->first();

    // Step 4: Check if the order exists
    if (!$order) {
        return response()->json(['error' => 'Order not found or unauthorized access'], 404);
    }

    // Step 5: Update the driver_id for the order
    // Keep the status aligned with the driver inbox poller so the request appears
    // on the chosen driver's account until they accept or ط±ظپط¶ it.
    $order->driver_id = $driver_id;
    $order->status ="waiting_driver_response";
    if (is_numeric($price)) {
        $order->price = round((float) $price, 2);
    }
    // Clear any acceptance left over from a previous driver (e.g. this
    // order already went through one driver who accepted, then failed the
    // delivery) — the app shows chat/call to the seller based on this
    // timestamp being set, and a newly (re)assigned driver hasn't accepted
    // anything yet, even if a prior one had.
    $order->driver_accepted_at = null;
    // Re-arm both arrival notifications for the newly (re)assigned driver.
    $order->pickup_notified_at = null;
    $order->destination_notified_at = null;
    // A leftover count from a previous driver's approach to the pickup must
    // not instantly satisfy the new driver's confirmation requirement
    // before they've sent a single location ping of their own.
    $order->arrival_confirmation_count = 0;

    // Step 6: Save the order with the new driver_id
    $order->save();

    $driverUser = User::find($driver_id);
    if ($driverUser) {
        $title = $this->orderKindText($order, 'طلب رحلة جديد', 'طلب جديد');
        $message = $this->orderKindText(
            $order,
            'لديك طلب رحلة جديد، يرجى الموافقة خلال 60 ثانية.',
            'لديك طلب جديد، يرجى الموافقة خلال 60 ثانية.'
        );

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $driverUser->id,
            'ref_id' => $order->id,
            'section' => 'orders',
            'title' => $title,
            'message' => $message,
            'data' => json_encode([
                'order_id' => $order->id,
                'driver_id' => $driverUser->id,
                'status' => $order->status,
            ]),
            'is_read' => 0,
            'created_at' => now(),
        ]);

        if (!empty($driverUser->fcm_token)) {
            $Notification->sendNotification([
                [
                    'fcm_token' => $driverUser->fcm_token,
                    'user_id' => $driverUser->id,
                    'ref_id' => $order->id,
                    'notification_id' => $notificationId,
                ],
            ], $title, $message);
        }
    }
    
    return response()->json([
        'result' => true,
        'message' => 'Driver assigned successfully',
        'data' =>$order 
    ], 201);
}



function updateOrderStatus(Request $request, FcmMessagingService $Notification)
{
    // Step 1: Authenticate the user using JWT
    $user = JWTAuth::parseToken()->authenticate();
    // picked_up is a mid-trip checkpoint, not an end state — it must still
    // be able to advance to in_transit/delivered/failed_delivery.
    $terminalStatuses = ['failed_delivery', 'canceled', 'delivered'];

  $result=true;
  $msg="Order Status has been updated";
    
    // Step 2: Get driver_id and order_id from the request
    $status = $request->input('status');  // or $request->driver_id if coming from a form
    $order_id = $request->input('order_id');

    // Step 3: Find the order based on the order_id and user_id
    $order = Order::where('id', $order_id) // Ensure the user owns the order
                  ->first();

                




    
    // Step 4: Check if the order exists
    if (!$order) {
        return response()->json(['error' => 'Order not found or unauthorized access'], 404);
    }

    if($order->driver_id!=null && $order->driver_id!=0 && $order->driver_id!=$user->id &&  $order->user_id!=$user->id){
        $result=false;
        $msg="عذراً، لا يمكنك تحديث حالة هذا الطلب.";
      }
      
      // A failed delivery is terminal for that trip attempt, but not for the
      // order itself — the seller must still be able to send it to another
      // driver (via ChooseDriver, unaffected by this status check) or give
      // up and cancel it, rather than being stuck with an order frozen at
      // failed_delivery forever.
      $reopeningAfterFailedDelivery = $order->status === 'failed_delivery' && $status === 'canceled';

      if ($result && in_array($order->status, $terminalStatuses, true) && $order->status !== $status && !$reopeningAfterFailedDelivery) {
        $result = false;
        $msg = 'لا يمكن تعديل الطلب بعد وصوله إلى الحالة النهائية.';
      }

      if ($result && $status === 'driver_rejected' && (int) $order->driver_id === (int) $user->id) {
        $order->status = 'driver_rejected';
        $order->driver_id = null;
        $order->save();

        $this->notifyOrderOwner(
            (int) $order->user_id,
            $order->id,
            'driver_rejected',
            $this->orderKindText($order, 'تم رفض الرحلة', 'تم رفض الطلب'),
            $this->orderKindText(
                $order,
                'قام السائق برفض طلب الرحلة، يرجى اختيار سائق آخر.',
                'قام السائق برفض الطلب، يرجى اختيار سائق آخر.'
            ),
            $Notification
        );

        return response()->json([
            'result' => true,
            'message' => 'Order marked as rejected by driver',
            'data' => $order,
        ], 201);
      }

      // The driver had the request open and the 60s countdown ran out with
      // no accept/reject tap — distinct from an explicit rejection above,
      // so the seller sees "no response" rather than "driver rejected".
      // Also guards against a stale client-side countdown: if the driver
      // already accepted (and possibly moved the order on to picked_up/
      // in_transit/etc.) through another screen or the request popup, a
      // leftover countdown from an earlier, un-refreshed Orders-tab card can
      // still fire this call after the fact. Without checking that the
      // order is still actually 'waiting_driver_response', that stale call
      // would clobber an in-progress trip back to "expired" and wrongly
      // tell the seller the driver never responded.
      if ($result && $status === 'request_expired' && $order->status === 'waiting_driver_response' && (int) $order->driver_id === (int) $user->id) {
        $order->status = 'request_expired';
        $order->driver_id = null;
        $order->save();

        $this->notifyOrderOwner(
            (int) $order->user_id,
            $order->id,
            'request_expired',
            $this->orderKindText($order, 'انتهت مهلة الرحلة', 'انتهت مهلة الطلب'),
            'لم يستجب السائق خلال المهلة المحددة، يرجى اختيار سائق آخر.',
            $Notification
        );

        return response()->json([
            'result' => true,
            'message' => 'Order marked as expired (no driver response)',
            'data' => $order,
        ], 201);
      }

      if (!$result) {
        return response()->json([
            'result' => false,
            'message' => $msg,
            'data' => $order,
        ], 201);
      }

      // Once a driver has rejected an order — or the request timed out with
      // no response — driver_id is cleared, which would otherwise skip the
      // ownership guard above entirely and let anyone "resurrect" it (e.g.
      // tapping a stale notification) by re-accepting it directly.
      // Re-engaging must go through ChooseDriver (which reassigns driver_id
      // and resets the status) rather than this endpoint. Cancelling is
      // exempt — the seller must still be able to close out a stale order
      // that never found a driver, rather than being stuck unable to cancel.
      if($result && in_array($order->status, ['driver_rejected', 'request_expired'], true) && $status !== $order->status && $status !== 'canceled'){
        $result=false;
        $msg='تم إغلاق هذا الطلب، يرجى اختيار سائق جديد.';
      }

      // A retried/duplicate request (double-tap, a client retry after a lost
      // response, a stale push notification acted on twice) asking for the
      // status the order is already in isn't a transition at all — applying
      // it again would be harmless for the `status` column itself but would
      // re-run every notification/side-effect below a second time (a driver
      // double-tapping "reject" used to re-fire the "order rejected" push).
      // Short-circuit here, before any of that runs, and report success
      // since the order is already in the state the caller wanted.
      if ($result && $status === $order->status) {
        Log::channel('tracking')->info('updateOrderStatus: ignored duplicate transition request', [
            'order_id' => $order->id,
            'status' => $status,
            'requested_by' => $user->id,
        ]);

        return response()->json([
            'result' => true,
            'message' => $msg,
            'late_cancellation' => false,
            'data' => $order,
        ], 201);
      }

      // Server-side guard against skipping required statuses (e.g. jumping
      // straight from `on_way` to `delivered` via the driver's manual status
      // picker, which — before this check — offered every post-accept status
      // regardless of where the order actually was). The client is no longer
      // trusted to only ever request a sane next status.
      if ($result && !OrderTrackingRules::isValidTransition($order->status, $status)) {
        Log::channel('tracking')->warning('updateOrderStatus: rejected invalid transition', [
            'order_id' => $order->id,
            'from' => $order->status,
            'to' => $status,
            'requested_by' => $user->id,
        ]);

        $result = false;
        $msg = 'لا يمكن تغيير حالة الطلب مباشرة من "' . $order->status . '" إلى "' . $status . '".';
      }

      if (!$result) {
        return response()->json([
            'result' => false,
            'message' => $msg,
            'data' => $order,
        ], 201);
      }

      $previousStatus = $order->status;
      $lateCancellation = false;

      if($result)  {$order->status = $status;
        // Any confirmed transition starts the next leg's GPS-arrival count
        // fresh — carrying over a count accumulated against the previous
        // leg's target (pickup vs destination) would let a stale count
        // instantly satisfy the new leg's confirmation requirement.
        $order->arrival_confirmation_count = 0;
        if($user->id!= $order->user_id){
        $order->driver_id = $user->id; }

        if ($status === 'on_way' && !$order->driver_accepted_at) {
            $order->driver_accepted_at = now();
        }

        if ($status === 'canceled') {
            $order->cancel_reason = trim((string) $request->input('reason', '')) ?: null;
        }

        $order->save();

        Log::channel('tracking')->info('updateOrderStatus: transition applied', [
            'order_id' => $order->id,
            'from' => $previousStatus,
            'to' => $status,
            'requested_by' => $user->id,
            'reason' => 'manual_client_request',
        ]);

        if ($status === 'on_way' && (int) $user->id !== (int) $order->user_id) {
            $this->notifyOrderOwner(
                (int) $order->user_id,
                $order->id,
                'on_way',
                $this->orderKindText($order, 'تم قبول رحلتك', 'تم قبول طلبك'),
                $this->orderKindText(
                    $order,
                    'قام السائق بقبول رحلتك وهو في الطريق إليك.',
                    'قام السائق بقبول طلبك وهو في الطريق إليك.'
                ),
                $Notification
            );
        }

        if ($status === 'canceled' && $order->driver_id && (int) $user->id !== (int) $order->driver_id) {
            // The seller cancelling while the driver is still just
            // "assigned/awaiting response" costs nothing — the driver
            // hasn't set off. Cancelling after they've actually accepted
            // and started moving is what wastes their time/fuel, so only
            // that case is checked against the grace period.
            if (in_array($previousStatus, self::ACTIVE_DRIVER_STATUSES, true) && $order->driver_accepted_at) {
                $secondsSinceAccepted = now()->diffInSeconds($order->driver_accepted_at);
                $lateCancellation = $secondsSinceAccepted > self::CANCELLATION_GRACE_SECONDS;
            }

            $cancelTitle = $this->orderKindText($order, 'تم إلغاء الرحلة', 'تم إلغاء الطلب');

            if ($lateCancellation) {
                $user->increment('late_cancellations_count');

                $this->notifyOrderOwner(
                    (int) $order->driver_id,
                    $order->id,
                    'canceled',
                    $cancelTitle,
                    $this->orderKindText(
                        $order,
                        'قام الراكب بإلغاء الرحلة بعد انطلاقك نحو نقطة الالتقاط، تم تسجيل هذا الإلغاء.',
                        'قام البائع بإلغاء الطلب بعد انطلاقك نحو نقطة الالتقاط، تم تسجيل هذا الإلغاء.'
                    ),
                    $Notification
                );
            } else {
                $this->notifyOrderOwner(
                    (int) $order->driver_id,
                    $order->id,
                    'canceled',
                    $cancelTitle,
                    $this->orderKindText(
                        $order,
                        'تم إلغاء الرحلة من قبل الراكب.',
                        'تم إلغاء الطلب من قبل البائع.'
                    ),
                    $Notification
                );
            }
        }

        if ($status === 'delivered' && (int) $user->id !== (int) $order->user_id) {
            $this->notifyOrderOwner(
                (int) $order->user_id,
                $order->id,
                'delivered',
                $this->orderKindText($order, 'اكتملت رحلتك', 'تم توصيل طلبك'),
                $this->orderKindText(
                    $order,
                    'قام السائق بإيصالك إلى وجهتك بنجاح.',
                    'قام السائق بتوصيل طلبك بنجاح.'
                ),
                $Notification
            );
        }

        if ($status === 'delivered') {
            $this->recordOrderTransaction($order);
        }

        if ($status === 'failed_delivery' && (int) $user->id !== (int) $order->user_id) {
            $this->notifyOrderOwner(
                (int) $order->user_id,
                $order->id,
                'failed_delivery',
                $this->orderKindText($order, 'تعذّر إكمال رحلتك', 'تعذّر توصيل طلبك'),
                $this->orderKindText(
                    $order,
                    'لم يتمكن السائق من إكمال رحلتك، يمكنك اختيار سائق آخر أو إلغاء الطلب.',
                    'لم يتمكن السائق من إتمام توصيل طلبك، يمكنك اختيار سائق آخر أو إلغاء الطلب.'
                ),
                $Notification
            );
        }
    }
    // Step 5: Update the driver_id for the order
  
    
    // Step 6: Save the order with the new driver_id
  
    
    return response()->json([
        'result' => $result,
        'message' => $msg,
        'late_cancellation' => $lateCancellation,
        'data' =>$order
    ], 201);
}


// Every notification below used to talk about "your order" ("طلبك")
// unconditionally — fine for a delivery, but wrong for a taxi ride, where
// the driver is picking up/dropping off a person, not a package. Callers
// pick the right phrasing per order_kind through this instead of hardcoding
// delivery wording everywhere.
private function orderKindText(Order $order, string $taxiText, string $deliveryText): string
{
    return $order->order_kind === 'taxi' ? $taxiText : $deliveryText;
}

// Arabic grammar needs a different noun form depending on the count (1,
// 2, 3-10, 11+) — "3 دقائق" reads fine, but "3 دقيقة" (the previous,
// count-agnostic phrasing) doesn't, so a plain "$minutes دقيقة" is not
// professional/correct Arabic.
private function arabicMinutesPhrase(int $minutes): string
{
    if ($minutes <= 0) {
        return 'أقل من دقيقة';
    }
    if ($minutes === 1) {
        return 'دقيقة واحدة';
    }
    if ($minutes === 2) {
        return 'دقيقتين';
    }
    if ($minutes <= 10) {
        return $minutes . ' دقائق';
    }
    return $minutes . ' دقيقة';
}

private function notifyOrderOwner(int $userId, int $orderId, string $status, string $title, string $message, FcmMessagingService $Notification): void
{
    $recipient = User::find($userId);
    if (!$recipient) {
        return;
    }

    $notificationId = DB::table('notifications')->insertGetId([
        'user_id' => $recipient->id,
        'ref_id' => $orderId,
        'section' => 'orders',
        'title' => $title,
        'message' => $message,
        'data' => json_encode([
            'order_id' => $orderId,
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
                'ref_id' => $orderId,
                'status' => $status,
                'notification_id' => $notificationId,
            ],
        ], $title, $message);
    }
}

///////////////////////////////Live driver tracking////////////////////

private const TRACKING_ACTIVE_STATUSES = ['on_way', 'picked_up', 'in_transit'];

// Every tunable value that used to live here as a class constant (arrival
// radius tiers, required confirmations, inactivity thresholds, min speed)
// now lives in config/tracking.php instead, so it can be adjusted per
// environment via .env without a code change. See arrivalRadiusKm() and
// requiredConfirmations() below for the read side.

private function trackingMinSpeedKmh(): float
{
    return (float) config('tracking.min_speed_kmh', 15.0);
}

// Adaptive arrival radius for the driver's last reported GPS accuracy — see
// OrderTrackingRules::arrivalRadiusKm() for the reasoning. Thin wrapper that
// just supplies the config values; the actual tiering logic lives in that
// framework-agnostic class so it stays unit-testable without a DB.
private function arrivalRadiusKm(?float $accuracyMeters): float
{
    return OrderTrackingRules::arrivalRadiusKm(
        $accuracyMeters,
        (float) config('tracking.arrival_radius.accurate_gps_threshold_m', 20),
        (float) config('tracking.arrival_radius.accurate_radius_m', 75),
        (float) config('tracking.arrival_radius.moderate_gps_threshold_m', 50),
        (float) config('tracking.arrival_radius.moderate_radius_m', 120),
        (float) config('tracking.arrival_radius.poor_radius_m', 180)
    );
}

private function requiredConfirmations(): int
{
    return (int) config('tracking.required_consecutive_confirmations', 3);
}

public function updateDriverLocation(Request $request, FcmMessagingService $Notification)
{
    $user = JWTAuth::parseToken()->authenticate();

    $validator = Validator::make($request->all(), [
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
        'heading' => 'nullable|numeric|min:0|max:360',
        // Client sends km/h (geolocator reports speed in m/s - it must convert with *3.6 first).
        'speed_kmh' => 'nullable|numeric|min:0|max:180',
        // GPS accuracy in metres (geolocator's Position.accuracy) — drives
        // the adaptive arrival radius below. Optional so an older client
        // build that doesn't send it yet still works (falls back to the
        // most forgiving radius tier).
        'accuracy' => 'nullable|numeric|min:0',
        // Client-side capture time, epoch milliseconds. Lets a resend of a
        // reading whose earlier response got lost — see the offline queue
        // in DriverLocationService on the Flutter side — be recognised and
        // ignored here instead of reprocessed.
        'captured_at' => 'nullable|integer|min:0',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'result' => false,
            'message' => 'Validation Error',
            'errors' => $validator->errors(),
        ], 400);
    }

    $capturedAt = $request->input('captured_at') !== null ? (int) $request->input('captured_at') : null;
    $lastAppliedCapturedAt = $user->last_location_client_ts !== null ? (int) $user->last_location_client_ts : null;

    if (OrderTrackingRules::isDuplicatePing($capturedAt, $lastAppliedCapturedAt)) {
        // Already applied this exact (or an older) reading — the offline
        // queue retries a queued ping until it sees a 2xx, so a response
        // lost after the update actually saved would otherwise reprocess
        // the same reading: double arrival notifications, a confirmation
        // count incremented twice for one real GPS sample, etc.
        Log::channel('tracking')->debug('updateDriverLocation: ignored duplicate/stale ping', [
            'driver_id' => $user->id,
            'captured_at' => $capturedAt,
            'last_applied_captured_at' => $lastAppliedCapturedAt,
        ]);

        return response()->json(['result' => true, 'duplicate' => true], 200);
    }

    $accuracyMeters = $request->input('accuracy') !== null ? (float) $request->input('accuracy') : null;

    $user->latitude = $request->input('latitude');
    $user->longitude = $request->input('longitude');
    if ($request->has('heading')) {
        $user->heading = $request->input('heading');
    }
    if ($request->has('speed_kmh')) {
        $user->speed_kmh = $request->input('speed_kmh');
    }
    $user->location_accuracy_m = $accuracyMeters;
    if ($capturedAt !== null) {
        $user->last_location_client_ts = $capturedAt;
    }
    $user->last_seen_at = now();
    $user->save();

    $activeOrders = Order::where('driver_id', $user->id)
        ->whereIn('status', self::TRACKING_ACTIVE_STATUSES)
        ->get();

    foreach ($activeOrders as $order) {
        // One order's address/notification hiccup must not stop the ping
        // from updating this driver's live position for every other active
        // order, and must not vanish without a trace — previously any
        // exception here would bubble up and fail the whole ping silently
        // from the driver's point of view (fire-and-forget on the client),
        // which made "driver position/status never updates" impossible to
        // diagnose after the fact.
        try {
            $this->maybeNotifyArrival($order, $user, $Notification);
            $this->maybeAdvanceOrderStatus($order, $user, $accuracyMeters, $Notification);
        } catch (\Throwable $e) {
            Log::error('updateDriverLocation: tracking update failed for order', [
                'order_id' => $order->id,
                'driver_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    $activeReservations = Reservation::where('driver_id', $user->id)
        ->where('status', 'accepted')
        ->get();

    foreach ($activeReservations as $reservation) {
        if ($this->isWithinTrackingWindow($reservation->start_date_time, $reservation->end_date_time)) {
            $this->maybeNotifyReservationArrival($reservation, $user, $Notification);
        }
    }

    return response()->json(['result' => true], 200);
}

private function maybeNotifyReservationArrival(Reservation $reservation, User $driver, FcmMessagingService $Notification): void
{
    if ($reservation->pickup_notified_at !== null) {
        return;
    }

    $pickup = $reservation->pickup ?? [];
    if (empty($pickup) || $driver->latitude === null || $driver->longitude === null) {
        return;
    }

    $distanceKm = $this->haversineDistance(
        (float) $driver->latitude,
        (float) $driver->longitude,
        (float) ($pickup['lat'] ?? 0),
        (float) ($pickup['lng'] ?? 0)
    );
    $speedKmh = max((float) ($driver->speed_kmh ?? 0), $this->trackingMinSpeedKmh());
    $etaMinutes = ($distanceKm / $speedKmh) * 60;

    if ($etaMinutes > 2) {
        return;
    }

    $seller = User::find($reservation->seller_id);
    if ($seller && !empty($seller->fcm_token)) {
        $minutesPhrase = $etaMinutes < 1
            ? 'أقل من دقيقة'
            : $this->arabicMinutesPhrase(max(1, (int) round($etaMinutes)));
        $Notification->sendNotification([
            [
                'fcm_token' => $seller->fcm_token,
                'user_id' => $seller->id,
                'ref_id' => $reservation->id,
            ],
        ], 'السائق قريب منك', "السائق سيصل خلال {$minutesPhrase} لاستلام حجزك.", 'reservations');
    }

    $reservation->pickup_notified_at = now();
    $reservation->save();
}

private function maybeNotifyArrival(Order $order, User $driver, FcmMessagingService $Notification): void
{
    $isPickupLeg = $order->status === 'on_way';
    $notifiedField = $isPickupLeg ? 'pickup_notified_at' : 'destination_notified_at';

    if ($order->{$notifiedField} !== null) {
        return;
    }

    $targetAddress = Address::where('order_id', $order->id)
        ->where('direction', $isPickupLeg ? 'start_address' : 'destination_address')
        ->first();

    if (!$targetAddress || $driver->latitude === null || $driver->longitude === null) {
        return;
    }

    $distanceKm = $this->haversineDistance(
        (float) $driver->latitude,
        (float) $driver->longitude,
        (float) $targetAddress->latitude,
        (float) $targetAddress->longitude
    );
    $speedKmh = max((float) ($driver->speed_kmh ?? 0), $this->trackingMinSpeedKmh());
    $etaMinutes = ($distanceKm / $speedKmh) * 60;

    if ($etaMinutes > 2) {
        return;
    }

    $seller = User::find($order->user_id);
    if ($seller && !empty($seller->fcm_token)) {
        $minutesPhrase = $etaMinutes < 1
            ? 'أقل من دقيقة'
            : $this->arabicMinutesPhrase(max(1, (int) round($etaMinutes)));
        $title = 'السائق قريب منك';
        $message = $isPickupLeg
            ? $this->orderKindText(
                $order,
                "السائق سيصل خلال {$minutesPhrase} لاصطحابك.",
                "السائق سيصل خلال {$minutesPhrase} لاستلام طلبك."
            )
            : $this->orderKindText(
                $order,
                "ستصل إلى وجهتك خلال {$minutesPhrase}.",
                "السائق سيصل خلال {$minutesPhrase} لتوصيل طلبك."
            );

        $Notification->sendNotification([
            [
                'fcm_token' => $seller->fcm_token,
                'user_id' => $seller->id,
                'ref_id' => $order->id,
            ],
        ], $title, $message);
    }

    $order->{$notifiedField} = now();
    $order->save();
}

// Auto-advances the order one leg (on_way -> picked_up, picked_up/in_transit
// -> delivered) once the driver's live position has stayed inside the
// adaptive arrival radius (OrderTrackingRules::arrivalRadiusKm) for several
// consecutive pings in a row (OrderTrackingRules::nextConfirmationState).
// Replaces the previous maybeAutoMarkPickedUp/maybeAutoCompleteDelivery pair
// — same two legs, but they duplicated almost identical distance/radius
// logic, which now also has to carry the shared confirmation-counter
// bookkeeping, so one method handles both.
private function maybeAdvanceOrderStatus(
    Order $order,
    User $driver,
    ?float $accuracyMeters,
    FcmMessagingService $Notification
): void {
    if ($order->status === 'on_way') {
        $targetDirection = 'start_address';
        $nextStatus = 'picked_up';
    } elseif (in_array($order->status, ['picked_up', 'in_transit'], true)) {
        $targetDirection = 'destination_address';
        $nextStatus = 'delivered';
    } else {
        return;
    }

    if ($driver->latitude === null || $driver->longitude === null) {
        return;
    }

    $targetAddress = Address::where('order_id', $order->id)
        ->where('direction', $targetDirection)
        ->first();

    if (!$targetAddress) {
        return;
    }

    $distanceKm = $this->haversineDistance(
        (float) $driver->latitude,
        (float) $driver->longitude,
        (float) $targetAddress->latitude,
        (float) $targetAddress->longitude
    );
    $radiusKm = $this->arrivalRadiusKm($accuracyMeters);
    $withinRadius = $distanceKm <= $radiusKm;

    $state = OrderTrackingRules::nextConfirmationState(
        $withinRadius,
        (int) $order->arrival_confirmation_count,
        $this->requiredConfirmations()
    );

    if ($state['count'] !== (int) $order->arrival_confirmation_count) {
        $order->arrival_confirmation_count = $state['count'];
        $order->save();
    }

    // Tracking health, logged on every ping while a leg is active: GPS
    // accuracy, distance to the current target, the radius that accuracy
    // produced, and where the confirmation count stands — enough to answer
    // "why didn't this arrive" from the log alone instead of guessing.
    Log::channel('tracking')->debug('tracking health', [
        'order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => $order->status,
        'target' => $targetDirection,
        'accuracy_m' => $accuracyMeters,
        'distance_km' => round($distanceKm, 4),
        'radius_km' => $radiusKm,
        'within_radius' => $withinRadius,
        'confirmation_count' => $state['count'],
        'required_confirmations' => $this->requiredConfirmations(),
        'last_update_at' => now()->toIso8601String(),
    ]);

    if (!$state['shouldTransition']) {
        return;
    }

    $previousStatus = $order->status;
    $order->status = $nextStatus;
    $order->arrival_confirmation_count = 0;
    $order->save();

    Log::channel('tracking')->info('auto status transition', [
        'order_id' => $order->id,
        'driver_id' => $driver->id,
        'from' => $previousStatus,
        'to' => $nextStatus,
        'reason' => 'gps_arrival_confirmed',
        'distance_km' => round($distanceKm, 4),
        'radius_km' => $radiusKm,
        'confirmations' => $state['count'],
    ]);

    if ($nextStatus === 'delivered') {
        $this->recordOrderTransaction($order);
    }

    if ($nextStatus === 'delivered' && (int) $driver->id !== (int) $order->user_id) {
        $this->notifyOrderOwner(
            (int) $order->user_id,
            $order->id,
            'delivered',
            $this->orderKindText($order, 'اكتملت رحلتك', 'تم توصيل طلبك'),
            $this->orderKindText(
                $order,
                'قام السائق بإيصالك إلى وجهتك بنجاح.',
                'قام السائق بتوصيل طلبك بنجاح.'
            ),
            $Notification
        );
    }
}

public function getOrderTracking($orderId)
{
    $user = JWTAuth::parseToken()->authenticate();

    // Reachable by whichever side is asking — the seller who placed the
    // order or the driver currently assigned to it (mirrors getOrder).
    $order = Order::where('id', $orderId)
        ->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('driver_id', $user->id);
        })
        ->first();

    if (!$order) {
        return response()->json([
            'result' => false,
            'message' => 'Order not found or unauthorized access',
        ], 404);
    }

    // Fetched unconditionally (not just while actively trackable) so the
    // order details map can always show a pickup/destination route preview,
    // with live driver tracking layered on top only once the trip is
    // actually underway.
    $addresses = Address::where('order_id', $order->id)->get()->keyBy('direction');
    $startAddress = $addresses->get('start_address');
    $destinationAddress = $addresses->get('destination_address');
    $routeCoordinates = [
        'start_address' => $startAddress->address_line1 ?? null,
        'destination_address' => $destinationAddress->address_line1 ?? null,
        'start_lat' => $startAddress ? (float) $startAddress->latitude : null,
        'start_lng' => $startAddress ? (float) $startAddress->longitude : null,
        'destination_lat' => $destinationAddress ? (float) $destinationAddress->latitude : null,
        'destination_lng' => $destinationAddress ? (float) $destinationAddress->longitude : null,
    ];

    if (!$order->driver_id || !in_array($order->status, self::TRACKING_ACTIVE_STATUSES, true)) {
        return response()->json([
            'result' => true,
            'trackable' => false,
            'status' => $order->status,
            ...$routeCoordinates,
        ], 200);
    }

    $isPickupLeg = $order->status === 'on_way';
    $targetAddress = $isPickupLeg ? $startAddress : $destinationAddress;

    if (!$targetAddress) {
        return response()->json([
            'result' => false,
            'message' => 'Address not found',
        ], 404);
    }

    $driver = User::find($order->driver_id);
    if (!$driver || $driver->latitude === null || $driver->longitude === null) {
        return response()->json([
            'result' => true,
            'trackable' => false,
            'status' => $order->status,
            'message' => 'Driver location not available yet',
            ...$routeCoordinates,
        ], 200);
    }

    $distanceKm = $this->haversineDistance(
        (float) $driver->latitude,
        (float) $driver->longitude,
        (float) $targetAddress->latitude,
        (float) $targetAddress->longitude
    );
    $speedKmh = max((float) ($driver->speed_kmh ?? 0), $this->trackingMinSpeedKmh());
    $etaMinutes = max(1, (int) round(($distanceKm / $speedKmh) * 60));

    // Driver-inactivity detection: there's no dedicated cron in this
    // environment (see expireStaleOrderRequests' comment for the same
    // constraint elsewhere), so this runs opportunistically from here
    // instead — the seller/driver screen watching this trip polls every few
    // seconds while it's open, which is exactly when a driver who's gone
    // quiet mid-trip (background service killed, phone died, no signal)
    // needs to be surfaced rather than the map just looking frozen with no
    // explanation.
    $secondsSinceLastUpdate = $driver->last_seen_at
        ? now()->diffInSeconds($driver->last_seen_at)
        : null;
    $inactivityLevel = $secondsSinceLastUpdate !== null
        ? OrderTrackingRules::inactivityLevel(
            $secondsSinceLastUpdate,
            (int) config('tracking.inactivity_warning_seconds', 120),
            (int) config('tracking.inactivity_stale_seconds', 300)
        )
        : null;

    if ($inactivityLevel === 'warning') {
        Log::channel('tracking')->warning('driver inactive: no location update in a while', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'seconds_since_last_update' => $secondsSinceLastUpdate,
        ]);
    } elseif ($inactivityLevel === 'stale') {
        Log::channel('tracking')->warning('driver inactive: tracking flagged stale', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'seconds_since_last_update' => $secondsSinceLastUpdate,
        ]);
    }

    return response()->json([
        'result' => true,
        'trackable' => true,
        'target' => $isPickupLeg ? 'pickup' : 'destination',
        'status' => $order->status,
        'target_lat' => (float) $targetAddress->latitude,
        'target_lng' => (float) $targetAddress->longitude,
        'driver_lat' => (float) $driver->latitude,
        'driver_lng' => (float) $driver->longitude,
        'driver_heading' => $driver->heading !== null ? (float) $driver->heading : null,
        'driver_speed_kmh' => $driver->speed_kmh !== null ? (float) $driver->speed_kmh : null,
        'driver_last_seen_at' => optional($driver->last_seen_at)->toIso8601String(),
        'distance_remaining_km' => round($distanceKm, 3),
        'eta_minutes' => $etaMinutes,
        // Additive fields — an older client build that doesn't know about
        // them simply ignores them, same as any other new key.
        'seconds_since_last_driver_update' => $secondsSinceLastUpdate,
        'tracking_stale' => $inactivityLevel === 'stale',
        ...$routeCoordinates,
    ], 200);
}

///////////////////////////////Drivers for Order////////////////////
public function getOrderDrivers(Request $request, $orderId = 34)
{
    $user = JWTAuth::parseToken()->authenticate();
    $genderFilter = in_array($request->query('gender'), ['male', 'female'], true)
        ? $request->query('gender')
        : null;

    $order = Order::where('orders.user_id', $user->id)
        ->where('orders.id', $orderId)
        ->join('addresses as address_from', 'address_from.order_id', '=', 'orders.id')
        ->where('address_from.direction', 'start_address')
        ->join('addresses as address_to', 'address_to.order_id', '=', 'orders.id')
        ->where('address_to.direction', 'destination_address')
        ->join('users', 'users.id', '=', 'orders.user_id')
        ->select(
            'orders.*',
            'users.name as username',
            'orders.created_at as date',
            'users.avatar as profileImage',
            'orders.id as order_id',
            'address_from.address_line1 as start_address',
            'address_to.address_line1 as destination_address',
            'address_from.region as start_region',
            'address_to.region as destination_region',
            'address_from.city as start_city',
            'address_to.city as destination_city',
            'address_from.latitude as start_lat',
            'address_from.longitude as start_lng',
            'address_to.latitude as destination_lat',
            'address_to.longitude as destination_lng'
        )
        ->first();

    if (!$order) {
        return response()->json([
            'result' => false,
            'message' => 'Order not found'
        ], 404);
    }

    $passengerRoute = $this->decodeRoutePoints($order->route_points ?? null);
    if (empty($passengerRoute) && $order->start_lat !== null && $order->destination_lat !== null) {
        $passengerRoute = [
            ['lat' => (float) $order->start_lat, 'lng' => (float) $order->start_lng],
            ['lat' => (float) $order->destination_lat, 'lng' => (float) $order->destination_lng],
        ];
    }

    $passengerPickup = [
        'lat' => (float) $order->start_lat,
        'lng' => (float) $order->start_lng,
    ];
    $passengerDestination = [
        'lat' => (float) $order->destination_lat,
        'lng' => (float) $order->destination_lng,
    ];
    $passengerDistanceKm = $this->resolveTripDistanceKm(
        $passengerPickup,
        $passengerDestination,
        $order->route_distance_km
            ? (float) $order->route_distance_km
            : $this->calculatePolylineDistance($passengerRoute)
    );

    $drivers = $this->findMatchingDrivers(
        $passengerRoute,
        $passengerPickup,
        $passengerDestination,
        $passengerDistanceKm,
        $order->order_kind === 'taxi' ? 0 : 1,
        false,
        $order->start_city,
        $order->start_region,
        $order->destination_city,
        $order->destination_region,
        $genderFilter
    );

    return response()->json([
        'result' => true,
        'message' => 'Order found',
        'data' => $drivers
    ], 200);
}

////////////////////////////////Orders For Driver////////////////////
public function getOrdersForDriver(Request $request, FcmMessagingService $Notification)
{
    $user = JWTAuth::parseToken()->authenticate();
    $driverUserId = $user->id;

    $this->expireStaleOrderRequests($Notification);

    $page = max(1, (int) $request->input('page', 1));
    $perPage = min(50, max(1, (int) $request->input('per_page', 20)));
    $offset = ($page - 1) * $perPage;

    $baseSql = "
      FROM orders as o
      JOIN users as u ON u.id = o.user_id
      JOIN addresses AS s_a ON o.id = s_a.order_id AND s_a.direction = 'start_address'
      JOIN addresses AS d_a ON o.id = d_a.order_id AND d_a.direction = 'destination_address'
      WHERE o.driver_id = ? AND o.status NOT IN ('driver_rejected', 'request_expired')
    ";
    $bindings = [$driverUserId];

    $status = $request->input('status');
    if (!empty($status)) {
        // Accepts a comma-separated list too (e.g. "on_way,picked_up,in_transit")
        // so the driver dashboard can pull every currently-active trip in one
        // call instead of scanning the paginated general list, where an older
        // trip that's stayed open a while can fall off the page as newer
        // orders pile up on top of it by created_at.
        $statusList = array_values(array_filter(array_map('trim', explode(',', $status))));
        if (count($statusList) === 1) {
            $baseSql .= ' AND o.status = ?';
            $bindings[] = $statusList[0];
        } elseif (count($statusList) > 1) {
            $placeholders = implode(',', array_fill(0, count($statusList), '?'));
            $baseSql .= " AND o.status IN ($placeholders)";
            foreach ($statusList as $singleStatus) {
                $bindings[] = $singleStatus;
            }
        }
    }

    $keyword = $request->input('keyword');
    if (!empty($keyword)) {
        $like = '%' . $keyword . '%';
        $baseSql .= ' AND (o.tracking_id LIKE ? OR u.name LIKE ?)';
        $bindings[] = $like;
        $bindings[] = $like;
    }

    // See the seller-side endpoint above: date_from/date_to are full UTC
    // timestamps for an exclusive [from, to) range, not bare dates.
    $dateFrom = $request->input('date_from');
    if (!empty($dateFrom)) {
        $baseSql .= ' AND o.created_at >= ?';
        $bindings[] = $dateFrom;
    }

    $dateTo = $request->input('date_to');
    if (!empty($dateTo)) {
        $baseSql .= ' AND o.created_at < ?';
        $bindings[] = $dateTo;
    }

    $totalCount = (int) (DB::selectOne("SELECT COUNT(*) as count $baseSql", $bindings)->count ?? 0);

    $dataSql = "
      SELECT o.id as order_id, o.description, o.status, o.order_kind, o.tracking_id, o.created_at as date, o.updated_at, o.driver_accepted_at, o.route_distance_km, o.cancel_reason, o.price, u.avatar as profileImage, u.name as username, o.driver_id,
        s_a.address_line1 as start_address, d_a.address_line1 as destination_address,
        s_a.location_note as start_location_note,
        d_a.location_note as destination_location_note,
        (SELECT COUNT(*) FROM order_messages WHERE order_messages.order_id = o.id AND order_messages.sender_id != ? AND order_messages.is_read = 0) as unread_chat_count,
        (SELECT COALESCE(date, day) FROM schedules WHERE schedules.order_id = o.id ORDER BY schedules.id DESC LIMIT 1) as scheduled_date,
        (SELECT time_from FROM schedules WHERE schedules.order_id = o.id ORDER BY schedules.id DESC LIMIT 1) as scheduled_time_from,
        (SELECT time_to FROM schedules WHERE schedules.order_id = o.id ORDER BY schedules.id DESC LIMIT 1) as scheduled_time_to
      $baseSql
      ORDER BY o.created_at DESC
      LIMIT ? OFFSET ?
    ";
    $orders = DB::select($dataSql, [$driverUserId, ...$bindings, $perPage, $offset]);

    return response()->json([
        'result' => true,
        'total_count' => $totalCount,
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => ($page * $perPage) < $totalCount,
        'message' => 'Order found',
        'data' => $orders
    ], 200);
}

public function getFareSettings()
{
    $user = JWTAuth::parseToken()->authenticate();

    return response()->json([
        'result' => true,
        'message' => 'Fare settings loaded',
        'data' => [
            'base_taxi' => $this->getSettingFloat('fare.base_taxi', 2.50),
            'base_delivery' => $this->getSettingFloat('fare.base_delivery', 3.00),
            'per_km_taxi' => $this->getSettingFloat('fare.per_km_taxi', 1.20),
            'per_km_delivery' => $this->getSettingFloat('fare.per_km_delivery', 1.00),
            'shared_multiplier' => $this->getSettingFloat('fare.shared_multiplier', 0.70),
            'route_deviation_km' => $this->getRouteDeviationThresholdKm(),
            'detour_surcharge_per_km' => $this->getDetourSurchargePerKm(),
            'reservation_multiplier' => $this->getReservationMultiplier(),
            'currency' => 'USD',
            'viewer_id' => $user->id,
        ],
    ], 200);
}

// This method finds drivers based on the city of the start and destination address
private function findDriversByCity($startCity, $destinationCity, $startRegion, $destinationRegion)
{
    // Find drivers based on the city of the start and destination addresses
    $drivers = Address::where('order_id',0)  // Only drivers (no order_id)
        ->where(function ($query) use ($startCity, $destinationCity) {
            $query->where('city', $startCity)  // Match the start address city
                ->orWhere('city', $destinationCity);  // Or match the destination address city
        })->orWhere(function ($query) use ($startRegion, $destinationRegion) {
            // Add region as a second priority condition
            $query->where('region', $startRegion)  // Match the start address region
                ->orWhere('region', $destinationRegion);  // Or match the destination address region
        })->join('users', function($join) {
            $join->on('users.id', '=', 'addresses.user_id')  // Join condition
                 ->where('addresses.order_id', 0); // Additional condition for order_id
        })
        ->select( // Select all columns from addresses
            'users.id as driver_id',  // Driver's name
            'users.name as driver_name',  // Driver's name
            'users.email as driver_email',  // Driver's email (add more user fields as necessary)
            'users.avatar as driver_avatar',  // Driver's avatar
            'users.phone as driver_phone' , // Driver's phone number (add more user fields if necessary)
            'users.fcm_token as fcm_token' ,
        )->groupBy('users.id')
        ->get();
   
    return $drivers;
}


public function getDriversNearClient($clientStartLat, $clientStartLon, $clientEndLat, $clientEndLon) {
    try {

        // Select drivers and their start and destination addresses from the database
        $driversWithAddresses = DB::select("
            SELECT 
                users.id, 
                users.phone, 
                MAX(start_address.latitude) as start_lat, 
                MAX(start_address.longitude) as start_lon, 
                MAX(destination_address.latitude) as dest_lat, 
                MAX(destination_address.longitude) as dest_lon,
                -- Calculate the distance between the driver's start location and the client's start location
                (6371 * acos(cos(radians(MAX(start_address.latitude))) 
                    * cos(radians(?)) 
                    * cos(radians(?) - radians(MAX(start_address.longitude))) 
                    + sin(radians(MAX(start_address.latitude))) 
                    * sin(radians(?)))) AS distance_from_start,
                -- Calculate the distance between the driver's end location and the client's destination location
                (6371 * acos(cos(radians(MAX(destination_address.latitude))) 
                    * cos(radians(?)) 
                    * cos(radians(?) - radians(MAX(destination_address.longitude))) 
                    + sin(radians(MAX(destination_address.latitude))) 
                    * sin(radians(?)))) AS distance_from_end
            FROM users
            JOIN addresses AS start_address ON users.id = start_address.user_id
            JOIN addresses AS destination_address ON users.id = destination_address.user_id
            WHERE start_address.direction = 'start_address'
              AND destination_address.direction = 'destination_address'
              AND start_address.order_id = 0 
              AND destination_address.order_id = 0
            GROUP BY users.id, users.phone
            HAVING distance_from_start < 50   -- within 10 km from the start
               AND distance_from_end < 50     -- within 10 km from the destination
        ", [
            $clientStartLat,  // client_start.latitude
            $clientStartLon,  // client_start.longitude
            $clientStartLat,  // client_start.latitude again for Haversine formula
            $clientEndLat,    // client_end.latitude
            $clientEndLon,    // client_end.longitude
            $clientEndLat,    // client_end.latitude again for Haversine formula
        ]);
   
        return $driversWithAddresses;
    } catch (\Exception $e) {
        \Log::error("Error: " . $e->getMessage());
        return response()->json(["error" => "Something went wrong"], 500);
    }
}

public function checkIfOrderInsideDriverPath($clientStartLat, $clientStartLon, $clientEndLat, $clientEndLon) {
    try {
       
        // Select drivers and their start and destination addresses from the database
        $driversWithAddresses = DB::select("
        SELECT 
            users.id, 
            users.phone, 
            start_address.id AS start_id, 
            destination_address.id AS destination_id, 
            start_address.latitude AS start_lat, 
            start_address.longitude AS start_lon, 
            destination_address.latitude AS dest_lat, 
            destination_address.longitude AS dest_lon,
            schedules.start_address AS schedule_start_address,
            schedules.destination_address AS schedule_dest_address
        FROM users
        JOIN addresses AS start_address ON users.id = start_address.user_id
        JOIN addresses AS destination_address ON users.id = destination_address.user_id
        JOIN schedules ON start_address.id = schedules.start_address
        AND destination_address.id = schedules.destination_address
        WHERE start_address.direction = 'start_address'
          AND destination_address.direction = 'destination_address'
          AND start_address.order_id = 0 
          AND destination_address.order_id = 0
        GROUP BY schedules.start_address, schedules.destination_address, users.id
    ");
    
        
        // Initialize an array to store drivers within path
        $driversWithinPath = [];
    
        foreach ($driversWithAddresses as $driver) {
            $driverStartLat = $driver->start_lat;
            $driverStartLon = $driver->start_lon;
            $driverEndLat = $driver->dest_lat;
            $driverEndLon = $driver->dest_lon;
    
            // Check if the order's path (start and end points) is inside the driver's path
            if (
                ($clientStartLat >= min($driverStartLat, $driverEndLat) && $clientStartLat <= max($driverStartLat, $driverEndLat)) &&
                ($clientStartLon >= min($driverStartLon, $driverEndLon) && $clientStartLon <= max($driverStartLon, $driverEndLon)) &&
                ($clientEndLat >= min($driverStartLat, $driverEndLat) && $clientEndLat <= max($driverStartLat, $driverEndLat)) &&
                ($clientEndLon >= min($driverStartLon, $driverEndLon) && $clientEndLon <= max($driverStartLon, $driverEndLon))
            ) {
                // Add driver to the results if the order's path is inside the driver's path segment
                $driversWithinPath[] = $driver;
            }
        }
    
        // Return the filtered list of drivers that meet the conditions
        return $driversWithAddresses;
    
    } catch (\Exception $e) {
        echo 'testt'. $e->getMessage();exit;
        \Log::error("Error: " . $e->getMessage());
        return response()->json(["error" => "Something went wrong"], 500);
    }

}


public function isPathInPath($startLat, $startLon, $endLat, $endLon, $orderStartLat, $orderStartLon, $orderDestLat, $orderDestLon)
{
    // Check if the order's path (start to destination) lies within the main path's start to destination
    // Tolerance value in km (you can adjust based on your needs)
    $tolerance = 35; // 5 km

    // Check if start and destination of the order fall within the main path's boundaries
    $distanceStart = $this->haversineDistance($startLat, $startLon, $orderStartLat, $orderStartLon);
    $distanceEnd = $this->haversineDistance($endLat, $endLon, $orderDestLat, $orderDestLon);

    // If both points of the inner path are within the tolerance of the main path
    return $distanceStart <= $tolerance && $distanceEnd <= $tolerance;
}



}

