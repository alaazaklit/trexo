<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['payment_status', 'plan_id']);

        $query = DriverSubscription::query()->with(['driver.user', 'plan'])->latest('id');

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (!empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        return view('admin.driver-subscriptions.index', [
            'pageTitle' => 'Driver Subscriptions',
            'subscriptions' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'plans' => SubscriptionPlan::orderBy('name')->get(),
            'statuses' => DriverSubscription::STATUSES,
        ]);
    }

    public function show(DriverSubscription $driverSubscription): View
    {
        $driverSubscription->load(['driver.user', 'plan']);

        return view('admin.driver-subscriptions.show', [
            'pageTitle' => 'Subscription Request',
            'subscription' => $driverSubscription,
        ]);
    }

    public function approve(DriverSubscription $driverSubscription): RedirectResponse
    {
        $this->subscriptions->approve($driverSubscription);

        return back()->with('success', 'Subscription approved.');
    }

    public function reject(Request $request, DriverSubscription $driverSubscription): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $this->subscriptions->reject($driverSubscription, $data['rejection_reason'] ?? null);

        return back()->with('success', 'Subscription rejected.');
    }
}
