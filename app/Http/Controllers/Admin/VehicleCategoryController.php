<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleCategory;
use App\Services\VehicleCategory\VehicleCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleCategoryController extends Controller
{
    public function __construct(private readonly VehicleCategoryService $service)
    {
    }

    public function index(): View
    {
        return view('admin.vehicle-categories.index', [
            'pageTitle' => 'Vehicle Categories',
            'categories' => $this->service->all(),
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:vehicle_categories,slug',
            'price_multiplier' => 'required|numeric|min:0.1|max:99.99',
            'capacity' => 'required|integer|min:1|max:50',
            'icon' => 'nullable|string|max:255',
            'supports_taxi' => 'nullable|boolean',
            'supports_delivery' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['supports_taxi'] = $request->boolean('supports_taxi');
        $data['supports_delivery'] = $request->boolean('supports_delivery');
        $data['is_active'] = $request->boolean('is_active');

        $this->service->create($data);

        return back()->with('success', 'Vehicle category created.');
    }

    public function update(Request $request, VehicleCategory $vehicleCategory): RedirectResponse
    {
        $rules = $this->rules();
        $rules['slug'] = 'nullable|string|max:100|unique:vehicle_categories,slug,'.$vehicleCategory->id;

        $data = $request->validate($rules);
        $data['supports_taxi'] = $request->boolean('supports_taxi');
        $data['supports_delivery'] = $request->boolean('supports_delivery');
        $data['is_active'] = $request->boolean('is_active');

        $this->service->update($vehicleCategory, $data);

        return back()->with('success', 'Vehicle category updated.');
    }

    public function destroy(VehicleCategory $vehicleCategory): RedirectResponse
    {
        $this->service->delete($vehicleCategory);

        return back()->with('success', 'Vehicle category deleted.');
    }
}
