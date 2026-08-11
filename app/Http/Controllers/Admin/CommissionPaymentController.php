<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayment;
use App\Services\Wallet\CommissionPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionPaymentController extends Controller
{
    public function __construct(private readonly CommissionPaymentService $payments)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['status']);

        $query = CommissionPayment::query()->with(['driver.user'])->latest('id');
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('admin.commission-payments.index', [
            'pageTitle' => 'Commission Payments',
            'payments' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => CommissionPayment::STATUSES,
        ]);
    }

    public function approve(CommissionPayment $commissionPayment): RedirectResponse
    {
        try {
            $this->payments->approve($commissionPayment);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Commission payment approved — driver\'s owed balance reduced.');
    }

    public function reject(Request $request, CommissionPayment $commissionPayment): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->payments->reject($commissionPayment, $data['rejection_reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Commission payment rejected.');
    }

    public function notify(CommissionPayment $commissionPayment): RedirectResponse
    {
        try {
            $this->payments->notify($commissionPayment);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Driver notified of the payment status.');
    }
}
