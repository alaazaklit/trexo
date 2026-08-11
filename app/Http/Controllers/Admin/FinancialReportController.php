<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['date_from', 'date_to']);

        $query = Transaction::query();
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Per-driver aggregate over the immutable ledger — never recomputed
        // from orders/reservations directly, so a later commission-rate
        // change can't silently corrupt this report for past periods.
        $rows = $query->select('driver_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_amount) as total_value')
            ->selectRaw('SUM(commission_amount) as trexo_profit')
            ->selectRaw('SUM(driver_earnings) as driver_profit')
            ->groupBy('driver_id')
            ->with('driver.user')
            ->orderByDesc('trexo_profit')
            ->get();

        $rows->each(function (Transaction $row) {
            $current = $row->driver !== null ? $this->subscriptions->currentSubscriptionFor($row->driver) : null;
            $row->current_plan = $current?->plan?->name ?? 'Basic';
            $row->subscription_expiry = $current?->end_date;
        });

        return view('admin.financial-reports.index', [
            'pageTitle' => 'Financial Report',
            'rows' => $rows,
            'filters' => $filters,
            'totals' => [
                'orders_count' => $rows->sum('orders_count'),
                'total_value' => $rows->sum('total_value'),
                'trexo_profit' => $rows->sum('trexo_profit'),
                'driver_profit' => $rows->sum('driver_profit'),
            ],
        ]);
    }
}
