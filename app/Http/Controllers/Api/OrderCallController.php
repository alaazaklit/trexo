<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Order;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderCallController extends Controller
{
    // Voice calling runs entirely client-side over Agora's free tier (10,000
    // minutes/month, no telephony billing) — real phone numbers are never
    // involved, so there's no masking/PSTN bridging to do server-side.
    // This endpoint's only job is to "ring" the other party: send them a
    // push telling them which Agora channel to join, since Agora itself
    // only lets people join a channel they already know about.
    public function initiateCall(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $orderId = (int) $request->input('order_id');
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'result' => false,
                'message' => 'الطلب غير موجود.',
            ], 404);
        }

        $isSeller = (int) $order->user_id === (int) $user->id;
        $isDriver = $order->driver_id !== null && (int) $order->driver_id === (int) $user->id;

        if (!$isSeller && !$isDriver) {
            return response()->json([
                'result' => false,
                'message' => 'لا يمكنك الاتصال بخصوص هذا الطلب.',
            ], 403);
        }

        $targetUserId = $isSeller ? $order->driver_id : $order->user_id;
        if (!$targetUserId) {
            return response()->json([
                'result' => false,
                'message' => 'لم يتم تعيين سائق لهذا الطلب بعد.',
            ], 422);
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser || empty($targetUser->fcm_token)) {
            return response()->json([
                'result' => false,
                'message' => 'الطرف الآخر غير متصل بالإنترنت حالياً.',
            ], 200);
        }

        // A channel name unique to this order — both sides join the same
        // one, Agora's servers relay the audio between them from there.
        $channelName = 'order_' . $order->id;

        $Notification->sendDataMessage(
            $targetUser->fcm_token,
            [
                'section' => 'call',
                'ref_id' => (string) $order->id,
                'channel_name' => $channelName,
                'caller_name' => $user->name,
                'caller_id' => (string) $user->id,
            ]
        );

        return response()->json([
            'result' => true,
            'message' => 'جاري الاتصال...',
            'channel_name' => $channelName,
        ], 200);
    }
}
