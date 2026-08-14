<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestDispatchLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequestReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['seller_id', 'driver_id', 'request_type', 'outcome', 'date_from', 'date_to']);

        $logs = RequestDispatchLog::with(['seller', 'driver', 'order', 'reservation'])
            ->filter($filters)
            ->orderByDesc('sent_at')
            ->paginate(50)
            ->withQueryString();

        $totals = RequestDispatchLog::query()->filter($filters)
            ->selectRaw('outcome, COUNT(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        return view('admin.request-reports.index', [
            'pageTitle' => 'Requests Report',
            'logs' => $logs,
            'filters' => $filters,
            'totals' => $totals,
            'sellers' => User::whereIn('id', RequestDispatchLog::query()->distinct()->pluck('seller_id'))->orderBy('name')->get(['id', 'name', 'phone']),
            'drivers' => User::whereIn('id', RequestDispatchLog::query()->distinct()->pluck('driver_id'))->orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }
}
