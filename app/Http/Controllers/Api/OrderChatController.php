<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderMessage;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderChatController extends Controller
{
    // Loads the order and confirms the authenticated user is one of its two
    // parties (the seller who placed it or the driver assigned to it) —
    // every method below needs this same check.
    private function authorizedOrder(int $orderId, $user): ?Order
    {
        $order = Order::find($orderId);
        if (!$order) {
            return null;
        }

        $isSeller = (int) $order->user_id === (int) $user->id;
        $isDriver = $order->driver_id !== null && (int) $order->driver_id === (int) $user->id;

        return ($isSeller || $isDriver) ? $order : null;
    }

    public function sendMessage(Request $request, FcmMessagingService $Notification)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $order = $this->authorizedOrder((int) $request->input('order_id'), $user);
        if (!$order) {
            return response()->json([
                'result' => false,
                'message' => 'الطلب غير موجود أو لا يمكنك الوصول إليه.',
            ], 404);
        }

        $isSeller = (int) $order->user_id === (int) $user->id;
        $recipientId = $isSeller ? $order->driver_id : $order->user_id;

        if (!$recipientId) {
            return response()->json([
                'result' => false,
                'message' => 'لم يتم تعيين سائق لهذا الطلب بعد.',
            ], 422);
        }

        $message = OrderMessage::create([
            'order_id' => $order->id,
            'sender_id' => $user->id,
            'message' => $request->input('message'),
        ]);

        $recipient = User::find($recipientId);
        if ($recipient && !empty($recipient->fcm_token)) {
            $preview = mb_strlen($message->message) > 80
                ? mb_substr($message->message, 0, 80) . '...'
                : $message->message;

            $Notification->sendNotification([
                [
                    'fcm_token' => $recipient->fcm_token,
                    'user_id' => $recipient->id,
                    'ref_id' => $order->id,
                ],
            ], $user->name . ' :رسالة جديدة', $preview, 'chat');
        }

        return response()->json([
            'result' => true,
            'message' => 'تم إرسال الرسالة',
            'data' => [
                'id' => $message->id,
                'order_id' => $message->order_id,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'is_read' => (bool) $message->is_read,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    public function getMessages(Request $request, $order_id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $order = $this->authorizedOrder((int) $order_id, $user);
        if (!$order) {
            return response()->json([
                'result' => false,
                'message' => 'الطلب غير موجود أو لا يمكنك الوصول إليه.',
            ], 404);
        }

        // Visiting the thread implicitly reads whatever the other party sent.
        OrderMessage::where('order_id', $order->id)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = OrderMessage::where('order_messages.order_id', $order->id)
            ->leftJoin('users as sender', 'sender.id', '=', 'order_messages.sender_id')
            ->orderBy('order_messages.created_at', 'asc')
            ->select(
                'order_messages.*',
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
