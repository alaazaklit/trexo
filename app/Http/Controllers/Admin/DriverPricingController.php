<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverIntercityRouteOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverPricingController extends Controller
{
    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'pricing_zone_id' => 'nullable|exists:pricing_zones,id',
            'base_fare_override' => 'nullable|numeric',
            'price_per_km_override' => 'nullable|numeric',
            'detour_surcharge_override' => 'nullable|numeric',
            'reservation_multiplier_override' => 'nullable|numeric',
            'school_bus_child_discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $driver->update($data);

        return back()->with('success', 'Driver pricing updated.');
    }

    public function storeIntercityOverride(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'intercity_route_id' => 'required|exists:intercity_routes,id',
            'fixed_fare_taxi_override' => 'nullable|numeric',
            'fixed_fare_delivery_override' => 'nullable|numeric',
        ]);

        DriverIntercityRouteOverride::updateOrCreate(
            [
                'user_id' => $driver->user_id,
                'intercity_route_id' => $data['intercity_route_id'],
            ],
            [
                'fixed_fare_taxi_override' => $data['fixed_fare_taxi_override'] ?? null,
                'fixed_fare_delivery_override' => $data['fixed_fare_delivery_override'] ?? null,
            ]
        );

        return back()->with('success', 'Intercity route override saved.');
    }

    public function destroyIntercityOverride(DriverIntercityRouteOverride $override): RedirectResponse
    {
        $override->delete();

        return back()->with('success', 'Intercity route override cleared.');
    }
}
