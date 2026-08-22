<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\DriverGalleryImage;
use App\Models\DriverIntercityRouteOverride;
use App\Models\DriverServiceLine;
use App\Models\IntercityRoute;
use App\Models\PricingZone;
use App\Models\User;
use App\Services\Pricing\FareCalculator;
use App\Traits\MatchesDriverSchedules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class DriverProfileController extends Controller
{
    use MatchesDriverSchedules;

    private const CATEGORIES = ['vehicle', 'work'];
    private const LINE_TYPES = ['work_area', 'schedule'];
    private const OVERRIDE_FIELDS = [
        'base_fare_override' => 'base_fare',
        'price_per_km_override' => 'price_per_km',
        'detour_surcharge_override' => 'detour_surcharge',
        'reservation_multiplier_override' => 'reservation_multiplier',
        'out_of_zone_percent_override' => 'out_of_zone_percent',
    ];

    // These three are the driver's own plain settings (see the simplified
    // pricing card) — no longer bounded to an admin-computed ±% envelope
    // around the zone/global default. detour_surcharge_override and
    // reservation_multiplier_override (the "Advanced" section) still are.
    private const UNBOUNDED_OVERRIDE_FIELDS = [
        'base_fare_override',
        'price_per_km_override',
        'out_of_zone_percent_override',
    ];

    // Seller-facing: everything needed to render the Driver Details page for
    // a given driver, in one call.
    public function details($userId)
    {
        JWTAuth::parseToken()->authenticate();

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $driver = Driver::where('user_id', $userId)->first();
        $lines = DriverServiceLine::where('user_id', $userId)->get();

        return response()->json([
            'result' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'gender' => $user->gender,
                ],
                'driver' => [
                    'rating' => $driver->rating ?? 0,
                    'vehicle_type' => $driver->vehicle_type ?? null,
                    'vehicle_make' => $driver->vehicle_make ?? null,
                    'vehicle_model' => $driver->vehicle_model ?? null,
                    'vehicle_color' => $driver->vehicle_color ?? null,
                    'vehicle_plate' => $driver->vehicle_plate ?? null,
                    'transmission' => $driver->transmission ?? null,
                ],
                'gallery' => [
                    'vehicle' => $this->formatGallery($userId, 'vehicle'),
                    'work' => $this->formatGallery($userId, 'work'),
                ],
                'work_areas' => $lines->where('line_type', 'work_area')->values()
                    ->map(fn ($line) => $this->formatLine($line)),
                'schedules' => $lines->where('line_type', 'schedule')->values()
                    ->map(fn ($line) => $this->formatLine($line)),
            ],
        ]);
    }

    // Driver-facing: the authenticated driver's own price overrides (null
    // where unset, meaning the zone/global default currently applies), the
    // zone they've chosen to work in (if any), the full list of zones to
    // choose from, and the range they're allowed to set each override
    // within — computed against their chosen zone's rate when they have
    // one, so the numbers shown are relevant to where they actually work.
    public function getPricingOverrides(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $driver = Driver::where('user_id', $user->id)->first();
        $zone = $driver?->pricing_zone_id
            ? PricingZone::find($driver->pricing_zone_id)
            : null;
        $bounds = $this->getOverrideBounds($zone);

        return response()->json([
            'result' => true,
            'data' => [
                'base_fare_override' => $driver?->base_fare_override,
                'price_per_km_override' => $driver?->price_per_km_override,
                'detour_surcharge_override' => $driver?->detour_surcharge_override,
                'reservation_multiplier_override' => $driver?->reservation_multiplier_override,
                'out_of_zone_percent_override' => $driver?->out_of_zone_percent_override,
                // A driver with no `drivers` row, or one created before this
                // column existed, must read as fully eligible — never
                // silently true=>false just because the row is missing.
                'offers_taxi' => $driver?->offers_taxi ?? true,
                'offers_delivery' => $driver?->offers_delivery ?? true,
                'pricing_zone_id' => $driver?->pricing_zone_id,
                'zones' => PricingZone::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (PricingZone $z) => ['id' => $z->id, 'name' => $z->name]),
                'bounds' => $bounds,
            ],
        ]);
    }

    // Sets (or, passed null, clears back to the global default) any subset
    // of the four override fields. Each provided value is checked against
    // getOverrideBounds() before saving — a driver can nudge their own
    // price within an admin-controlled range, not set anything they like.
    public function updatePricingOverrides(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'base_fare_override' => 'nullable|numeric',
            'price_per_km_override' => 'nullable|numeric',
            'detour_surcharge_override' => 'nullable|numeric',
            'reservation_multiplier_override' => 'nullable|numeric',
            'out_of_zone_percent_override' => 'nullable|numeric',
            'pricing_zone_id' => 'nullable|integer|exists:pricing_zones,id',
            'offers_taxi' => 'nullable|boolean',
            'offers_delivery' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $existingDriver = Driver::where('user_id', $user->id)->first();
        $updates = [];

        // Validate the override fields against whichever zone will be in
        // effect once this request is applied — the newly chosen one if
        // they're changing it in this same call, otherwise their existing
        // one.
        if ($request->has('pricing_zone_id')) {
            $zoneId = $request->input('pricing_zone_id');
            $updates['pricing_zone_id'] = $zoneId === null || $zoneId === '' ? null : (int) $zoneId;
        }
        $effectiveZoneId = array_key_exists('pricing_zone_id', $updates)
            ? $updates['pricing_zone_id']
            : $existingDriver?->pricing_zone_id;
        $zone = $effectiveZoneId ? PricingZone::find($effectiveZoneId) : null;
        $bounds = $this->getOverrideBounds($zone);

        foreach (self::OVERRIDE_FIELDS as $field => $boundsKey) {
            if (!$request->has($field)) {
                continue;
            }

            $value = $request->input($field);
            if ($value === null || $value === '') {
                $updates[$field] = null;
                continue;
            }

            $value = (float) $value;

            if (in_array($field, self::UNBOUNDED_OVERRIDE_FIELDS, true)) {
                // No allowed-range check for these — just a basic sanity
                // floor, since a negative base fare/per-km/out-of-zone
                // percent would produce a nonsensical (or negative) price
                // downstream.
                if ($value < 0) {
                    return response()->json([
                        'result' => false,
                        'message' => 'القيمة يجب أن تكون صفر أو أكثر.',
                    ], 422);
                }
            } else {
                $range = $bounds[$boundsKey];
                if ($value < $range['min'] || $value > $range['max']) {
                    return response()->json([
                        'result' => false,
                        'message' => "القيمة يجب أن تكون بين {$range['min']} و {$range['max']}.",
                    ], 422);
                }
            }

            $updates[$field] = $value;
        }

        if ($request->has('offers_taxi')) {
            $updates['offers_taxi'] = $request->boolean('offers_taxi');
        }
        if ($request->has('offers_delivery')) {
            $updates['offers_delivery'] = $request->boolean('offers_delivery');
        }

        // A driver accepting neither service is almost certainly a mistake
        // (they'd silently stop receiving any order at all) rather than
        // intent, so it's rejected outright instead of saved.
        $effectiveOffersTaxi = array_key_exists('offers_taxi', $updates)
            ? $updates['offers_taxi']
            : ($existingDriver?->offers_taxi ?? true);
        $effectiveOffersDelivery = array_key_exists('offers_delivery', $updates)
            ? $updates['offers_delivery']
            : ($existingDriver?->offers_delivery ?? true);
        if (!$effectiveOffersTaxi && !$effectiveOffersDelivery) {
            return response()->json([
                'result' => false,
                'message' => 'يجب قبول خدمة واحدة على الأقل (تاكسي أو توصيل).',
            ], 422);
        }

        if (empty($updates)) {
            return response()->json([
                'result' => false,
                'message' => 'لم يتم إرسال أي تعديل.',
            ], 422);
        }

        $driver = $existingDriver;
        if ($driver) {
            $driver->fill($updates);
            $driver->save();
        } else {
            // A driver with no `drivers` row yet reads as implicitly
            // eligible in the matching query's approval check (it only
            // excludes an explicit 'pending'/'rejected' value). Creating a
            // row here purely to store a price override must not silently
            // default them to 'pending' and make them stop being matched
            // until an admin approves them.
            $driver = Driver::create(array_merge([
                'offers_taxi' => true,
                'offers_delivery' => true,
            ], $updates, [
                'user_id' => $user->id,
                'approval_status' => 'approved',
            ]));
        }

        return response()->json([
            'result' => true,
            'message' => 'تم تحديث تسعيرك بنجاح',
            'data' => [
                'base_fare_override' => $driver->base_fare_override,
                'price_per_km_override' => $driver->price_per_km_override,
                'detour_surcharge_override' => $driver->detour_surcharge_override,
                'reservation_multiplier_override' => $driver->reservation_multiplier_override,
                'out_of_zone_percent_override' => $driver->out_of_zone_percent_override,
                'offers_taxi' => $driver->offers_taxi,
                'offers_delivery' => $driver->offers_delivery,
                'pricing_zone_id' => $driver->pricing_zone_id,
            ],
        ], 201);
    }

    // Driver-facing price simulator: given a pickup/destination pair, prices
    // it exactly like a real trip would — same FareCalculator::calculate()
    // call findMatchingDrivers() uses, the same pickup-location zone lookup
    // (not the driver's own chosen working zone — that's a different,
    // eligibility-only concept), the same intercity-fixed-fare short
    // circuit, the same distance/duration resolution — but performs no
    // writes whatsoever: no order/reservation row, no notification, no
    // status change. Purely a read+compute endpoint.
    //
    // Real orders/reservations get their pickup/destination city+region from
    // the client's own geocoding, stored on the addresses table
    // (OrderController::getOrderDrivers reads `address_from.city` etc.).
    // This endpoint only receives raw coordinates, so it resolves the same
    // city/region text itself via resolveCityRegion() before doing the
    // identical zone/intercity lookups findMatchingDrivers() does.
    public function testPrice(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            'order_type' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $orderType = (int) $request->input('order_type', 0);
        $pickup = [
            'lat' => (float) $request->input('pickup_lat'),
            'lng' => (float) $request->input('pickup_lng'),
        ];
        $destination = [
            'lat' => (float) $request->input('destination_lat'),
            'lng' => (float) $request->input('destination_lng'),
        ];

        $straightLineKm = $this->haversineDistance($pickup['lat'], $pickup['lng'], $destination['lat'], $destination['lng']);
        $distanceKm = $this->resolveTripDistanceKm($pickup, $destination, $straightLineKm);
        $durationMinutes = $this->drivingDurationMinutes($pickup['lat'], $pickup['lng'], $destination['lat'], $destination['lng']);

        $driver = Driver::where('user_id', $user->id)->first();

        $pickupLocation = $this->resolveCityRegion($pickup['lat'], $pickup['lng']);
        $destinationLocation = $this->resolveCityRegion($destination['lat'], $destination['lng']);
        $zone = $this->findPricingZone($pickupLocation['city'], $pickupLocation['region']);
        $destinationZone = $this->findPricingZone($destinationLocation['city'], $destinationLocation['region']);
        $intercityRoute = $this->findIntercityRoute($zone, $destinationZone);
        // Same cross-zone/intercity guardrail findMatchingDrivers() applies —
        // see FareCalculator::effectivePerKmRate.
        $crossesZones = $zone !== null && $destinationZone !== null && $zone->id !== $destinationZone->id;

        $baseFare = $driver?->base_fare_override !== null
            ? (float) $driver->base_fare_override
            : $this->getBaseFare($orderType, $zone);
        $normalPricePerKm = $driver?->price_per_km_override !== null
            ? (float) $driver->price_per_km_override
            : FareCalculator::effectivePerKmRate(
                $this->getNormalPricePerKm($orderType, $zone),
                $this->getNormalPricePerKm($orderType, $destinationZone),
                $this->getNormalPricePerKm($orderType, null),
                $crossesZones
            );

        // Same out-of-zone surcharge findMatchingDrivers() applies — see the
        // comment there. $driver->pricing_zone_id is this driver's own
        // chosen working zone, a different concept from $zone/$destinationZone
        // above (which are the trip's pickup/destination zones).
        $isOutOfZone = $driver?->pricing_zone_id !== null
            && $intercityRoute === null
            && $destinationZone === null;
        $outOfZonePercent = 0.0;
        $effectivePricePerKm = $normalPricePerKm;
        if ($isOutOfZone) {
            $outOfZonePercent = $driver->out_of_zone_percent_override !== null
                ? (float) $driver->out_of_zone_percent_override
                : $this->getOutOfZonePercentDefault();
            $effectivePricePerKm = FareCalculator::applyOutOfZonePercent($normalPricePerKm, $outOfZonePercent);
        }

        $sharedRidePricePerKm = $effectivePricePerKm * $this->getSettingFloat('fare.shared_multiplier', 0.70);
        $detourSurchargePerKm = $driver?->detour_surcharge_override !== null
            ? (float) $driver->detour_surcharge_override
            : $this->getDetourSurchargePerKm();

        $intercityFixedFare = null;
        if ($intercityRoute !== null) {
            $routeOverride = $driver
                ? DriverIntercityRouteOverride::where('user_id', $user->id)
                    ->where('intercity_route_id', $intercityRoute->id)
                    ->first()
                : null;
            $overrideFare = $routeOverride === null
                ? null
                : ($orderType === 0 ? $routeOverride->fixed_fare_taxi_override : $routeOverride->fixed_fare_delivery_override);
            $intercityFixedFare = $overrideFare !== null
                ? (float) $overrideFare
                : ($orderType === 0 ? $intercityRoute->fixed_fare_taxi : $intercityRoute->fixed_fare_delivery);
        }

        $priceUsd = FareCalculator::calculate(
            $baseFare,
            $effectivePricePerKm,
            $sharedRidePricePerKm,
            $detourSurchargePerKm,
            $distanceKm,
            false, // onRoute — a direct point-to-point test, not a shared-ride match
            0.0,   // detourKm — no detour concept for a standalone test
            1.0,   // reservationMultiplier — tested as a one-way order, not a reservation round trip
            $intercityFixedFare !== null ? (float) $intercityFixedFare : null
        );

        $lbpPerUsd = $this->getSettingFloat('pricing.exchange_rate_lbp_usd', 89500.0);
        // Money is computed and returned as an integer LBP amount from here
        // on — LBP has no fractional unit in practice, and the final 20,000
        // rounding happens exactly once, on this whole-trip total, never on
        // an individual component (per-km rate, base fare, distance, etc.).
        $calculatedPriceLbp = (int) round($priceUsd * $lbpPerUsd);
        $finalPriceLbp = FareCalculator::roundToNearestLbp($calculatedPriceLbp);

        return response()->json([
            'result' => true,
            'data' => [
                'distance_km' => round($distanceKm, 2),
                'duration_minutes' => $durationMinutes,
                'calculated_price_lbp' => $calculatedPriceLbp,
                'final_price_lbp' => $finalPriceLbp,
                'is_out_of_zone' => $isOutOfZone,
                'out_of_zone_percent' => $outOfZonePercent,
            ],
        ]);
    }

    // Driver-facing: every active intercity route (with readable zone
    // names), the admin's global fixed fare per order kind, this driver's
    // own override (if any), and the range they're allowed to set it
    // within for each kind.
    public function getIntercityRouteOverrides(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $routes = IntercityRoute::where('is_active', true)
            ->with(['fromZone', 'toZone'])
            ->get();

        $overrides = DriverIntercityRouteOverride::where('user_id', $user->id)
            ->get()
            ->keyBy('intercity_route_id');

        // No row at all (driver never opened this screen for this route)
        // defaults to whatever findMatchingDrivers() will actually treat
        // them as — true (matches today's behavior) for a driver whose
        // account predates the opt-in cutoff, false (must explicitly
        // enable each route) for one created after it. Keeps this screen's
        // displayed toggle state consistent with real eligibility.
        $cutoff = $this->getLongDistanceOptInCutoff();
        $defaultActive = !($cutoff !== null && $user->created_at !== null && $user->created_at->toDateTimeString() >= $cutoff);

        $data = $routes->map(function (IntercityRoute $route) use ($overrides, $defaultActive) {
            $override = $overrides->get($route->id);
            return [
                'id' => $route->id,
                'from_zone' => $route->fromZone?->name,
                'to_zone' => $route->toZone?->name,
                'fixed_fare_taxi' => $route->fixed_fare_taxi,
                'fixed_fare_delivery' => $route->fixed_fare_delivery,
                'fixed_fare_taxi_override' => $override?->fixed_fare_taxi_override,
                'fixed_fare_delivery_override' => $override?->fixed_fare_delivery_override,
                'is_active_taxi' => $override?->is_active_taxi ?? $defaultActive,
                'is_active_delivery' => $override?->is_active_delivery ?? $defaultActive,
                'bounds_taxi' => $this->getIntercityFareBounds($route, 0),
                'bounds_delivery' => $this->getIntercityFareBounds($route, 1),
            ];
        })->values();

        return response()->json([
            'result' => true,
            'data' => $data,
        ]);
    }

    // Sets (or, passed null, clears) this driver's own fare for one
    // intercity route. Each provided value is checked against
    // getIntercityFareBounds() for that specific route+kind — a driver can
    // nudge a route's price within an admin-controlled range, not set
    // anything they like. A kind the admin never set a fixed fare for at
    // all has no override allowed either (nothing to bound it against).
    public function updateIntercityRouteOverride(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'intercity_route_id' => 'required|integer|exists:intercity_routes,id',
            'fixed_fare_taxi_override' => 'nullable|numeric',
            'fixed_fare_delivery_override' => 'nullable|numeric',
            'is_active_taxi' => 'nullable|boolean',
            'is_active_delivery' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $route = IntercityRoute::find($request->input('intercity_route_id'));

        $updates = [];
        foreach ([0 => 'fixed_fare_taxi_override', 1 => 'fixed_fare_delivery_override'] as $orderType => $field) {
            if (!$request->has($field)) {
                continue;
            }

            $value = $request->input($field);
            if ($value === null || $value === '') {
                $updates[$field] = null;
                continue;
            }

            $bounds = $this->getIntercityFareBounds($route, $orderType);
            if ($bounds['min'] === null) {
                return response()->json([
                    'result' => false,
                    'message' => 'لا يمكن تخصيص هذا الخط لأن السعر الأساسي غير محدد من الإدارة.',
                ], 422);
            }

            $value = (float) $value;
            if ($value < $bounds['min'] || $value > $bounds['max']) {
                return response()->json([
                    'result' => false,
                    'message' => "القيمة يجب أن تكون بين {$bounds['min']} و {$bounds['max']}.",
                ], 422);
            }

            $updates[$field] = $value;
        }

        // Whether this driver serves this specific route at all (per order
        // kind) — independent of whether they've also set a custom price.
        // Checked by MatchesDriverSchedules::findMatchingDrivers() before a
        // driver is even considered eligible for a long-distance trip.
        foreach (['is_active_taxi', 'is_active_delivery'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = (bool) $request->input($field);
            }
        }

        if (empty($updates)) {
            return response()->json([
                'result' => false,
                'message' => 'لم يتم إرسال أي تعديل.',
            ], 422);
        }

        $override = DriverIntercityRouteOverride::updateOrCreate(
            ['user_id' => $user->id, 'intercity_route_id' => $route->id],
            $updates
        );

        return response()->json([
            'result' => true,
            'message' => 'تم تحديث سعرك لهذا الخط بنجاح',
            'data' => [
                'fixed_fare_taxi_override' => $override->fixed_fare_taxi_override,
                'fixed_fare_delivery_override' => $override->fixed_fare_delivery_override,
                'is_active_taxi' => $override->is_active_taxi,
                'is_active_delivery' => $override->is_active_delivery,
            ],
        ], 201);
    }

    private function formatGallery($userId, string $category)
    {
        return DriverGalleryImage::where('user_id', $userId)
            ->where('category', $category)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'sort_order' => $image->sort_order,
            ]);
    }

    private function formatLine(DriverServiceLine $line): array
    {
        return [
            'line_id' => $line->client_line_id,
            'service_type' => $line->service_type,
            'line_type' => $line->line_type,
            'from_label' => $line->from_label,
            'to_label' => $line->to_label,
            'discount_rules' => $line->discount_rules ?? [],
            'schedule_data' => [
                'mode' => $line->schedule_mode,
                'weekly_start_times' => $line->weekly_start_times ?? [],
                'weekly_end_times' => $line->weekly_end_times ?? [],
                'specific_dates' => $line->specific_dates ?? [],
            ],
        ];
    }

    // Driver-facing: the authenticated driver's own lines, to populate
    // work_areas_page.dart / schedule_page.dart on open.
    public function getServiceLines(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $query = DriverServiceLine::where('user_id', $user->id);

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->input('service_type'));
        }

        if ($request->filled('line_type')) {
            $query->where('line_type', $request->input('line_type'));
        }

        $lines = $query->get()->map(fn ($line) => $this->formatLine($line));

        return response()->json(['result' => true, 'data' => $lines]);
    }

    // Upserts a work-area route or a schedule line for the authenticated
    // driver, keyed on (user_id, client_line_id). Accepts the payload shape
    // already sent by work_areas_page.dart / schedule_page.dart.
    public function saveServiceLine(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'service_type' => 'required|string',
            'line_id' => 'required|string',
            'line' => 'required|array',
            'line.from_label' => 'nullable|string',
            'line.to_label' => 'nullable|string',
            'line.line_type' => 'nullable|string|in:' . implode(',', self::LINE_TYPES),
            'line.discount_rules' => 'nullable|array',
            'schedule_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $line = $request->input('line', []);
        $scheduleData = $request->input('schedule_data', []);

        $attributes = [
            'service_type' => $request->input('service_type'),
            'line_type' => $line['line_type'] ?? 'work_area',
            'from_label' => $line['from_label'] ?? null,
            'to_label' => $line['to_label'] ?? null,
            'discount_rules' => $line['discount_rules'] ?? [],
        ];

        if (!empty($scheduleData)) {
            $attributes['schedule_mode'] = $scheduleData['mode'] ?? null;
            $attributes['weekly_start_times'] = $scheduleData['weekly_start_times'] ?? [];
            $attributes['weekly_end_times'] = $scheduleData['weekly_end_times'] ?? [];
            $attributes['specific_dates'] = $scheduleData['specific_dates'] ?? [];
        }

        $savedLine = DriverServiceLine::updateOrCreate(
            [
                'user_id' => $user->id,
                'client_line_id' => $request->input('line_id'),
            ],
            $attributes
        );

        return response()->json([
            'result' => true,
            'message' => 'تم حفظ البيانات بنجاح',
            'data' => $this->formatLine($savedLine),
        ], 201);
    }

    public function deleteServiceLine(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'line_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        DriverServiceLine::where('user_id', $user->id)
            ->where('client_line_id', $request->input('line_id'))
            ->delete();

        return response()->json(['result' => true, 'message' => 'تم الحذف بنجاح']);
    }

    // Driver-facing: powers the grace-period banner and the document-lock
    // screen. Returned on its own (rather than only inside login/refresh
    // responses) so the app can re-check it after every document upload and
    // at any later app-resume without forcing a full re-login.
    public function verificationStatus(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $driver = Driver::where('user_id', $user->id)->first();

        return response()->json([
            'result' => true,
            'data' => $driver?->verificationStatus() ?? [
                'approval_status' => null,
                'grace_period_ends_at' => null,
                'grace_days_remaining' => 0,
                'documents_uploaded' => array_fill_keys(Driver::REQUIRED_VERIFICATION_DOCUMENTS, false),
                'is_locked' => false,
            ],
        ]);
    }

    // Driver-facing: self-service upload for one of the 3 required
    // verification documents (id_card/license/selfie). Mirrors
    // DriverManagementService::uploadDocument's admin-side file handling,
    // but is reachable by the driver themselves — re-uploading a type
    // replaces the previous pending/rejected row rather than piling up
    // duplicates, since only the latest submission should count towards
    // Driver::hasAllRequiredDocuments().
    public function uploadVerificationDocument(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:' . implode(',', Driver::REQUIRED_VERIFICATION_DOCUMENTS),
            'file' => 'required|file|mimetypes:image/jpeg,image/png,image/jpg,image/webp,image/heic,image/heif|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver record not found'], 404);
        }

        $documentType = $request->input('document_type');
        $file = $request->file('file');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('driver_documents', $file, $fileName);

        $existing = DriverDocument::where('driver_id', $driver->id)
            ->where('document_type', $documentType)
            ->first();
        if ($existing) {
            Storage::disk('public')->delete($existing->file_path);
            $existing->delete();
        }

        DriverDocument::create([
            'driver_id' => $driver->id,
            'document_type' => $documentType,
            'file_path' => 'driver_documents/' . $fileName,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'تم رفع المستند بنجاح',
            'data' => $driver->fresh()->verificationStatus(),
        ], 201);
    }

    public function uploadGalleryImage(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'category' => 'required|string|in:' . implode(',', self::CATEGORIES),
            'image' => 'required|file|mimetypes:image/jpeg,image/png,image/jpg,image/webp,image/heic,image/heif|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $image = $request->file('image');
        $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        Storage::disk('public')->putFileAs('driver_gallery', $image, $imageName);

        $maxSortOrder = DriverGalleryImage::where('user_id', $user->id)
            ->where('category', $request->input('category'))
            ->max('sort_order');

        $galleryImage = DriverGalleryImage::create([
            'user_id' => $user->id,
            'category' => $request->input('category'),
            'path' => 'driver_gallery/' . $imageName,
            'sort_order' => ($maxSortOrder ?? -1) + 1,
        ]);

        return response()->json([
            'result' => true,
            'message' => 'تم رفع الصورة بنجاح',
            'data' => [
                'id' => $galleryImage->id,
                'path' => $galleryImage->path,
                'sort_order' => $galleryImage->sort_order,
            ],
        ], 201);
    }

    public function deleteGalleryImage($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $image = DriverGalleryImage::where('user_id', $user->id)->where('id', $id)->first();
        if (!$image) {
            return response()->json(['result' => false, 'message' => 'Image not found'], 404);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['result' => true, 'message' => 'تم حذف الصورة بنجاح']);
    }

    public function reorderGallery(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        foreach ($request->input('ids') as $index => $imageId) {
            DriverGalleryImage::where('user_id', $user->id)
                ->where('id', $imageId)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['result' => true, 'message' => 'تم تحديث الترتيب بنجاح']);
    }
}
