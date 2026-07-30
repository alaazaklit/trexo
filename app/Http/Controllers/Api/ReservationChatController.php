<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Reservation;
use App\ReservationMessage;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReservationChatController extends Controller
{
    // Mirrors OrderChatController::authorizedOrder — confirms the
    // authenticated user is one of the reservation's two parties (the
    // seller who booked it or the driver assigned to it).
    private function authorizedReservation(int $reservationId, $user): ?Reservation
    {
        $reservation = Reservation::find($reservationId);
        if (!$reservation) {
            return null;
        }

        $isSeller = (int) $reservation->seller_id === (int) $user->id;
        $isDriver = $reservation->driver_id !== null && (int) $reservation->driver_id === (int) $user->id;

        return ($isSeller || $isDriver) ? $reservation : null;
    }

    public function sendMessage(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|integer',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $reservation = $this->authorizedReservation((int) $request->input('reservation_id'), $user);
        if (!$reservation) {
            return response()->json([
                'result' => false,
                'message' => 'الحجز غير موجود أو لا يمكنك الوصول إليه.',
            ], 404);
        }

        $isSeller = (int) $reservation->seller_id === (int) $user->id;
        $recipientId = $isSeller ? $reservation->driver_id : $reservation->seller_id;

        if (!$recipientId) {
            return response()->json([
                'result' => false,
                'message' => 'لم يتم تعيين سائق لهذا الحجز بعد.',
            ], 422);
        }

        $message = ReservationMessage::create([
            'reservation_id' => $reservation->id,
            'sender_id' => $user->id,
            'message' => $request->input('message'),
        ]);

        $recipient = User::find($recipientId);
        if ($recipient && !empty($recipient->fcm_token)) {
            $preview = mb_strlen($message->message) > 80
                ? mb_substr($message->message, 0, 80) . '...'
                : $message->message;

            // Distinct section from order chat ('reservation_chat' not
            // 'chat') so a reservation id can never be mistaken for an
            // order id by a currently-open OrderDetailsPage/OrderChatPage
            // (or vice versa) filtering on section + ref_id.
            $Notification->sendNotification([
                [
                    'fcm_token' => $recipient->fcm_token,
                    'user_id' => $recipient->id,
                    'ref_id' => $reservation->id,
                ],
            ], $user->name . ' :رسالة جديدة', $preview, 'reservation_chat');
        }

        return response()->json([
            'result' => true,
            'message' => 'تم إرسال الرسالة',
            'data' => [
                'id' => $message->id,
                'reservation_id' => $message->reservation_id,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'is_read' => (bool) $message->is_read,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    public function getMessages(Request $request, $reservation_id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $reservation = $this->authorizedReservation((int) $reservation_id, $user);
        if (!$reservation) {
            return response()->json([
                'result' => false,
                'message' => 'الحجز غير موجود أو لا يمكنك الوصول إليه.',
            ], 404);
        }

        // Visiting the thread implicitly reads whatever the other party sent.
        ReservationMessage::where('reservation_id', $reservation->id)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = ReservationMessage::where('reservation_messages.reservation_id', $reservation->id)
            ->leftJoin('users as sender', 'sender.id', '=', 'reservation_messages.sender_id')
            ->orderBy('reservation_messages.created_at', 'asc')
            ->select(
                'reservation_messages.*',
                'sender.name as sender_name',
                'sender.avatar as sender_avatar'
            )
            ->get();

        return response()->json([
            'result' => true,
            'current_user_id' => $user->id,
            'data' => $messages,
        ], 200);
    }
}
