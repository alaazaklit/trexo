<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Reservation;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReservationCallController extends Controller
{
    // Mirrors OrderCallController::initiateCall — same Agora-over-FCM
    // "ring" relay, scoped to a reservation instead of an order.
    public function initiateCall(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $reservationId = (int) $request->input('reservation_id');
        $reservation = Reservation::find($reservationId);

        if (!$reservation) {
            return response()->json([
                'result' => false,
                'message' => 'الحجز غير موجود.',
            ], 404);
        }

        $isSeller = (int) $reservation->seller_id === (int) $user->id;
        $isDriver = $reservation->driver_id !== null && (int) $reservation->driver_id === (int) $user->id;

        if (!$isSeller && !$isDriver) {
            return response()->json([
                'result' => false,
                'message' => 'لا يمكنك الاتصال بخصوص هذا الحجز.',
            ], 403);
        }

        $targetUserId = $isSeller ? $reservation->driver_id : $reservation->seller_id;
        if (!$targetUserId) {
            return response()->json([
                'result' => false,
                'message' => 'لم يتم تعيين سائق لهذا الحجز بعد.',
            ], 422);
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser || empty($targetUser->fcm_token)) {
            return response()->json([
                'result' => false,
                'message' => 'الطرف الآخر غير متصل بالإنترنت حالياً.',
            ], 200);
        }

        // Distinct prefix from order calls ('reservation_' not 'order_') so
        // channel names — and the 'reservation_call' section below — can
        // never collide with an order that happens to share the same id.
        $channelName = 'reservation_' . $reservation->id;

        $Notification->sendDataMessage(
            $targetUser->fcm_token,
            [
                'section' => 'reservation_call',
                'ref_id' => (string) $reservation->id,
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
