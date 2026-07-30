<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverApplication;
use App\Services\DriverApplication\DriverApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverApplicationController extends Controller
{
    public function __construct(private readonly DriverApplicationService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'service_type']);

        return view('admin.driver-applications.index', [
            'pageTitle' => 'Driver Applications',
            'applications' => $this->service->filtered($filters),
            'filters' => $filters,
            'statuses' => DriverApplication::STATUSES,
        ]);
    }

    public function show(DriverApplication $driverApplication): View
    {
        $driverApplication->load('notes.author', 'driver');

        return view('admin.driver-applications.show', [
            'pageTitle' => 'Application: '.$driverApplication->full_name,
            'application' => $driverApplication,
            'statuses' => DriverApplication::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, DriverApplication $driverApplication): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', DriverApplication::STATUSES),
        ]);

        $this->service->updateStatus($driverApplication, $data['status']);

        return back()->with('success', 'Application status updated.');
    }

    public function addNote(Request $request, DriverApplication $driverApplication): RedirectResponse
    {
        $data = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $this->service->addNote($driverApplication, $data['note']);

        return back()->with('success', 'Note added.');
    }

    public function convertToDriver(DriverApplication $driverApplication): RedirectResponse
    {
        try {
            $driver = $this->service->convertToDriver($driverApplication);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['convert' => $e->getMessage()]);
        }

        return redirect()->route('admin.drivers.show', $driver)->with('success', 'Driver account created from this application.');
    }
}
