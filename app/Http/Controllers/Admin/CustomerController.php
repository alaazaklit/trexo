<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'account_status']);

        return view('admin.customers.index', [
            'pageTitle' => 'Customers',
            'customers' => $this->service->filtered($filters),
            'filters' => $filters,
            'statuses' => CustomerService::STATUSES,
        ]);
    }

    public function show(User $customer): View
    {
        abort_if($customer->type !== 'seller', 404);

        return view('admin.customers.show', [
            'pageTitle' => 'Customer: '.($customer->name ?: $customer->phone),
            'customer' => $customer,
            'profile' => $this->service->profile($customer),
            'statuses' => CustomerService::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, User $customer): RedirectResponse
    {
        $data = $request->validate([
            'account_status' => 'required|in:'.implode(',', CustomerService::STATUSES),
            'reason' => 'nullable|string|max:500',
        ]);

        $this->service->updateStatus($customer, $data['account_status'], $data['reason'] ?? null);

        return back()->with('success', 'Customer status updated.');
    }

    public function toggleVerified(Request $request, User $customer): RedirectResponse
    {
        $this->service->setVerified($customer, !$customer->is_verified);

        return back()->with('success', 'Verification status updated.');
    }
}
