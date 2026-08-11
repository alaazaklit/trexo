<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolBusPremiumSubscription;
use App\Services\SchoolBus\SchoolBusPremiumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Mirrors Admin\DriverSubscriptionController — same manual
// receipt-upload + approve/reject review workflow, just scoped to a
// school_bus_subscription (one student/route) instead of a driver plan.
class SchoolBusPremiumSubscriptionController extends Controller
{
    public function __construct(private readonly SchoolBusPremiumService $premium)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['status']);

        $query = SchoolBusPremiumSubscription::query()
            ->with(['subscription.route.school', 'subscription.driver.user', 'parentUser'])
            ->latest('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('admin.school-bus-premium-subscriptions.index', [
            'pageTitle' => 'School Bus Premium Subscriptions',
            'premiumSubscriptions' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => SchoolBusPremiumSubscription::STATUSES,
        ]);
    }

    public function show(SchoolBusPremiumSubscription $schoolBusPremiumSubscription): View
    {
        $schoolBusPremiumSubscription->load(['subscription.route.school', 'subscription.driver.user', 'parentUser', 'reviewedBy']);

        return view('admin.school-bus-premium-subscriptions.show', [
            'pageTitle' => 'School Bus Premium Request',
            'premium' => $schoolBusPremiumSubscription,
        ]);
    }

    public function approve(SchoolBusPremiumSubscription $schoolBusPremiumSubscription): RedirectResponse
    {
        $this->premium->approve($schoolBusPremiumSubscription);

        return back()->with('success', 'Premium subscription approved.');
    }

    public function reject(Request $request, SchoolBusPremiumSubscription $schoolBusPremiumSubscription): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $this->premium->reject($schoolBusPremiumSubscription, $data['rejection_reason'] ?? null);

        return back()->with('success', 'Premium subscription rejected.');
    }
}
