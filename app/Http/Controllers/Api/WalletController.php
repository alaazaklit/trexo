<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayment;
use App\Models\Driver;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Order;
use App\Reservation;
use App\Services\Subscription\SubscriptionService;
use App\Services\Wallet\CommissionPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use TCG\Voyager\Models\Setting;

class WalletController extends Controller
{
    // "Pending" = anything not yet in one of these end states.
    private const TERMINAL_ORDER_STATUSES = ['driver_rejected', 'request_expired', 'canceled', 'delivered', 'failed_delivery'];
    private const TERMINAL_RESERVATION_STATUSES = ['completed', 'cancelled'];

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly CommissionPaymentService $commissionPayments,
    ) {
    }

    private function authenticatedDriver(): ?Driver
    {
        $user = JWTAuth::parseToken()->authenticate();

        return Driver::where('user_id', $user->id)->first();
    }

    private function debtLimit(): float
    {
        $value = Setting::where('key', 'wallet.debt_limit')->value('value');

        return $value === null || $value === '' ? 50.0 : (float) $value;
    }

    public function wallet()
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $wallet = $driver->wallet ?: Wallet::create(['driver_id' => $driver->id, 'commission_owed' => 0]);
        $debtLimit = $this->debtLimit();

        return response()->json([
            'result' => true,
            'message' => 'Wallet loaded',
            'data' => [
                'commission_owed' => (float) $wallet->commission_owed,
                'debt_limit' => $debtLimit,
                'over_limit' => (float) $wallet->commission_owed > $debtLimit,
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

        $query = Transaction::where('driver_id', $driver->id);

        $serviceType = $request->input('service_type');
        if (in_array($serviceType, ['taxi', 'delivery', 'bus'], true)) {
            $query->where('service_type', $serviceType);
        }

        $dateFrom = $request->input('date_from');
        if (!empty($dateFrom)) {
            $query->where('created_at', '>=', $dateFrom);
        }

        $dateTo = $request->input('date_to');
        if (!empty($dateTo)) {
            $query->where('created_at', '<', $dateTo);
        }

        $totalCount = $query->count();
        $transactions = $query->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get(['id', 'order_id', 'reservation_id', 'service_type', 'total_amount', 'commission_percentage', 'commission_amount', 'driver_earnings', 'status', 'created_at']);

        return response()->json([
            'result' => true,
            'total_count' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $totalCount,
            'message' => 'Transactions loaded',
            'data' => $transactions,
        ]);
    }

    public function dashboard()
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $userId = $driver->user_id;
        $today = now()->toDateString();

        $todayEarnings = (float) Transaction::where('driver_id', $driver->id)
            ->whereDate('created_at', $today)
            ->sum('driver_earnings');

        $monthEarnings = (float) Transaction::where('driver_id', $driver->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('driver_earnings');

        $completedOrders = Order::where('driver_id', $userId)->where('status', 'delivered')->count();
        $completedReservations = Reservation::where('driver_id', $userId)->where('status', 'completed')->count();

        $pendingOrders = Order::where('driver_id', $userId)
            ->whereNotIn('status', self::TERMINAL_ORDER_STATUSES)
            ->count();
        $pendingReservations = Reservation::where('driver_id', $userId)
            ->whereNotIn('status', self::TERMINAL_RESERVATION_STATUSES)
            ->count();

        $current = $this->subscriptions->currentSubscriptionFor($driver);
        $commissionOwed = $driver->wallet !== null ? (float) $driver->wallet->commission_owed : 0.0;
        $debtLimit = $this->debtLimit();

        return response()->json([
            'result' => true,
            'message' => 'Driver dashboard loaded',
            'data' => [
                'today_earnings' => $todayEarnings,
                'month_earnings' => $monthEarnings,
                'completed_orders' => $completedOrders + $completedReservations,
                'pending_orders' => $pendingOrders + $pendingReservations,
                'commission_percentage' => $current?->commission_percentage_snapshot !== null
                    ? (float) $current->commission_percentage_snapshot
                    : null,
                'current_plan' => $current?->plan?->name ?? 'Basic',
                'commission_owed' => $commissionOwed,
                'debt_limit' => $debtLimit,
                'over_limit' => $commissionOwed > $debtLimit,
            ],
        ]);
    }

    public function financialReport()
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $totalEarnings = (float) Transaction::where('driver_id', $driver->id)->sum('total_amount');
        $trexoCommission = (float) Transaction::where('driver_id', $driver->id)->sum('commission_amount');
        $netEarnings = (float) Transaction::where('driver_id', $driver->id)->sum('driver_earnings');
        $wallet = $driver->wallet;
        $current = $this->subscriptions->currentSubscriptionFor($driver);

        return response()->json([
            'result' => true,
            'message' => 'Financial report loaded',
            'data' => [
                'total_earnings' => $totalEarnings,
                'trexo_commission' => $trexoCommission,
                'net_earnings' => $netEarnings,
                'subscription_cost' => $current?->plan?->monthly_price !== null ? (float) $current->plan->monthly_price : 0.0,
                'commission_owed' => $wallet !== null ? (float) $wallet->commission_owed : 0.0,
            ],
        ]);
    }

    public function submitCommissionPayment(Request $request)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $payment = $this->commissionPayments->submit($driver, (float) $request->input('amount'), $request->file('receipt'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['result' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'result' => true,
            'message' => 'Payment submitted, pending review',
            'data' => $this->formatCommissionPayment($payment),
        ], 201);
    }

    public function commissionPayments()
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $payments = CommissionPayment::where('driver_id', $driver->id)->latest('id')->get();

        return response()->json([
            'result' => true,
            'message' => 'Commission payments loaded',
            'data' => $payments->map(fn (CommissionPayment $payment) => $this->formatCommissionPayment($payment)),
        ]);
    }

    private function formatCommissionPayment(CommissionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'rejection_reason' => $payment->rejection_reason,
            'created_at' => $payment->created_at?->toIso8601String(),
            'reviewed_at' => $payment->reviewed_at?->toIso8601String(),
        ];
    }
}
