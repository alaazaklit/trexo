<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Address;
use App\Models\Driver;
use App\Models\DriverSimulatorRide;
use App\Order;
use App\Services\DriverSimulator\DriverSimulatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverSimulatorController extends Controller
{
    public function __construct(private readonly DriverSimulatorService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.driver-simulator', [
            'pageTitle' => 'Driver Simulator',
            'filters' => $request->only(['search', 'status', 'vehicle_type', 'online', 'busy']),
            'vehicleTypes' => $this->service->getVehicleTypes(),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        return response()->json($this->service->dashboardPayload($request->only(['search', 'status', 'vehicle_type', 'online', 'busy'])));
    }

    public function drivers(Request $request): JsonResponse
    {
        $payload = $this->service->dashboardPayload(
            $request->only(['search', 'status', 'vehicle_type', 'online', 'busy'])
        );

        return response()->json([
            'result' => true,
            'data' => $payload['drivers'] ?? [],
            'stats' => $payload['stats'] ?? [],
            'vehicle_types' => $payload['vehicle_types'] ?? [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedDriverPayload($request);

        return response()->json([
            'result' => true,
            'message' => 'Driver created successfully.',
            'driver' => $this->service->serializeDriver($this->service->createDriver($data)),
            'stats' => $this->service->getStats(),
        ], 201);
    }

    public function update(Request $request, Driver $driver): JsonResponse
    {
        $data = $this->validatedDriverPayload($request, true);

        return response()->json([
            'result' => true,
            'message' => 'Driver updated successfully.',
            'driver' => $this->service->serializeDriver($this->service->updateDriver($driver, $data)),
            'stats' => $this->service->getStats(),
        ]);
    }

    public function destroy(Driver $driver): JsonResponse
    {
        if (!$driver->is_simulated) {
            return response()->json([
                'result' => false,
                'message' => 'Only simulated drivers can be removed from the simulator.',
            ], 403);
        }

        if (in_array($driver->status, ['busy', 'driving_to_pickup', 'waiting_passenger', 'trip_started'], true)) {
            return response()->json([
                'result' => false,
                'message' => 'This driver is currently active on a simulated ride.',
            ], 409);
        }

        $driver->delete();

        return response()->json([
            'result' => true,
            'message' => 'Driver deleted successfully.',
            'stats' => $this->service->getStats(),
        ]);
    }

    public function spawn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => 'required|integer|min:1|max:500',
            'center_latitude' => 'required|numeric',
            'center_longitude' => 'required|numeric',
            'radius_km' => 'required|numeric|min:0.1|max:100',
            'vehicle_type' => 'nullable|string|max:50',
            'speed_kmh' => 'nullable|numeric|min:5|max:180',
            'ride_response_mode' => 'nullable|in:manual,auto_accept,auto_reject',
        ]);

        $drivers = $this->service->spawnDrivers(
            (int) $data['count'],
            (float) $data['center_latitude'],
            (float) $data['center_longitude'],
            (float) $data['radius_km'],
            array_filter($data, static fn ($value, $key) => !in_array($key, ['count', 'center_latitude', 'center_longitude', 'radius_km'], true), ARRAY_FILTER_USE_BOTH)
        );

        return response()->json([
            'result' => true,
            'message' => $drivers->count() . ' drivers spawned successfully.',
            'drivers' => $drivers->map(fn (Driver $driver) => $this->service->serializeDriver($driver->fresh('user')))->values(),
            'stats' => $this->service->getStats(),
        ], 201);
    }

    public function toggle(Request $request, Driver $driver): JsonResponse
    {
        $data = $request->validate([
            'is_online' => 'required|boolean',
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Driver availability updated.',
            'driver' => $this->service->serializeDriver($this->service->toggleOnline($driver, (bool) $data['is_online'])),
            'stats' => $this->service->getStats(),
        ]);
    }

    public function move(Request $request, Driver $driver): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'heading' => 'nullable|numeric|min:0|max:360',
            'speed_kmh' => 'nullable|numeric|min:0|max:180',
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Driver position updated.',
            'driver' => $this->service->serializeDriver(
                $this->service->updatePosition(
                    $driver,
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    isset($data['heading']) ? (float) $data['heading'] : null,
                    isset($data['speed_kmh']) ? (float) $data['speed_kmh'] : null
                )
            ),
            'stats' => $this->service->getStats(),
        ]);
    }

    public function tick(): JsonResponse
    {
        return response()->json($this->service->tick());
    }

    public function rideRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pickup_latitude' => 'nullable|numeric',
            'pickup_longitude' => 'nullable|numeric',
            'dropoff_latitude' => 'nullable|numeric',
            'dropoff_longitude' => 'nullable|numeric',
            'passenger_name' => 'nullable|string|max:120',
            'passenger_phone' => 'nullable|string|max:30',
            'pickup_label' => 'nullable|string|max:255',
            'dropoff_label' => 'nullable|string|max:255',
            'auto_response_mode' => 'nullable|in:manual,auto_accept,auto_reject',
            'limit' => 'nullable|integer|min:1|max:10',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        if (!empty($data['order_id'])) {
            $order = Order::findOrFail((int) $data['order_id']);
            $startAddress = Address::where('order_id', $order->id)
                ->where('direction', 'start_address')
                ->first();
            $endAddress = Address::where('order_id', $order->id)
                ->where('direction', 'destination_address')
                ->first();

            if (!$startAddress || !$endAddress || $startAddress->latitude === null || $startAddress->longitude === null || $endAddress->latitude === null || $endAddress->longitude === null) {
                return response()->json([
                    'result' => false,
                    'message' => 'The selected order is missing pickup or dropoff coordinates.',
                ], 422);
            }

            $data['pickup_latitude'] = $startAddress->latitude;
            $data['pickup_longitude'] = $startAddress->longitude;
            $data['dropoff_latitude'] = $endAddress->latitude;
            $data['dropoff_longitude'] = $endAddress->longitude;
            $data['pickup_label'] = $data['pickup_label'] ?? $startAddress->address_line1;
            $data['dropoff_label'] = $data['dropoff_label'] ?? $endAddress->address_line1;

            $ride = $this->service->createRideFromOrder($order, $data);
        } else {
            foreach (['pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude'] as $requiredField) {
                if (!array_key_exists($requiredField, $data) || $data[$requiredField] === null) {
                    return response()->json([
                        'result' => false,
                        'message' => 'Pickup and dropoff coordinates are required when no order is selected.',
                    ], 422);
                }
            }

            $ride = $this->service->createRideRequest($data);
        }

        return response()->json([
            'result' => true,
            'message' => 'Ride request created.',
            'ride' => $this->service->serializeRide($ride),
            'stats' => $this->service->getStats(),
        ], 201);
    }

    public function rideDecision(Request $request, DriverSimulatorRide $ride): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:accept,reject',
            'driver_id' => 'nullable|exists:drivers,id',
        ]);

        $driver = null;

        if (!empty($data['driver_id'])) {
            $driver = Driver::findOrFail((int) $data['driver_id']);
        } elseif ($ride->driver_id) {
            $driver = Driver::find($ride->driver_id);
        }

        if ($data['decision'] === 'accept' && !$driver) {
            $driver = $this->service->findNearestAvailableDrivers((float) $ride->pickup_latitude, (float) $ride->pickup_longitude, 1)->first();
        }

        if ($data['decision'] === 'accept' && !$driver) {
            return response()->json([
                'result' => false,
                'message' => 'No available driver was found for this ride.',
            ], 409);
        }

        $updatedRide = $data['decision'] === 'accept'
            ? $this->service->acceptRide($ride, $driver)
            : $this->service->rejectRide($ride, $driver);

        return response()->json([
            'result' => true,
            'message' => $data['decision'] === 'accept' ? 'Ride accepted.' : 'Ride rejected.',
            'ride' => $this->service->serializeRide($updatedRide),
            'stats' => $this->service->getStats(),
        ]);
    }

    private function validatedDriverPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name' => ($isUpdate ? 'sometimes' : 'required') . '|string|max:120',
            'phone' => ($isUpdate ? 'sometimes' : 'nullable') . '|string|max:30',
            'email' => 'nullable|email|max:191',
            'password' => 'nullable|string|min:6',
            'license_number' => ($isUpdate ? 'sometimes' : 'nullable') . '|string|max:100',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'vehicle_type' => 'nullable|string|max:50',
            'rating' => 'nullable|numeric|min:0|max:5',
            'is_online' => 'nullable|boolean',
            'status' => 'nullable|string|max:40',
            'ride_response_mode' => 'nullable|in:manual,auto_accept,auto_reject',
            'speed_kmh' => 'nullable|numeric|min:0|max:180',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'heading' => 'nullable|numeric|min:0|max:360',
            'simulation_notes' => 'nullable|string',
        ];

        return $request->validate($rules);
    }
}
