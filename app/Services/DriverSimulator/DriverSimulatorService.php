<?php

namespace App\Services\DriverSimulator;

use App\Models\Driver;
use App\Models\DriverSimulatorRide;
use App\Models\User;
use App\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverSimulatorService
{
    private const VEHICLE_TYPES = [
        'Sedan',
        'SUV',
        'Hatchback',
        'Minivan',
        'Van',
        'Bike',
    ];

    private const DRIVER_STATUSES = [
        'offline',
        'online',
        'available',
        'busy',
        'driving_to_pickup',
        'waiting_passenger',
        'trip_started',
        'trip_finished',
    ];

    public function listDrivers(array $filters = []): Collection
    {
        $query = Driver::query()->with('user');

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function (Builder $builder) use ($search) {
                $builder->where('drivers.id', $search)
                    ->orWhere('license_number', 'like', '%' . $search . '%')
                    ->orWhere('vehicle_type', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['vehicle_type'])) {
            $query->where('vehicle_type', $filters['vehicle_type']);
        }

        if (array_key_exists('online', $filters) && $filters['online'] !== null && $filters['online'] !== '') {
            $query->where('is_online', filter_var($filters['online'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
        }

        if (!empty($filters['busy'])) {
            $query->whereIn('status', ['busy', 'driving_to_pickup', 'waiting_passenger', 'trip_started']);
        }

        return $query
            ->orderByRaw("CASE WHEN status = 'offline' THEN 1 ELSE 0 END")
            ->orderByRaw("CASE WHEN status = 'busy' THEN 1 ELSE 0 END")
            ->orderBy('id')
            ->get();
    }

    public function getVehicleTypes(): array
    {
        return self::VEHICLE_TYPES;
    }

    public function getStats(): array
    {
        $drivers = Driver::query();
        $rides = DriverSimulatorRide::query();

        return [
            'drivers_online' => (clone $drivers)->where('is_online', true)->count(),
            'drivers_offline' => (clone $drivers)->where('is_online', false)->count(),
            'busy_drivers' => (clone $drivers)->whereIn('status', ['busy', 'driving_to_pickup', 'waiting_passenger', 'trip_started'])->count(),
            'available_drivers' => (clone $drivers)->where('status', 'available')->count(),
            'trips_running' => (clone $rides)->whereIn('status', ['accepted', 'driving_to_pickup', 'waiting_passenger', 'trip_started'])->count(),
            'average_response_time' => (float) round((clone $rides)->whereNotNull('response_time_seconds')->avg('response_time_seconds') ?? 0, 2),
        ];
    }

    public function createDriver(array $payload): Driver
    {
        return DB::transaction(function () use ($payload) {
            $user = User::create([
                'name' => $payload['name'] ?? $this->makeDriverName(),
                'phone' => $payload['phone'] ?? $this->makeDriverPhone(),
                'email' => $payload['email'] ?? ('sim-' . Str::uuid() . '@example.test'),
                'password' => bcrypt($payload['password'] ?? 'password'),
                'type' => 'driver',
                'is_available' => (bool) ($payload['is_online'] ?? true),
                'is_simulated' => true,
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'heading' => $payload['heading'] ?? null,
                'speed_kmh' => $payload['speed_kmh'] ?? null,
                'last_seen_at' => now(),
            ]);

            $driver = Driver::create([
                'user_id' => $user->id,
                'license_number' => $payload['license_number'] ?? ('SIM-' . strtoupper(Str::random(10))),
                'vehicle_id' => $payload['vehicle_id'] ?? null,
                'rating' => $payload['rating'] ?? 5,
                'is_online' => (bool) ($payload['is_online'] ?? true),
                'status' => $payload['status'] ?? ((bool) ($payload['is_online'] ?? true) ? 'available' : 'offline'),
                'vehicle_type' => $payload['vehicle_type'] ?? 'Sedan',
                'speed_kmh' => $payload['speed_kmh'] ?? $this->randomSpeed(),
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'heading' => $payload['heading'] ?? rand(0, 359),
                'last_seen_at' => now(),
                'ride_response_mode' => $payload['ride_response_mode'] ?? 'manual',
                'workflow_state' => $payload['status'] ?? ((bool) ($payload['is_online'] ?? true) ? 'available' : 'offline'),
                'route_cursor' => 0,
                'spawn_center_latitude' => $payload['spawn_center_latitude'] ?? null,
                'spawn_center_longitude' => $payload['spawn_center_longitude'] ?? null,
                'spawn_radius_km' => $payload['spawn_radius_km'] ?? null,
                'is_simulated' => true,
                'simulation_notes' => $payload['simulation_notes'] ?? null,
            ]);

            $this->syncUserFromDriver($driver);

            return $driver->fresh('user');
        });
    }

    public function updateDriver(Driver $driver, array $payload): Driver
    {
        return DB::transaction(function () use ($driver, $payload) {
            $driver->loadMissing('user');
            $licenseNumber = array_key_exists('license_number', $payload) && trim((string) $payload['license_number']) !== ''
                ? $payload['license_number']
                : $driver->license_number;
            $vehicleType = array_key_exists('vehicle_type', $payload) && trim((string) $payload['vehicle_type']) !== ''
                ? $payload['vehicle_type']
                : $driver->vehicle_type;
            $status = array_key_exists('status', $payload) && trim((string) $payload['status']) !== ''
                ? $payload['status']
                : $driver->status;
            $workflowState = array_key_exists('workflow_state', $payload) && trim((string) $payload['workflow_state']) !== ''
                ? $payload['workflow_state']
                : $driver->workflow_state;
            $rideResponseMode = array_key_exists('ride_response_mode', $payload) && trim((string) $payload['ride_response_mode']) !== ''
                ? $payload['ride_response_mode']
                : $driver->ride_response_mode;
            $simulationNotes = array_key_exists('simulation_notes', $payload) && trim((string) $payload['simulation_notes']) !== ''
                ? $payload['simulation_notes']
                : $driver->simulation_notes;

            $driver->fill([
                'license_number' => $licenseNumber,
                'vehicle_id' => $payload['vehicle_id'] ?? $driver->vehicle_id,
                'rating' => $payload['rating'] ?? $driver->rating,
                'is_online' => array_key_exists('is_online', $payload) ? (bool) $payload['is_online'] : $driver->is_online,
                'status' => $status,
                'vehicle_type' => $vehicleType,
                'speed_kmh' => $payload['speed_kmh'] ?? $driver->speed_kmh,
                'latitude' => $payload['latitude'] ?? $driver->latitude,
                'longitude' => $payload['longitude'] ?? $driver->longitude,
                'heading' => $payload['heading'] ?? $driver->heading,
                'ride_response_mode' => $rideResponseMode,
                'workflow_state' => $workflowState,
                'simulation_notes' => $simulationNotes,
            ]);

            if (!$driver->is_online && empty($payload['status'])) {
                $driver->status = 'offline';
                $driver->workflow_state = 'offline';
            } elseif ($driver->is_online && $driver->status === 'offline') {
                $driver->status = 'available';
                $driver->workflow_state = 'available';
            }

            $driver->last_seen_at = now();
            $driver->save();

            if ($driver->user) {
                $userName = array_key_exists('name', $payload) && trim((string) $payload['name']) !== ''
                    ? $payload['name']
                    : $driver->user->name;
                $userPhone = array_key_exists('phone', $payload) && trim((string) $payload['phone']) !== ''
                    ? $payload['phone']
                    : $driver->user->phone;
                $userEmail = array_key_exists('email', $payload) && trim((string) $payload['email']) !== ''
                    ? $payload['email']
                    : $driver->user->email;

                $driver->user->fill([
                    'name' => $userName,
                    'phone' => $userPhone,
                    'email' => $userEmail,
                    'is_available' => $driver->status === 'available',
                    'is_simulated' => true,
                    'latitude' => $driver->latitude,
                    'longitude' => $driver->longitude,
                    'heading' => $driver->heading,
                    'speed_kmh' => $driver->speed_kmh,
                    'last_seen_at' => $driver->last_seen_at,
                ]);

                if (!empty($payload['password'])) {
                    $driver->user->password = bcrypt($payload['password']);
                }

                $driver->user->save();
            }

            $this->syncUserFromDriver($driver);

            return $driver->fresh('user');
        });
    }

    public function toggleOnline(Driver $driver, bool $online): Driver
    {
        return $this->updateDriver($driver, [
            'is_online' => $online,
            'status' => $online ? 'available' : 'offline',
            'workflow_state' => $online ? 'available' : 'offline',
        ]);
    }

    public function updatePosition(Driver $driver, float $latitude, float $longitude, ?float $heading = null, ?float $speedKmh = null): Driver
    {
        return $this->updateDriver($driver, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'heading' => $heading ?? $driver->heading,
            'speed_kmh' => $speedKmh ?? $driver->speed_kmh,
        ]);
    }

    public function spawnDrivers(int $count, float $centerLat, float $centerLng, float $radiusKm, array $overrides = []): Collection
    {
        $drivers = collect();

        for ($i = 0; $i < $count; $i++) {
            $point = $this->randomPointInRadius($centerLat, $centerLng, $radiusKm);

            $drivers->push($this->createDriver(array_merge($overrides, [
                'name' => $overrides['name'] ?? $this->makeDriverName($i + 1),
                'phone' => $overrides['phone'] ?? $this->makeDriverPhone(),
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'heading' => rand(0, 359),
                'spawn_center_latitude' => $centerLat,
                'spawn_center_longitude' => $centerLng,
                'spawn_radius_km' => $radiusKm,
                'is_online' => true,
                'status' => 'available',
                'workflow_state' => 'available',
                'vehicle_type' => $overrides['vehicle_type'] ?? $this->randomVehicleType(),
                'speed_kmh' => $overrides['speed_kmh'] ?? $this->randomSpeed(),
                'ride_response_mode' => $overrides['ride_response_mode'] ?? 'manual',
            ])));
        }

        return $drivers;
    }

    public function tick(): array
    {
        $drivers = Driver::query()->with('user')->where('is_simulated', true)->get();

        foreach ($drivers as $driver) {
            if (!$driver->is_online) {
                continue;
            }

            if (in_array($driver->status, ['driving_to_pickup', 'waiting_passenger', 'trip_started', 'busy'], true)) {
                $this->advanceRideDriver($driver);
                continue;
            }

            $this->advanceIdleDriver($driver);
        }

        return $this->dashboardPayload();
    }

    public function createRideRequest(array $payload): DriverSimulatorRide
    {
        return DB::transaction(function () use ($payload) {
            $ride = DriverSimulatorRide::create([
                'passenger_name' => $payload['passenger_name'] ?? 'Test Passenger',
                'passenger_phone' => $payload['passenger_phone'] ?? null,
                'pickup_latitude' => $payload['pickup_latitude'],
                'pickup_longitude' => $payload['pickup_longitude'],
                'dropoff_latitude' => $payload['dropoff_latitude'],
                'dropoff_longitude' => $payload['dropoff_longitude'],
                'pickup_label' => $payload['pickup_label'] ?? null,
                'dropoff_label' => $payload['dropoff_label'] ?? null,
                'status' => 'pending',
                'auto_response_mode' => $payload['auto_response_mode'] ?? 'manual',
                'order_id' => $payload['order_id'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $matches = $this->findNearestAvailableDrivers(
                (float) $ride->pickup_latitude,
                (float) $ride->pickup_longitude,
                (int) ($payload['limit'] ?? 5)
            );

            $ride->matched_driver_ids = $matches->pluck('id')->values()->all();
            $ride->save();

            foreach ($matches as $driver) {
                if ($driver->ride_response_mode === 'auto_reject') {
                    continue;
                }

                if ($driver->ride_response_mode === 'auto_accept') {
                    return $this->acceptRide($ride->refresh(), $driver, 'auto');
                }
            }

            $ride->save();

            return $ride->fresh();
        });
    }

    public function acceptRide(DriverSimulatorRide $ride, Driver $driver, string $responseSource = 'manual'): DriverSimulatorRide
    {
        return DB::transaction(function () use ($ride, $driver, $responseSource) {
            $ride->refresh();
            $driver->refresh();

            if ($ride->status !== 'pending' && $ride->status !== 'matched') {
                return $ride;
            }

            $startedAt = Carbon::now();
            $ride->driver_id = $driver->id;
            $ride->status = 'accepted';
            $ride->accepted_at = $startedAt;
            $ride->response_time_seconds = $ride->created_at ? $ride->created_at->diffInSeconds($startedAt) : 0;
            $ride->metadata = array_merge($ride->metadata ?? [], [
                'response_source' => $responseSource,
            ]);
            $ride->save();

            $this->updateDriver($driver, [
                'is_online' => true,
                'status' => 'driving_to_pickup',
                'workflow_state' => 'driving_to_pickup',
                'assigned_trip_id' => $ride->id,
                'pickup_latitude' => $ride->pickup_latitude,
                'pickup_longitude' => $ride->pickup_longitude,
                'dropoff_latitude' => $ride->dropoff_latitude,
                'dropoff_longitude' => $ride->dropoff_longitude,
                'simulation_notes' => 'Assigned to simulated ride #' . $ride->id,
            ]);

            $ride->refresh();
            $ride->route_points = $this->buildSimpleRoute(
                (float) $driver->latitude,
                (float) $driver->longitude,
                (float) $ride->pickup_latitude,
                (float) $ride->pickup_longitude,
                (float) $ride->dropoff_latitude,
                (float) $ride->dropoff_longitude
            );
            $ride->save();

            return $ride->fresh('driver');
        });
    }

    public function rejectRide(DriverSimulatorRide $ride, ?Driver $driver = null): DriverSimulatorRide
    {
        return DB::transaction(function () use ($ride, $driver) {
            $ride->refresh();
            $ride->status = 'rejected';
            $ride->rejected_at = now();

            if ($driver) {
                $ride->driver_id = $driver->id;
            }

            $ride->save();

            return $ride->fresh();
        });
    }

    public function serializeDriver(Driver $driver): array
    {
        $driver->loadMissing('user');

        return [
            'id' => $driver->id,
            'user_id' => $driver->user_id,
            'name' => $driver->user->name ?? 'Driver ' . $driver->id,
            'phone' => $driver->user->phone ?? null,
            'email' => $driver->user->email ?? null,
            'license_number' => $driver->license_number,
            'vehicle_id' => $driver->vehicle_id,
            'vehicle_type' => $driver->vehicle_type,
            'rating' => (float) $driver->rating,
            'is_online' => (bool) $driver->is_online,
            'status' => $driver->status,
            'workflow_state' => $driver->workflow_state,
            'ride_response_mode' => $driver->ride_response_mode,
            'speed_kmh' => (float) $driver->speed_kmh,
            'latitude' => $driver->latitude !== null ? (float) $driver->latitude : null,
            'longitude' => $driver->longitude !== null ? (float) $driver->longitude : null,
            'heading' => $driver->heading !== null ? (float) $driver->heading : null,
            'last_seen_at' => optional($driver->last_seen_at)->toIso8601String(),
            'assigned_trip_id' => $driver->assigned_trip_id,
            'pickup_latitude' => $driver->pickup_latitude !== null ? (float) $driver->pickup_latitude : null,
            'pickup_longitude' => $driver->pickup_longitude !== null ? (float) $driver->pickup_longitude : null,
            'dropoff_latitude' => $driver->dropoff_latitude !== null ? (float) $driver->dropoff_latitude : null,
            'dropoff_longitude' => $driver->dropoff_longitude !== null ? (float) $driver->dropoff_longitude : null,
            'spawn_center_latitude' => $driver->spawn_center_latitude !== null ? (float) $driver->spawn_center_latitude : null,
            'spawn_center_longitude' => $driver->spawn_center_longitude !== null ? (float) $driver->spawn_center_longitude : null,
            'spawn_radius_km' => $driver->spawn_radius_km !== null ? (float) $driver->spawn_radius_km : null,
            'simulation_notes' => $driver->simulation_notes,
        ];
    }

    public function serializeRide(DriverSimulatorRide $ride): array
    {
        $ride->loadMissing('driver.user');

        return [
            'id' => $ride->id,
            'passenger_name' => $ride->passenger_name,
            'pickup_latitude' => (float) $ride->pickup_latitude,
            'pickup_longitude' => (float) $ride->pickup_longitude,
            'dropoff_latitude' => (float) $ride->dropoff_latitude,
            'dropoff_longitude' => (float) $ride->dropoff_longitude,
            'pickup_label' => $ride->pickup_label,
            'dropoff_label' => $ride->dropoff_label,
            'status' => $ride->status,
            'auto_response_mode' => $ride->auto_response_mode,
            'driver' => $ride->driver ? $this->serializeDriver($ride->driver) : null,
            'matched_driver_ids' => $ride->matched_driver_ids ?? [],
            'route_points' => $ride->route_points ?? [],
            'route_cursor' => $ride->route_cursor,
            'response_time_seconds' => $ride->response_time_seconds,
            'accepted_at' => optional($ride->accepted_at)->toIso8601String(),
            'rejected_at' => optional($ride->rejected_at)->toIso8601String(),
            'started_at' => optional($ride->started_at)->toIso8601String(),
            'finished_at' => optional($ride->finished_at)->toIso8601String(),
            'created_at' => optional($ride->created_at)->toIso8601String(),
        ];
    }

    public function dashboardPayload(array $filters = []): array
    {
        return [
            'drivers' => $this->listDrivers($filters)->map(function (Driver $driver) {
                return $this->serializeDriver($driver);
            })->values(),
            'rides' => DriverSimulatorRide::query()
                ->with('driver.user')
                ->latest()
                ->limit(100)
                ->get()
                ->map(function (DriverSimulatorRide $ride) {
                    return $this->serializeRide($ride);
                })
                ->values(),
            'stats' => $this->getStats(),
            'vehicle_types' => $this->getVehicleTypes(),
        ];
    }

    public function findNearestAvailableDrivers(float $latitude, float $longitude, int $limit = 5): Collection
    {
        $drivers = Driver::query()
            ->with('user')
            ->where('is_online', true)
            ->where('status', 'available')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (Driver $driver) use ($latitude, $longitude) {
                $driver->distance_to_pickup_km = $this->distanceKm(
                    $latitude,
                    $longitude,
                    (float) $driver->latitude,
                    (float) $driver->longitude
                );

                return $driver;
            })
            ->sortBy('distance_to_pickup_km')
            ->take($limit)
            ->values();

        return $drivers;
    }

    public function createRideFromOrder(Order $order, array $payload = []): DriverSimulatorRide
    {
        return $this->createRideRequest(array_merge($payload, [
            'order_id' => $order->id,
            'passenger_name' => $payload['passenger_name'] ?? ('Order #' . $order->id),
        ]));
    }

    private function syncUserFromDriver(Driver $driver): void
    {
        if (!$driver->relationLoaded('user')) {
            $driver->load('user');
        }

        if (!$driver->user) {
            return;
        }

        $driver->user->fill([
            'name' => $driver->user->name,
            'type' => 'driver',
            'is_available' => $driver->status === 'available',
            'is_simulated' => true,
            'latitude' => $driver->latitude,
            'longitude' => $driver->longitude,
            'heading' => $driver->heading,
            'speed_kmh' => $driver->speed_kmh,
            'last_seen_at' => $driver->last_seen_at,
        ]);
        $driver->user->save();
    }

    private function advanceIdleDriver(Driver $driver): void
    {
        $baseSpeed = max(10, (float) $driver->speed_kmh ?: $this->randomSpeed());
        $stepKm = ($baseSpeed / 3600) * 1.5;
        $heading = $driver->heading !== null ? (float) $driver->heading : rand(0, 359);
        $heading += rand(-20, 20);

        $point = $this->moveByHeading(
            (float) ($driver->latitude ?? $driver->spawn_center_latitude ?? 0),
            (float) ($driver->longitude ?? $driver->spawn_center_longitude ?? 0),
            $heading,
            $stepKm
        );

        $this->updateDriver($driver, [
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
            'heading' => $point['heading'],
            'speed_kmh' => $baseSpeed,
            'status' => 'available',
            'workflow_state' => 'available',
        ]);
    }

    private function advanceRideDriver(Driver $driver): void
    {
        $ride = DriverSimulatorRide::query()->where('id', $driver->assigned_trip_id)->first();

        if (!$ride) {
            $this->updateDriver($driver, [
                'status' => 'available',
                'workflow_state' => 'available',
                'assigned_trip_id' => null,
            ]);

            return;
        }

        if ($driver->status === 'driving_to_pickup') {
            $targetLat = (float) $ride->pickup_latitude;
            $targetLng = (float) $ride->pickup_longitude;
            $nextState = 'waiting_passenger';
        } elseif ($driver->status === 'waiting_passenger') {
            $this->updateRideState($ride, 'trip_started');
            $this->updateDriver($driver, [
                'status' => 'trip_started',
                'workflow_state' => 'trip_started',
            ]);

            return;
        } elseif ($driver->status === 'trip_started' || $driver->status === 'busy') {
            $targetLat = (float) $ride->dropoff_latitude;
            $targetLng = (float) $ride->dropoff_longitude;
            $nextState = 'trip_finished';
        } else {
            return;
        }

        $speed = max(12, (float) $driver->speed_kmh ?: $this->randomSpeed());
        $stepKm = ($speed / 3600) * 1.5;
        $currentLat = (float) ($driver->latitude ?? $targetLat);
        $currentLng = (float) ($driver->longitude ?? $targetLng);
        $distance = $this->distanceKm($currentLat, $currentLng, $targetLat, $targetLng);

        if ($distance <= max(0.03, $stepKm)) {
            $this->updateDriver($driver, [
                'latitude' => $targetLat,
                'longitude' => $targetLng,
                'heading' => $this->bearing($currentLat, $currentLng, $targetLat, $targetLng),
                'speed_kmh' => $speed,
                'status' => $nextState,
                'workflow_state' => $nextState,
            ]);

            if ($nextState === 'waiting_passenger') {
                $this->updateRideState($ride, 'waiting_passenger');
            } elseif ($nextState === 'trip_finished') {
                $this->finishRide($ride, $driver);
            }

            return;
        }

        $point = $this->moveTowards($currentLat, $currentLng, $targetLat, $targetLng, $stepKm);
        $this->updateDriver($driver, [
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
            'heading' => $point['heading'],
            'speed_kmh' => $speed,
            'status' => $driver->status,
            'workflow_state' => $driver->workflow_state,
        ]);
    }

    private function updateRideState(DriverSimulatorRide $ride, string $status): void
    {
        $ride->status = $status;

        if ($status === 'trip_started' && $ride->started_at === null) {
            $ride->started_at = now();
        }

        $ride->save();
    }

    private function finishRide(DriverSimulatorRide $ride, Driver $driver): void
    {
        $ride->status = 'finished';
        $ride->finished_at = now();
        $ride->save();

        $this->updateDriver($driver, [
            'status' => 'available',
            'workflow_state' => 'available',
            'assigned_trip_id' => null,
            'pickup_latitude' => null,
            'pickup_longitude' => null,
            'dropoff_latitude' => null,
            'dropoff_longitude' => null,
        ]);
    }

    private function buildSimpleRoute(
        float $currentLat,
        float $currentLng,
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng
    ): array {
        return [
            [
                'lat' => $currentLat,
                'lng' => $currentLng,
            ],
            [
                'lat' => $pickupLat,
                'lng' => $pickupLng,
            ],
            [
                'lat' => $dropoffLat,
                'lng' => $dropoffLng,
            ],
        ];
    }

    private function randomPointInRadius(float $centerLat, float $centerLng, float $radiusKm): array
    {
        $distance = $radiusKm * sqrt(mt_rand() / mt_getrandmax());
        $bearing = lcg_value() * 2 * M_PI;
        $earthRadius = 6371.0;

        $centerLatRad = deg2rad($centerLat);
        $centerLngRad = deg2rad($centerLng);
        $angularDistance = $distance / $earthRadius;

        $lat = asin(
            sin($centerLatRad) * cos($angularDistance)
            + cos($centerLatRad) * sin($angularDistance) * cos($bearing)
        );

        $lng = $centerLngRad + atan2(
            sin($bearing) * sin($angularDistance) * cos($centerLatRad),
            cos($angularDistance) - sin($centerLatRad) * sin($lat)
        );

        return [
            'latitude' => round(rad2deg($lat), 7),
            'longitude' => round(rad2deg($lng), 7),
        ];
    }

    private function moveByHeading(float $latitude, float $longitude, float $heading, float $distanceKm): array
    {
        $earthRadius = 6371.0;
        $bearing = deg2rad(fmod($heading + 360.0, 360.0));
        $lat1 = deg2rad($latitude);
        $lng1 = deg2rad($longitude);
        $angularDistance = $distanceKm / $earthRadius;

        $lat2 = asin(
            sin($lat1) * cos($angularDistance)
            + cos($lat1) * sin($angularDistance) * cos($bearing)
        );

        $lng2 = $lng1 + atan2(
            sin($bearing) * sin($angularDistance) * cos($lat1),
            cos($angularDistance) - sin($lat1) * sin($lat2)
        );

        return [
            'latitude' => round(rad2deg($lat2), 7),
            'longitude' => round(rad2deg($lng2), 7),
            'heading' => fmod($heading + rand(-12, 12) + 360.0, 360.0),
        ];
    }

    private function moveTowards(float $fromLat, float $fromLng, float $toLat, float $toLng, float $distanceKm): array
    {
        $totalDistance = $this->distanceKm($fromLat, $fromLng, $toLat, $toLng);

        if ($totalDistance <= 0) {
            return [
                'latitude' => round($toLat, 7),
                'longitude' => round($toLng, 7),
                'heading' => 0.0,
            ];
        }

        $ratio = min(1.0, $distanceKm / $totalDistance);
        $latitude = $fromLat + (($toLat - $fromLat) * $ratio);
        $longitude = $fromLng + (($toLng - $fromLng) * $ratio);

        return [
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'heading' => $this->bearing($fromLat, $fromLng, $toLat, $toLng),
        ];
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function bearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $y = sin(deg2rad($lng2 - $lng1)) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2))
            - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng2 - $lng1));

        return fmod((rad2deg(atan2($y, $x)) + 360.0), 360.0);
    }

    private function makeDriverName(?int $index = null): string
    {
        $base = ['Amin', 'Karim', 'Rami', 'Hassan', 'Yara', 'Maya', 'Omar', 'Nour', 'Zein', 'Ali'];

        return 'Driver ' . ($index ?? Str::upper(Str::random(3))) . ' ' . $base[array_rand($base)];
    }

    private function makeDriverPhone(): string
    {
        return '71' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function randomVehicleType(): string
    {
        return self::VEHICLE_TYPES[array_rand(self::VEHICLE_TYPES)];
    }

    private function randomSpeed(): float
    {
        return (float) random_int(25, 65);
    }
}
