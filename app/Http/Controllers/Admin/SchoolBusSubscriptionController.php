<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolBusSubscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolBusSubscriptionController extends Controller
{
    // View-only in the admin panel — accept/reject stays a driver action,
    // per the spec ("The request is sent to the driver. The driver can
    // Accept / Reject.").
    public function index(Request $request): View
    {
        $filters = $request->only(['status']);

        $query = SchoolBusSubscription::query()->with(['driver.user', 'route.school'])->latest('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('admin.school-bus-subscriptions.index', [
            'pageTitle' => 'School Bus Subscriptions',
            'subscriptions' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => SchoolBusSubscription::STATUSES,
        ]);
    }

    public function show(SchoolBusSubscription $schoolBusSubscription): View
    {
        $schoolBusSubscription->load(['driver.user', 'route.school', 'parentUser']);

        return view('admin.school-bus-subscriptions.show', [
            'pageTitle' => 'School Bus Subscription',
            'subscription' => $schoolBusSubscription,
        ]);
    }
}
