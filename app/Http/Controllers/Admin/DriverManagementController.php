<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\VehicleCategory;
use App\Services\Driver\DriverManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverManagementController extends Controller
{
    public function __construct(private readonly DriverManagementService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'approval_status', 'school_bus_status', 'expiring_soon']);

        return view('admin.drivers.index', [
            'pageTitle' => 'Drivers',
            'drivers' => $this->service->filtered($filters),
            'filters' => $filters,
            'statuses' => DriverManagementService::APPROVAL_STATUSES,
            'schoolBusStatuses' => Driver::SCHOOL_BUS_STATUSES,
        ]);
    }

    public function show(Driver $driver): View
    {
        $driver->load(['user', 'documents', 'vehicleCategory']);

        return view('admin.drivers.show', [
            'pageTitle' => 'Driver: '.($driver->user->name ?: $driver->user->phone),
            'driver' => $driver,
            'trips' => $this->service->tripHistory($driver),
            'statuses' => DriverManagementService::APPROVAL_STATUSES,
            'schoolBusStatuses' => Driver::SCHOOL_BUS_STATUSES,
            'documentTypes' => DriverManagementService::DOCUMENT_TYPES,
            'vehicleCategories' => VehicleCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:30',
            'license_number' => 'nullable|string|max:100|unique:drivers,license_number',
        ]);

        $user = $this->service->findOrCreateUserByPhone($data['phone']);

        try {
            $driver = $this->service->createDriver($user, [
                'license_number' => $data['license_number'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return redirect()->route('admin.drivers.show', $driver)->with('success', 'Driver created.');
    }

    public function updateApprovalStatus(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'approval_status' => 'required|in:'.implode(',', DriverManagementService::APPROVAL_STATUSES),
        ]);

        $this->service->updateApprovalStatus($driver, $data['approval_status']);

        return back()->with('success', 'Driver approval status updated.');
    }

    public function updateSchoolBusStatus(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'school_bus_status' => 'nullable|in:'.implode(',', Driver::SCHOOL_BUS_STATUSES),
        ]);

        $this->service->updateSchoolBusStatus($driver, $data['school_bus_status'] ?? null);

        return back()->with('success', 'School bus status updated.');
    }

    public function uploadDocument(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => 'required|in:'.implode(',', DriverManagementService::DOCUMENT_TYPES),
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'expires_at' => 'nullable|date',
        ]);

        $this->service->uploadDocument($driver, $data['document_type'], $request->file('file'), $data['expires_at'] ?? null);

        return back()->with('success', 'Document uploaded.');
    }

    public function reviewDocument(Request $request, DriverDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $this->service->reviewDocument($document, $data['status'], $data['rejection_reason'] ?? null);

        return back()->with('success', 'Document review saved.');
    }

    public function deleteDocument(DriverDocument $document): RedirectResponse
    {
        $this->service->deleteDocument($document);

        return back()->with('success', 'Document deleted.');
    }

    public function updateVehicle(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'license_number' => 'nullable|string|max:100|unique:drivers,license_number,'.$driver->id,
            'vehicle_category_id' => 'nullable|exists:vehicle_categories,id',
            'vehicle_make' => 'nullable|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_color' => 'nullable|string|max:50',
            'vehicle_plate' => 'nullable|string|max:20',
            'transmission' => 'nullable|string|max:20',
        ]);

        $driver->update($data);

        return back()->with('success', 'Vehicle details updated.');
    }

    public function destroy(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'revert_type' => 'nullable|in:seller,driver',
        ]);

        $this->service->deleteDriver($driver, $data['revert_type'] ?? null);

        return redirect()->route('admin.drivers.index')->with('success', 'Driver record removed.');
    }
}
