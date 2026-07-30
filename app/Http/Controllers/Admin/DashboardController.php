<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.dashboard', [
            'pageTitle' => 'Dashboard',
            'rangeDays' => (int) $request->query('range', 7),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'result' => true,
            'data' => $this->service->payload((int) $request->query('range', 7)),
        ]);
    }
}
