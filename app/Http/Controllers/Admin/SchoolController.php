<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(): View
    {
        return view('admin.schools.index', [
            'pageTitle' => 'Schools',
            'schools' => School::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'area' => 'nullable|string|max:150',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        School::create($data);

        return back()->with('success', 'School created.');
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'area' => 'nullable|string|max:150',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $school->update($data);

        return back()->with('success', 'School updated.');
    }

    public function destroy(School $school): RedirectResponse
    {
        $school->delete();

        return back()->with('success', 'School deleted.');
    }
}
