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
use App\Notification;
use App\Order;
use App\Reservation;
use Illuminate\Support\Facades\DB;


class NotificationsController extends Controller
{

function updateNotification(Request $request)
{
    // Step 1: Authenticate the user using JWT
    $user = JWTAuth::parseToken()->authenticate();

    $result=true;
    $msg="Order Status has been updated";

    $cond['ref_id'] = intval($request->input('ref_id'));
    $cond['user_id'] = intval($request->input('user_id'));
    $cond['section']=$request->input('section');

    DB::table('notifications')
    ->where($cond)
    ->update(['is_read' => 1]);
    
    return response()->json([
        'result' => $result,
        'message' => $msg,
        'data' =>$user 
    ], 201);
}

function updateFCMToken(Request $request)
{
    // Step 1: Authenticate the user using JWT
    $user = JWTAuth::parseToken()->authenticate();

    $result=true;
    $msg="FCMToken has been updated";

    $cond['id'] = $user->id;
    $FCMToken=$request->input('FCMToken');

    DB::table('users')
    ->where($cond)
    ->update(['fcm_token' => $FCMToken]);
    
    return response()->json([
        'result' => $result,
        'message' => $msg,
        'data' =>$user 
    ], 201);
}


function getNotifications(Request $request)
{   $user = JWTAuth::parseToken()->authenticate();

    // Default to the full history (read + unread) sorted newest-first, like
    // a real inbox. Callers can still pass ?is_read=0/1 to filter.
    $query = Notification::where('notifications.user_id', $user->id)
    ->leftJoin('users as from_user', 'from_user.id', '=', 'notifications.user_id')
    ->select(
        'notifications.*',
        'from_user.name as from_user_name',
        'from_user.name as username',
        'from_user.avatar as profileImage',
        DB::raw("(SELECT COUNT(*) FROM notifications WHERE user_id = {$user->id}) as total_count"),
        DB::raw("(SELECT COUNT(*) FROM notifications WHERE is_read=0 and user_id = {$user->id}) as unread_total_count"),
    );

    if ($request->has('is_read')) {
        $query->where('notifications.is_read', $request->input('is_read'));
    }

    $notifications = $query->orderBy('notifications.created_at', 'desc')->get();

    // `notifications.data` is a frozen snapshot of the order/reservation
    // status at the moment the row was created — it goes stale the instant
    // another driver accepts the same request, the seller cancels it, or it
    // auto-expires. Attach the live status in bulk (one query per section,
    // not per row) so the app can tell a driver their accept/decline
    // buttons no longer apply instead of leaving them tappable on a
    // request that already moved on.
    $orderIds = $notifications->where('section', 'orders')->pluck('ref_id')->unique()->values();
    $reservationIds = $notifications->where('section', 'reservations')->pluck('ref_id')->unique()->values();

    $orders = Order::whereIn('id', $orderIds)
        ->get(['id', 'status', 'order_kind', 'driver_id'])
        ->keyBy('id');

    $reservations = Reservation::whereIn('id', $reservationIds)
        ->get(['id', 'status', 'driver_id'])
        ->keyBy('id');

    foreach ($notifications as $notification) {
        $order = $notification->section === 'orders' ? $orders->get($notification->ref_id) : null;
        $reservation = $notification->section === 'reservations' ? $reservations->get($notification->ref_id) : null;

        $notification->current_status = $order->status ?? $reservation->status ?? null;
        $notification->current_order_kind = $order->order_kind ?? null;
        $notification->current_driver_id = $order->driver_id ?? $reservation->driver_id ?? null;
    }

    $totalCount = $notifications[0]->total_count ?? 0;
    $unReadCount = $notifications[0]->unread_total_count ?? 0;
    return response()->json([
        'result' => true,
        'total_count' => $totalCount,
        'unReadCount' => $unReadCount,
        'message' => 'All Records',
        'data' =>$notifications
    ], 201);

}

function markAllNotificationsRead(Request $request)
{
    $user = JWTAuth::parseToken()->authenticate();

    DB::table('notifications')
        ->where('user_id', $user->id)
        ->where('is_read', 0)
        ->update(['is_read' => 1]);

    return response()->json([
        'result' => true,
        'message' => 'All notifications marked as read',
    ], 201);
}







}
