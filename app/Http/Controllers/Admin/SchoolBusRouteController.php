<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\School;
use App\Models\SchoolBusRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolBusRouteController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['driver_id', 'school_id', 'is_active']);

        $query = SchoolBusRoute::with(['driver.user', 'school']);

        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }
        if (!empty($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return view('admin.school-bus-routes.index', [
            'pageTitle' => 'School Bus Routes',
            'routes' => $query->orderByDesc('id')->get(),
            'filters' => $filters,
            'drivers' => Driver::with('user')->get(),
            'schools' => School::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'school_id' => 'required|exists:schools,id',
            'pickup_area' => 'required|string|max:150',
            'monthly_price' => 'required|numeric|min:0',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        SchoolBusRoute::create($data);

        return back()->with('success', 'School bus route created.');
    }

    public function update(Request $request, SchoolBusRoute $schoolBusRoute): RedirectResponse
    {
        $data = $request->validate([
            'pickup_area' => 'required|string|max:150',
            'monthly_price' => 'required|numeric|min:0',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $schoolBusRoute->update($data);

        return back()->with('success', 'School bus route updated.');
    }

    public function destroy(SchoolBusRoute $schoolBusRoute): RedirectResponse
    {
        $schoolBusRoute->delete();

        return back()->with('success', 'School bus route deleted.');
    }
}
