<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Order;
use App\Reservation;
use App\TripRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class RatingController extends Controller
{
    public function submitRating(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|integer',
            'reservation_id' => 'nullable|integer',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $orderId = $request->input('order_id');
        $reservationId = $request->input('reservation_id');

        if ((!$orderId && !$reservationId) || ($orderId && $reservationId)) {
            return response()->json([
                'result' => false,
                'message' => 'Provide exactly one of order_id or reservation_id',
            ], 400);
        }

        if ($orderId) {
            $entity = Order::find($orderId);
            if (!$entity) {
                return response()->json(['result' => false, 'message' => 'Order not found'], 404);
            }
            if ((int) $user->id !== (int) $entity->user_id && (int) $user->id !== (int) $entity->driver_id) {
                return response()->json(['result' => false, 'message' => 'Not part of this order'], 403);
            }
            if ($entity->status !== 'delivered') {
                return response()->json(['result' => false, 'message' => 'Order is not delivered yet'], 400);
            }
            $ratedUserId = (int) $user->id === (int) $entity->user_id ? $entity->driver_id : $entity->user_id;
            $lookup = ['order_id' => $orderId, 'reservation_id' => null];
        } else {
            $entity = Reservation::find($reservationId);
            if (!$entity) {
                return response()->json(['result' => false, 'message' => 'Reservation not found'], 404);
            }
            if ((int) $user->id !== (int) $entity->seller_id && (int) $user->id !== (int) $entity->driver_id) {
                return response()->json(['result' => false, 'message' => 'Not part of this reservation'], 403);
            }
            if ($entity->status !== 'completed') {
                return response()->json(['result' => false, 'message' => 'Reservation is not completed yet'], 400);
            }
            $ratedUserId = (int) $user->id === (int) $entity->seller_id ? $entity->driver_id : $entity->seller_id;
            $lookup = ['order_id' => null, 'reservation_id' => $reservationId];
        }

        if (!$ratedUserId) {
            return response()->json(['result' => false, 'message' => 'No counterpart to rate'], 400);
        }

        $existing = TripRating::where($lookup)->where('rater_user_id', $user->id)->first();
        if ($existing) {
            return response()->json([
                'result' => true,
                'already_rated' => true,
                'message' => 'لقد قمت بالتقييم مسبقاً',
                'data' => $existing,
            ], 200);
        }

        $ratingRow = TripRating::create(array_merge($lookup, [
            'rater_user_id' => $user->id,
            'rated_user_id' => $ratedUserId,
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment'),
        ]));

        // If the rated party is a driver, refresh their aggregate rating column.
        $ratedUser = User::find($ratedUserId);
        if ($ratedUser && strtolower((string) $ratedUser->type) === 'driver') {
            $average = TripRating::where('rated_user_id', $ratedUserId)->avg('rating');
            DB::table('drivers')->where('user_id', $ratedUserId)->update(['rating' => round($average, 2)]);
        }

        return response()->json([
            'result' => true,
            'already_rated' => false,
            'message' => 'تم إرسال التقييم بنجاح',
            'data' => $ratingRow,
        ], 201);
    }

    public function getRating(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $orderId = $request->input('order_id');
        $reservationId = $request->input('reservation_id');

        if (!$orderId && !$reservationId) {
            return response()->json(['result' => false, 'message' => 'Provide order_id or reservation_id'], 400);
        }

        $query = TripRating::where('rater_user_id', $user->id);
        if ($orderId) {
            $query->where('order_id', $orderId);
        } else {
            $query->where('reservation_id', $reservationId);
        }

        $rating = $query->first();

        return response()->json([
            'result' => true,
            'rated' => (bool) $rating,
            'rating' => $rating->rating ?? null,
            'comment' => $rating->comment ?? null,
        ], 200);
    }
}
