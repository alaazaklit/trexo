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
