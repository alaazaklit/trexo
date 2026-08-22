<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverGalleryImage;
use App\Models\School;
use App\Models\SchoolBusRoute;
use App\Models\SchoolBusSubscription;
use App\Services\MapboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SchoolController extends Controller
{
    // The parent-facing "browse schools" screen — the list here grows
    // organically as drivers resolve new schools via resolveFromPlace()
    // below, there's no separate admin-seeding step required anymore.
    public function index()
    {
        JWTAuth::parseToken()->authenticate();

        $schools = School::where('is_active', true)->orderBy('name')->get(['id', 'name', 'area']);

        return response()->json([
            'result' => true,
            'message' => 'Schools loaded',
            'data' => $schools,
        ]);
    }

    // Step 2 of the parent flow (school -> pickup area -> driver) — every
    // distinct pickup area any approved driver actively serves for this
    // school, with a starting price so the seller can gauge an area before
    // drilling into its driver list.
    public function pickupAreasForSchool(School $school)
    {
        JWTAuth::parseToken()->authenticate();

        $areas = SchoolBusRoute::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            // Visible as soon as a driver has added the route — no separate
            // request+approval step. Only an explicit admin suspend/reject
            // hides it; a null/pending status (including a driver who's
            // never touched school_bus_status at all) still shows through.
            ->whereHas('driver', fn ($q) => $q->where(
                fn ($qq) => $qq->whereNull('school_bus_status')
                    ->orWhereNotIn('school_bus_status', ['suspended', 'rejected'])
            ))
            ->selectRaw('pickup_area, MIN(monthly_price) as min_price, COUNT(DISTINCT driver_id) as driver_count')
            ->groupBy('pickup_area')
            ->orderBy('pickup_area')
            ->get();

        return response()->json([
            'result' => true,
            'message' => 'Pickup areas loaded',
            'data' => $areas->map(fn ($row) => [
                'pickup_area' => $row->pickup_area,
                'min_price' => (float) $row->min_price,
                'driver_count' => (int) $row->driver_count,
            ]),
        ]);
    }

    // Drivers who serve this school — i.e. have at least one active route
    // for it — and are approved for the school-bus service. Once a pickup
    // area has been chosen (step 2), scope to drivers serving THAT area and
    // include each one's price for it, sorted with price as the dominant
    // factor: cheapest first, and — only as a tiebreak between equally
    // priced drivers — the one already running more active students on
    // that exact route first (a fuller route there is a sign the driver
    // already covers that area well, so pickups end up clustered together
    // rather than scattered).
    public function driversForSchool(Request $request, School $school)
    {
        JWTAuth::parseToken()->authenticate();

        $pickupArea = $request->query('pickup_area');

        $drivers = Driver::query()
            ->with(['user', 'schoolBusRoutes' => function ($q) use ($school, $pickupArea) {
                $q->where('school_id', $school->id)->where('is_active', true);
                if (!empty($pickupArea)) {
                    $q->where('pickup_area', $pickupArea);
                }
            }])
            // Same "enabled unless explicitly suspended/rejected" rule as
            // pickupAreasForSchool() above.
            ->where(
                fn ($q) => $q->whereNull('school_bus_status')
                    ->orWhereNotIn('school_bus_status', ['suspended', 'rejected'])
            )
            ->whereHas('schoolBusRoutes', function ($q) use ($school, $pickupArea) {
                $q->where('school_id', $school->id)->where('is_active', true);
                if (!empty($pickupArea)) {
                    $q->where('pickup_area', $pickupArea);
                }
            })
            ->get();

        $mapped = $drivers->map(function (Driver $driver) {
            // Cheapest of the (possibly several, if no pickup_area filter
            // was given) matching routes represents this driver in the list.
            $route = $driver->schoolBusRoutes->sortBy('monthly_price')->first();

            return [
                'driver_id' => $driver->id,
                'user_id' => $driver->user_id,
                'name' => $driver->user->name,
                'avatar' => $driver->user->avatar,
                'rating' => $driver->rating ?? 0,
                'photo' => $this->firstVehiclePhoto($driver->user_id),
                'schools_served' => $driver->schoolBusRoutes()->where('is_active', true)->distinct('school_id')->count('school_id'),
                'pickup_area' => $route?->pickup_area,
                'monthly_price' => $route !== null ? (float) $route->monthly_price : null,
                'active_students_count' => $route !== null
                    ? SchoolBusSubscription::where('route_id', $route->id)->where('status', 'active')->count()
                    : 0,
                // Lets the app's drivers list offer a direct "select" shortcut
                // straight into the subscribe form for this exact route.
                'route_id' => $route?->id,
                'child_discount_percent' => (float) ($driver->school_bus_child_discount_percent ?? 0),
            ];
        });

        $sorted = $mapped->sort(function ($a, $b) {
            $priceA = $a['monthly_price'] ?? PHP_FLOAT_MAX;
            $priceB = $b['monthly_price'] ?? PHP_FLOAT_MAX;
            if ($priceA !== $priceB) {
                return $priceA <=> $priceB;
            }

            return $b['active_students_count'] <=> $a['active_students_count'];
        })->values();

        return response()->json([
            'result' => true,
            'message' => 'Drivers loaded',
            'data' => $sorted,
        ]);
    }

    // Driver-facing: given a Google Place ID (from the autocomplete search
    // used while adding a route), returns the matching School row, creating
    // it on first use. `place_id` is the dedup key — a school already
    // resolved by any driver is reused rather than duplicated. Reuses the
    // same GOOGLE_MAPS_KEY / server-side-only pattern as MapProxyController,
    // but (unlike that controller's placeDetails) parses the provider's
    // response here since the result needs to become a School row, not just
    // be forwarded to the client. Same 'mapbox:'-prefix detection as
    // MapProxyController::placeDetails() for the same reason: a place_id
    // belongs to whichever provider issued it at autocomplete time,
    // independent of whatever maps.places_provider is configured now.
    public function resolveFromPlace(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'place_id' => 'required|string|max:200',
            'language' => 'nullable|string|max:10',
            // Optional pre-resolved data — the driver's own autocomplete
            // widget already did its own Google Details lookup for this
            // exact place_id to get coordinates for the map camera, before
            // this endpoint is even called. When all four are present, they
            // came from that same lookup, so this endpoint reuses them
            // directly instead of firing a second, separate Google Details
            // call for the same place_id. Any caller that can't supply them
            // still works — see the Google-calling fallback below.
            'name' => 'nullable|string|max:255',
            'formatted_address' => 'nullable|string|max:500',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $placeId = $request->input('place_id');

        $existing = School::where('place_id', $placeId)->first();
        if ($existing) {
            return response()->json([
                'result' => true,
                'message' => 'School resolved',
                'data' => $existing->only(['id', 'name', 'area']),
            ]);
        }

        $preResolvedName = $request->input('name');
        $preResolvedAddress = $request->input('formatted_address');
        $preResolvedLat = $request->input('lat');
        $preResolvedLng = $request->input('lng');
        $hasPreResolvedData = $preResolvedName !== null
            && $preResolvedAddress !== null
            && $preResolvedLat !== null
            && $preResolvedLng !== null;

        if ($hasPreResolvedData) {
            $result = [
                'name' => $preResolvedName,
                'formatted_address' => $preResolvedAddress,
                'geometry' => ['location' => ['lat' => $preResolvedLat, 'lng' => $preResolvedLng]],
            ];
        } elseif (str_starts_with($placeId, 'mapbox:')) {
            $normalized = (new MapboxService())->retrievePlace($placeId);
            $result = $normalized['result'] ?? null;

            if ($result === null) {
                Log::warning('SchoolController::resolveFromPlace: no result from Mapbox', ['place_id' => $placeId]);
                return response()->json(['result' => false, 'message' => 'Could not find this school'], 422);
            }
        } else {
            $apiKey = config('services.google_maps.key');
            if (empty($apiKey)) {
                return response()->json(['result' => false, 'message' => 'Maps not configured'], 503);
            }

            try {
                // Without a language, Google defaults the returned
                // formatted_address to whatever it deems appropriate for the
                // region (often English) — while the school's `name` comes
                // from the driver's own Arabic-language autocomplete search
                // result. Left mismatched, a school ends up with an Arabic
                // name but an English address, which reads as broken/random
                // to an Arabic-locale user despite each half being "correct".
                // Restricted to Basic Data fields only — a School row only
                // ever stores name/area/lat/lng. Leaving `fields` unset
                // makes Google return (and bill) the full default set,
                // which spans the pricier Contact + Atmosphere Data SKUs
                // for data never read here.
                $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'placeid' => $placeId,
                    'language' => $request->input('language', 'en'),
                    'fields' => 'place_id,name,formatted_address,geometry',
                    'key' => $apiKey,
                ]);

                $body = $response->json();
                $result = $body['result'] ?? null;

                if (($body['status'] ?? null) !== 'OK' || !$result) {
                    Log::warning('SchoolController::resolveFromPlace: no result from Google', [
                        'status' => $body['status'] ?? null,
                        'place_id' => $placeId,
                    ]);
                    return response()->json(['result' => false, 'message' => 'Could not find this school'], 422);
                }
            } catch (\Throwable $e) {
                Log::warning('SchoolController::resolveFromPlace failed', ['error' => $e->getMessage()]);
                return response()->json(['result' => false, 'message' => 'Could not resolve this school'], 502);
            }
        }

        try {
            $school = School::firstOrCreate(
                ['place_id' => $placeId],
                [
                    'name' => $result['name'] ?? 'Unknown School',
                    'area' => $result['formatted_address'] ?? null,
                    'lat' => $result['geometry']['location']['lat'] ?? null,
                    'lng' => $result['geometry']['location']['lng'] ?? null,
                ]
            );

            return response()->json([
                'result' => true,
                'message' => 'School resolved',
                'data' => $school->only(['id', 'name', 'area']),
            ], 201);
        } catch (\Throwable $e) {
            Log::warning('SchoolController::resolveFromPlace failed', ['error' => $e->getMessage()]);
            return response()->json(['result' => false, 'message' => 'Could not resolve this school'], 502);
        }
    }

    private function firstVehiclePhoto(int $userId): ?string
    {
        return DriverGalleryImage::where('user_id', $userId)
            ->where('category', 'vehicle')
            ->orderBy('sort_order')
            ->value('path');
    }
}
