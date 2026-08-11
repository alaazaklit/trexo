<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.subscription-plans.index', [
            'pageTitle' => 'Subscription Plans',
            'plans' => SubscriptionPlan::orderBy('monthly_price')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|alpha_dash|unique:subscription_plans,slug',
            'monthly_price' => 'required|numeric|min:0',
            'commission_percentage' => 'required|numeric|min:0|max:100',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        SubscriptionPlan::create($data);

        return back()->with('success', 'Plan created.');
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'monthly_price' => 'required|numeric|min:0',
            'commission_percentage' => 'required|numeric|min:0|max:100',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $subscriptionPlan->update($data);

        return back()->with('success', 'Plan updated.');
    }
}
