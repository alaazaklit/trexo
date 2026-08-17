<?php

namespace App\Traits;

use App\Models\DriverIntercityRouteOverride;
use App\Models\IntercityRoute;
use App\Models\PricingZone;
use App\Services\Pricing\FareCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Models\Setting;

/**
 * Shared driver-matching logic used by both order and reservation driver
 * listing endpoints, so both flows match against the same pool of currently
 * online/available drivers using identical route/pricing math.
 */
trait MatchesDriverSchedules
{
    protected function isWithinTrackingWindow($startDateTime, $endDateTime, float $preWindowHours = 2.0): bool
    {
        $now = Carbon::now();
        $trackableFrom = Carbon::parse($startDateTime)->subHours($preWindowHours);
        $trackableUntil = Carbon::parse($endDateTime);

        return $now->betweenIncluded($trackableFrom, $trackableUntil);
    }

    protected function findMatchingDrivers(
        array $passengerRoute,
        array $pickup,
        array $destination,
        float $passengerDistanceKm,
        int $orderType,
        bool $isReservation = false,
        ?string $pickupCity = null,
        ?string $pickupRegion = null,
        ?string $destinationCity = null,
        ?string $destinationRegion = null,
        ?string $genderFilter = null
    ): array {
        // Resolved once per request (not per driver) — the pickup location
        // doesn't change per candidate, only which driver-specific override
        // (if any) wins over it below.
        $zone = $this->findPricingZone($pickupCity, $pickupRegion);

        // A well-known long trip (e.g. Beirut <-> Sidon) gets an admin-set
        // fixed fare instead of the linear base_fare + per-km calculation,
        // which tends to overshoot a competitively-priced route the further
        // it goes. Only replaces that one component — detour surcharge and
        // the reservation round-trip multiplier still apply on top below.
        $destinationZone = $this->findPricingZone($destinationCity, $destinationRegion);
        $intercityRoute = $this->findIntercityRoute($zone, $destinationZone);

        // Cross-zone/intercity per-km guardrail: only a genuine crossing
        // between two *resolved, different* zones triggers it — same-zone
        // trips, and trips where either side's zone couldn't be resolved at
        // all, keep using the existing pickup-zone-only rate unchanged (see
        // FareCalculator::effectivePerKmRate). This never overrides an
        // intercity fixed fare above, which still short-circuits the whole
        // distance formula first when configured.
        $crossesZones = $zone !== null && $destinationZone !== null && $zone->id !== $destinationZone->id;

        // Every quoted price is rounded to the nearest 20,000 LBP note
        // before being shown to the seller (this app is cash-to-driver) —
        // resolved once per request, same rate DriverProfileController's
        // Test Price simulator and the app's exchange-rate endpoint use.
        $lbpPerUsd = $this->getSettingFloat('pricing.exchange_rate_lbp_usd', 89500.0);

        // A driver can nudge the fare for a specific route they serve often
        // (see getIntercityFareBounds) — batched in one query up front
        // rather than per-candidate, keyed by user_id to match how every
        // other driver lookup in this method identifies a driver.
        $intercityOverridesByUserId = $intercityRoute !== null
            ? DriverIntercityRouteOverride::where('intercity_route_id', $intercityRoute->id)
                ->get()
                ->keyBy('user_id')
            : collect();
        // Eligibility is "driver currently online/available" — NOT tied to
        // having a pre-registered `schedules` row. A registered route
        // (schedules.order_id = 0, from the driver's Work Areas / Schedule
        // screens) is optional: when present it's left-joined in below
        // purely to enable the on-route/shared-ride bonus pricing; its
        // absence must not exclude an otherwise-online driver.
        //
        // `users.is_available` alone is the source of truth here: the real
        // app's updateAvailability toggle only ever writes this column, and
        // the simulator's DriverSimulatorService::updateDriver() keeps it in
        // lockstep with `drivers.is_online`/status on every simulator change
        // too. ORing in `drivers.is_online` on top used to let a stale
        // simulator flag override a driver who had since gone offline for
        // real (is_available=0 but is_online left at its old true value),
        // keeping them visible to sellers after they'd gone offline.
        //
        // This raw query builder bypasses Eloquent's SoftDeletes scope, so
        // a soft-deleted driver account left with is_available=1 would
        // otherwise keep surfacing here indefinitely — deleted_at must be
        // checked explicitly.
        //
        // A driver who hasn't cleared document/vehicle approval yet
        // (drivers.approval_status = 'pending'/'rejected') must not be
        // selectable even if is_available got flipped true, matching the
        // same approval gate already enforced in OrderOpsService and
        // ReservationOpsService's own candidate-driver queries.
        $driverRows = DB::table('users')
            ->leftJoin('drivers', 'drivers.user_id', '=', 'users.id')
            ->where('users.type', 'driver')
            ->where('users.is_available', true)
            ->whereNull('users.deleted_at')
            ->when($genderFilter !== null, function ($q) use ($genderFilter) {
                $q->where('users.gender', $genderFilter);
            })
            ->where(function ($q) {
                $q->whereNull('drivers.approval_status')->orWhere('drivers.approval_status', 'approved');
            })
            // A driver declares which services they accept from their own
            // Pricing screen (offers_taxi/offers_delivery on `drivers`,
            // default true). NULL is treated the same as true so a driver
            // with no `drivers` row yet, or one created before these columns
            // existed, keeps matching exactly as they did before this filter
            // existed — same backward-compat pattern as approval_status
            // just above.
            ->where(function ($q) use ($orderType) {
                $offersColumn = $orderType === 0 ? 'offers_taxi' : 'offers_delivery';
                $q->whereNull("drivers.$offersColumn")->orWhere("drivers.$offersColumn", true);
            })
            // A lapsed paid subscription does NOT exclude a driver — they
            // fall back to Basic (never expires) automatically, matching
            // SubscriptionService::currentSubscriptionFor exactly. This
            // only excludes the case of a driver with zero subscription
            // history at all, which shouldn't happen post-Driver::booted()
            // backfill, but is checked defensively rather than assumed.
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('driver_subscriptions')
                    ->whereColumn('driver_subscriptions.driver_id', 'drivers.id')
                    ->where('driver_subscriptions.payment_status', 'approved')
                    ->where(function ($q) {
                        $q->whereNull('driver_subscriptions.end_date')
                            ->orWhereDate('driver_subscriptions.end_date', '>=', now()->toDateString());
                    });
            })
            // This app is cash-to-driver — a driver who lets unpaid
            // commission pile up past the configured limit gets excluded
            // from new work entirely (unlike a lapsed subscription, which
            // only softens to Basic) until they settle it via a
            // CommissionPayment, since every extra trip just grows a debt
            // they may never pay back.
            ->leftJoin('wallets', 'wallets.driver_id', '=', 'drivers.id')
            ->where(function ($q) {
                $q->whereNull('wallets.commission_owed')
                    ->orWhere('wallets.commission_owed', '<=', $this->getSettingFloat('wallet.debt_limit', 50.0));
            })
            ->leftJoin('schedules', function ($join) {
                $join->on('schedules.user_id', '=', 'users.id')
                    ->where('schedules.order_id', 0);
            })
            ->leftJoin('addresses as start_address', 'start_address.id', '=', 'schedules.start_address')
            ->leftJoin('addresses as destination_address', 'destination_address.id', '=', 'schedules.destination_address')
            ->select(
                'users.id as driver_id',
                'users.name as driver_name',
                'users.avatar as driver_avatar',
                'users.phone as driver_phone',
                'users.gender as driver_gender',
                'users.created_at as driver_created_at',
                'users.fcm_token as fcm_token',
                'users.latitude as driver_live_lat',
                'users.longitude as driver_live_lng',
                'drivers.rating as driver_rating',
                'drivers.pricing_zone_id as pricing_zone_id',
                'drivers.base_fare_override as base_fare_override',
                'drivers.price_per_km_override as price_per_km_override',
                'drivers.detour_surcharge_override as detour_surcharge_override',
                'drivers.reservation_multiplier_override as reservation_multiplier_override',
                'schedules.time_from',
                'schedules.time_to',
                'schedules.route_points as route_points',
                'schedules.route_distance_km as route_distance_km',
                'start_address.latitude as start_lat',
                'start_address.longitude as start_lng',
                'destination_address.latitude as destination_lat',
                'destination_address.longitude as destination_lng'
            )
            ->get();

        $drivers = [];
        foreach ($driverRows as $driverRow) {
            // A driver who has explicitly chosen a working zone (the
            // dropdown on their own Pricing screen — `pricing_zone_id`)
            // means "I only work within this area": skip them entirely for
            // a trip that leaves it (e.g. a Sidon-zoned driver for a
            // Sidon->Beirut trip), unless the pickup/destination pair is a
            // recognized intercity route the platform has explicitly set up
            // touching their zone. A driver who never picked a zone at all
            // (null) stays fully unrestricted, unchanged from before this
            // check existed.
            if ($driverRow->pricing_zone_id !== null) {
                $withinOwnZone = $zone !== null && $zone->id === $driverRow->pricing_zone_id
                    && ($destinationZone === null || $destinationZone->id === $driverRow->pricing_zone_id);
                $intercityTouchesOwnZone = $intercityRoute !== null && (
                    ($zone !== null && $zone->id === $driverRow->pricing_zone_id)
                    || ($destinationZone !== null && $destinationZone->id === $driverRow->pricing_zone_id)
                );

                if (!$withinOwnZone && !$intercityTouchesOwnZone) {
                    continue;
                }
            }

            // A driver can opt out of a specific long-distance route (per
            // order kind, independently) even though their own zone
            // touches it — e.g. they don't want the full Saida<->Beirut
            // trip despite being zoned in Saida.
            $routeOverride = $intercityRoute !== null
                ? $intercityOverridesByUserId->get((int) $driverRow->driver_id)
                : null;
            if ($routeOverride !== null) {
                $isActiveForOrderType = $orderType === 0
                    ? $routeOverride->is_active_taxi
                    : $routeOverride->is_active_delivery;
                if ($isActiveForOrderType === false) {
                    continue;
                }
            } elseif ($intercityRoute !== null) {
                // No override row at all — this driver has never opened
                // the Long-Distance Routes screen for this specific route.
                // Grandfathered drivers (their account already existed when
                // this opt-in requirement was introduced) stay eligible,
                // unchanged from before this feature existed. A driver
                // created from that cutoff onward must explicitly enable
                // each route before showing up for it — new accounts start
                // opted out, not in.
                $cutoff = $this->getLongDistanceOptInCutoff();
                if ($cutoff !== null && $driverRow->driver_created_at !== null
                    && $driverRow->driver_created_at >= $cutoff) {
                    continue;
                }
            }

            $driverRoute = $this->decodeRoutePoints($driverRow->route_points ?? null);
            if (empty($driverRoute) && $driverRow->start_lat !== null && $driverRow->destination_lat !== null) {
                $driverRoute = [
                    ['lat' => (float) $driverRow->start_lat, 'lng' => (float) $driverRow->start_lng],
                    ['lat' => (float) $driverRow->destination_lat, 'lng' => (float) $driverRow->destination_lng],
                ];
            }

            $match = $this->matchPassengerRouteToDriverRoute(
                $passengerRoute,
                $driverRoute,
                $pickup,
                $destination
            );

            // Driver-specific overrides win over the pricing-zone/global
            // rate when set (e.g. a premium vehicle priced above the
            // standard rate); null means "use the zone/global rate" for
            // that component. The shared-ride multiplier itself stays
            // global-only — it's not one of the overridable fields.
            $baseFare = $driverRow->base_fare_override !== null
                ? (float) $driverRow->base_fare_override
                : $this->getBaseFare($orderType, $zone);
            $normalPricePerKm = $driverRow->price_per_km_override !== null
                ? (float) $driverRow->price_per_km_override
                : FareCalculator::effectivePerKmRate(
                    $this->getNormalPricePerKm($orderType, $zone),
                    $this->getNormalPricePerKm($orderType, $destinationZone),
                    $this->getNormalPricePerKm($orderType, null),
                    $crossesZones
                );
            $sharedRidePricePerKm = $normalPricePerKm * $this->getSettingFloat('fare.shared_multiplier', 0.70);
            $detourSurchargePerKm = $driverRow->detour_surcharge_override !== null
                ? (float) $driverRow->detour_surcharge_override
                : $this->getDetourSurchargePerKm();

            // Reservations bill for the round trip (drop-off, then pickup
            // again later) — orders never do, regardless of any override.
            $reservationMultiplier = 1.0;
            if ($isReservation) {
                $reservationMultiplier = $driverRow->reservation_multiplier_override !== null
                    ? (float) $driverRow->reservation_multiplier_override
                    : $this->getReservationMultiplier();
            }

            $intercityFixedFare = null;
            if ($intercityRoute !== null) {
                // $routeOverride already resolved above, for the eligibility check.
                $overrideFare = $routeOverride === null
                    ? null
                    : ($orderType === 0
                        ? $routeOverride->fixed_fare_taxi_override
                        : $routeOverride->fixed_fare_delivery_override);
                $intercityFixedFare = $overrideFare !== null
                    ? $overrideFare
                    : ($orderType === 0 ? $intercityRoute->fixed_fare_taxi : $intercityRoute->fixed_fare_delivery);
            }

            $price = FareCalculator::calculate(
                $baseFare,
                $normalPricePerKm,
                $sharedRidePricePerKm,
                $detourSurchargePerKm,
                $passengerDistanceKm,
                $match['on_route'],
                $match['detour_km'],
                $reservationMultiplier,
                $intercityFixedFare !== null ? (float) $intercityFixedFare : null
            );
            $price = FareCalculator::roundPriceUsdToNearestLbpNote($price, $lbpPerUsd);

            $drivers[] = [
                'driver_id' => (int) $driverRow->driver_id,
                'driver_name' => $driverRow->driver_name,
                'driver_avatar' => $driverRow->driver_avatar,
                'driver_phone' => $driverRow->driver_phone,
                'driver_gender' => $driverRow->driver_gender,
                'fcm_token' => $driverRow->fcm_token,
                'driver_rating' => $this->normalizeRating($driverRow->driver_rating),
                'price' => $price,
                'estimated_pickup_minutes' => $this->estimateRealPickupMinutes(
                    $driverRow,
                    $pickup,
                    $match['detour_km'],
                    $driverRow->time_from ?? null,
                    $driverRow->time_to ?? null
                ),
                'on_route' => $match['on_route'],
                'detour_km' => round($match['detour_km'], 3),
                'pickup_deviation_km' => $match['pickup_deviation_km'] === null
                    ? null
                    : round($match['pickup_deviation_km'], 3),
                'destination_deviation_km' => $match['destination_deviation_km'] === null
                    ? null
                    : round($match['destination_deviation_km'], 3),
                'route_points' => $driverRoute,
                'time_from' => $driverRow->time_from,
                'time_to' => $driverRow->time_to,
            ];
        }

        usort($drivers, function ($a, $b) {
            if ($a['on_route'] !== $b['on_route']) {
                return $a['on_route'] ? -1 : 1;
            }

            if (abs($a['price'] - $b['price']) > 0.001) {
                return $a['price'] <=> $b['price'];
            }

            if (abs($a['driver_rating'] - $b['driver_rating']) > 0.001) {
                return $b['driver_rating'] <=> $a['driver_rating'];
            }

            return $a['estimated_pickup_minutes'] <=> $b['estimated_pickup_minutes'];
        });

        return $drivers;
    }

    protected function decodeRoutePoints($routePoints): array
    {
        if (empty($routePoints)) {
            return [];
        }

        if (is_string($routePoints)) {
            $decoded = json_decode($routePoints, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $routePoints = $decoded;
            } else {
                return [];
            }
        }

        if (!is_array($routePoints)) {
            return [];
        }

        $points = [];
        foreach ($routePoints as $point) {
            if (is_array($point) && isset($point['lat'], $point['lng'])) {
                $points[] = [
                    'lat' => (float) $point['lat'],
                    'lng' => (float) $point['lng'],
                ];
            } elseif (is_array($point) && isset($point[0], $point[1])) {
                $points[] = [
                    'lat' => (float) $point[0],
                    'lng' => (float) $point[1],
                ];
            }
        }

        return $points;
    }

    protected function calculatePolylineDistance(array $points): float
    {
        if (count($points) < 2) {
            return 0.0;
        }

        $distance = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $distance += $this->haversineDistance(
                $points[$i - 1]['lat'],
                $points[$i - 1]['lng'],
                $points[$i]['lat'],
                $points[$i]['lng']
            );
        }

        return $distance;
    }

    protected function matchPassengerRouteToDriverRoute(array $passengerRoute, array $driverRoute, array $pickup, array $destination): array
    {
        // null (not INF) — a driver with no registered route has no
        // deviation to report, and INF breaks response()->json() for the
        // *entire* driver list, not just this one entry.
        $default = [
            'on_route' => false,
            'pickup_deviation_km' => null,
            'destination_deviation_km' => null,
            'detour_km' => 0.0,
        ];

        if (count($driverRoute) < 2 || count($passengerRoute) < 2) {
            return $default;
        }

        $pickupProjection = $this->closestProjection($driverRoute, $pickup);
        $destinationProjection = $this->closestProjection($driverRoute, $destination);
        $thresholdKm = $this->getRouteDeviationThresholdKm();

        $onRoute = $pickupProjection['distance_km'] <= $thresholdKm
            && $destinationProjection['distance_km'] <= $thresholdKm
            && $pickupProjection['route_position_km'] <= $destinationProjection['route_position_km'];

        // Uncapped, this scales with how far the matched driver's
        // *registered route* happens to be from the trip — not a real
        // detour at all for a driver whose Work Area is in an unrelated
        // city, which used to turn an ordinary few-dollar intra-city fare
        // into $20-$30+ purely from this term. Capping it keeps the
        // surcharge meaningful (a genuine slight detour) without letting an
        // irrelevant driver's distance blow up the price.
        $detourKm = min(
            $this->getMaxDetourKm(),
            max(0.0, ($pickupProjection['distance_km'] + $destinationProjection['distance_km']) - $thresholdKm)
        );

        return [
            'on_route' => $onRoute,
            'pickup_deviation_km' => $pickupProjection['distance_km'],
            'destination_deviation_km' => $destinationProjection['distance_km'],
            'detour_km' => $detourKm,
        ];
    }

    protected function closestProjection(array $routePoints, array $point): array
    {
        $bestDistance = INF;
        $bestRoutePosition = 0.0;
        $travelled = 0.0;

        for ($i = 0; $i < count($routePoints) - 1; $i++) {
            $start = $routePoints[$i];
            $end = $routePoints[$i + 1];
            $segmentLength = $this->haversineDistance($start['lat'], $start['lng'], $end['lat'], $end['lng']);
            $projection = $this->projectPointToSegment($point, $start, $end);

            if ($projection['distance_km'] < $bestDistance) {
                $bestDistance = $projection['distance_km'];
                $bestRoutePosition = $travelled + ($segmentLength * $projection['t']);
            }

            $travelled += $segmentLength;
        }

        return [
            'distance_km' => $bestDistance,
            'route_position_km' => $bestRoutePosition,
        ];
    }

    protected function projectPointToSegment(array $point, array $start, array $end): array
    {
        $earthRadiusKm = 6371.0;
        $latScale = ($earthRadiusKm * pi()) / 180.0;
        $lngScale = cos((($start['lat'] + $end['lat'] + $point['lat']) / 3.0) * pi() / 180.0) * $latScale;

        $ax = $start['lng'] * $lngScale;
        $ay = $start['lat'] * $latScale;
        $bx = $end['lng'] * $lngScale;
        $by = $end['lat'] * $latScale;
        $px = $point['lng'] * $lngScale;
        $py = $point['lat'] * $latScale;

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = $dx * $dx + $dy * $dy;

        if ($lengthSquared == 0.0) {
            return [
                'distance_km' => $this->haversineDistance($point['lat'], $point['lng'], $start['lat'], $start['lng']),
                't' => 0.0,
            ];
        }

        $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / $lengthSquared;
        $t = max(0.0, min(1.0, $t));

        $closestX = $ax + ($t * $dx);
        $closestY = $ay + ($t * $dy);
        $distanceKm = sqrt(pow($px - $closestX, 2) + pow($py - $closestY, 2));

        return [
            'distance_km' => (float) $distanceKm,
            't' => (float) $t,
        ];
    }

    protected function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth radius in kilometers

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlon / 2) * sin($dlon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Distance from the driver's last known live GPS position to the
     * passenger's pickup point — this is what actually determines pickup
     * ETA. A driver with no live position yet (never sent a location
     * heartbeat) has no way to estimate this, so null signals the caller
     * to fall back to a flat default rather than guessing.
     */
    protected function driverToPickupKm($driverRow, array $pickup): ?float
    {
        if ($driverRow->driver_live_lat === null || $driverRow->driver_live_lng === null) {
            return null;
        }

        return $this->haversineDistance(
            (float) $driverRow->driver_live_lat,
            (float) $driverRow->driver_live_lng,
            $pickup['lat'],
            $pickup['lng']
        );
    }

    protected function estimatePickupMinutes(?float $driverToPickupKm, float $detourKm, $timeFrom = null, $timeTo = null): int
    {
        // No live driver position to measure from — a flat placeholder
        // beats a distance-based guess built from unrelated data (the
        // previous bug here used the passenger's own trip distance).
        if ($driverToPickupKm === null) {
            return 10;
        }

        $minutes = (int) round(($driverToPickupKm * 2.0) + ($detourKm * 3.0) + 5);
        return max(3, $minutes);
    }

    /**
     * Real driving-time pickup ETA via Google's Distance Matrix API (roads,
     * not a straight line) — falls back to the straight-line estimate
     * whenever the driver has no live position yet or the API call fails,
     * so a Google outage degrades the number rather than breaking the
     * whole driver list.
     */
    protected function estimateRealPickupMinutes($driverRow, array $pickup, float $detourKm, $timeFrom = null, $timeTo = null): int
    {
        $driverToPickupKm = $this->driverToPickupKm($driverRow, $pickup);
        if ($driverToPickupKm === null) {
            return $this->estimatePickupMinutes(null, $detourKm, $timeFrom, $timeTo);
        }

        $drivingMinutes = $this->drivingDurationMinutes(
            (float) $driverRow->driver_live_lat,
            (float) $driverRow->driver_live_lng,
            $pickup['lat'],
            $pickup['lng']
        );

        if ($drivingMinutes === null) {
            return $this->estimatePickupMinutes($driverToPickupKm, $detourKm, $timeFrom, $timeTo);
        }

        // The detour bonus/penalty (off-route deviation) has no road-network
        // equivalent from a single point-to-point Distance Matrix call, so
        // it's still added as a flat per-km adjustment on top of the real
        // driving time.
        $minutes = (int) round($drivingMinutes + ($detourKm * 3.0));
        return max(1, $minutes);
    }

    /**
     * Actual road driving time in minutes between two points, via Google's
     * Distance Matrix API. Null on any failure (missing key, timeout,
     * non-OK status) — callers must fall back to the straight-line
     * estimate rather than treat null as "0 minutes".
     */
    protected function drivingDurationMinutes(float $originLat, float $originLng, float $destLat, float $destLng): ?int
    {
        $apiKey = config('services.google_maps.key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(3)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $apiKey,
                    // Field mask is required by the Routes API — an unmasked
                    // request is rejected outright, not just trimmed down.
                    'X-Goog-FieldMask' => 'routes.duration',
                ])
                ->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                    'origin' => ['location' => ['latLng' => ['latitude' => $originLat, 'longitude' => $originLng]]],
                    'destination' => ['location' => ['latLng' => ['latitude' => $destLat, 'longitude' => $destLng]]],
                    'travelMode' => 'DRIVE',
                ]);

            if (!$response->successful()) {
                Log::warning('Routes API pickup ETA lookup failed', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            // Returned as e.g. "823s", not a plain number like the legacy
            // Distance Matrix API's duration.value.
            $duration = $response->json('routes.0.duration');
            if (!is_string($duration) || !str_ends_with($duration, 's')) {
                return null;
            }

            $seconds = (float) rtrim($duration, 's');

            return (int) round($seconds / 60);
        } catch (\Throwable $e) {
            Log::warning('Routes API pickup ETA lookup failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Authoritative trip distance (pickup -> destination) via Google Routes
     * API. The caller's own route_distance_km is whatever the mobile app's
     * client-side calculation produced and is trusted with zero server-side
     * validation elsewhere — a client bug or a bad GPS fix there directly
     * inflates/deflates the fare. Falls back to that same value (or the
     * caller's polyline-sum) whenever the API call fails, so a Google outage
     * degrades to the previous behavior rather than breaking pricing.
     */
    protected function resolveTripDistanceKm(array $pickup, array $destination, float $fallbackKm): float
    {
        $apiKey = config('services.google_maps.key');
        if (empty($apiKey)) {
            return $fallbackKm;
        }

        try {
            $response = Http::timeout(3)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'routes.distanceMeters',
                ])
                ->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                    'origin' => ['location' => ['latLng' => ['latitude' => $pickup['lat'], 'longitude' => $pickup['lng']]]],
                    'destination' => ['location' => ['latLng' => ['latitude' => $destination['lat'], 'longitude' => $destination['lng']]]],
                    'travelMode' => 'DRIVE',
                ]);

            if (!$response->successful()) {
                Log::warning('Routes API trip distance lookup failed', ['status' => $response->status(), 'body' => $response->body()]);
                return $fallbackKm;
            }

            $meters = $response->json('routes.0.distanceMeters');
            if (!is_numeric($meters) || $meters <= 0) {
                return $fallbackKm;
            }

            return round($meters / 1000, 3);
        } catch (\Throwable $e) {
            Log::warning('Routes API trip distance lookup failed', ['error' => $e->getMessage()]);
            return $fallbackKm;
        }
    }

    /**
     * City/administrative-region text for a coordinate, via Google's
     * Geocoding API — the same signal a real order/reservation already
     * carries (Order::start_city/start_region etc., geocoded client-side
     * when the address was picked and stored on the `addresses` table).
     * Needed only by DriverProfileController::testPrice(), which receives
     * raw coordinates with no such stored text, so it can zone-match/
     * intercity-match a pickup/destination pair the exact same way
     * findMatchingDrivers() does. Cached briefly per ~11m coordinate
     * bucket, same reasoning/precision as MapProxyController::reverseGeocode.
     */
    protected function resolveCityRegion(float $lat, float $lng): array
    {
        $apiKey = config('services.google_maps.key');
        if (empty($apiKey)) {
            return ['city' => null, 'region' => null];
        }

        $cacheKey = 'test_price_geocode:' . md5(round($lat, 4) . ',' . round($lng, 4));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $apiKey,
            ]);

            if (!$response->successful() || $response->json('status') !== 'OK') {
                return ['city' => null, 'region' => null];
            }

            $city = null;
            $region = null;
            // Google fragments address components across several result
            // entries for the same point — scan all of them, not just the
            // first, same reasoning as address.dart's _findFirstAcrossResults.
            foreach ($response->json('results', []) as $result) {
                foreach ($result['address_components'] ?? [] as $component) {
                    $types = $component['types'] ?? [];
                    if ($city === null && in_array('locality', $types, true)) {
                        $city = $component['long_name'] ?? null;
                    }
                    if ($region === null && in_array('administrative_area_level_1', $types, true)) {
                        $region = $component['long_name'] ?? null;
                    }
                }
                if ($city !== null && $region !== null) {
                    break;
                }
            }

            $resolved = ['city' => $city, 'region' => $region];
            Cache::put($cacheKey, $resolved, now()->addMinutes(30));

            return $resolved;
        } catch (\Throwable $e) {
            Log::warning('Reverse geocode for zone/intercity matching failed', ['error' => $e->getMessage()]);

            return ['city' => null, 'region' => null];
        }
    }

    protected function getBaseFare(int $orderType, ?PricingZone $zone = null): float
    {
        $key = $orderType === 0 ? 'fare.base_taxi' : 'fare.base_delivery';
        $global = $this->getSettingFloat($key, $orderType === 0 ? 2.50 : 3.00);

        if ($zone === null) {
            return $global;
        }

        $zoneValue = $orderType === 0 ? $zone->base_fare_taxi : $zone->base_fare_delivery;
        return $zoneValue !== null ? (float) $zoneValue : $global;
    }

    protected function getNormalPricePerKm(int $orderType, ?PricingZone $zone = null): float
    {
        $key = $orderType === 0 ? 'fare.per_km_taxi' : 'fare.per_km_delivery';
        $global = $this->getSettingFloat($key, $orderType === 0 ? 1.20 : 1.00);

        if ($zone === null) {
            return $global;
        }

        $zoneValue = $orderType === 0 ? $zone->per_km_taxi : $zone->per_km_delivery;
        return $zoneValue !== null ? (float) $zoneValue : $global;
    }

    // Highest-priority active zone whose keywords match this pickup
    // location's city/region, or null if none do (callers then fall back
    // to the global fare.* settings entirely, unchanged from before this
    // feature existed).
    protected function findPricingZone(?string $city, ?string $region): ?PricingZone
    {
        if (($city === null || $city === '') && ($region === null || $region === '')) {
            return null;
        }

        return PricingZone::where('is_active', true)
            ->orderByDesc('priority')
            ->get()
            ->first(fn (PricingZone $zone) => $zone->matchesLocation($city, $region));
    }

    // Matched in both directions (Beirut->Sidon and Sidon->Beirut are the
    // same registered route) — an admin only needs to register each
    // city-pair once. Returns null if either end didn't resolve to a zone
    // at all, or no route is registered between them.
    protected function findIntercityRoute(?PricingZone $fromZone, ?PricingZone $toZone): ?IntercityRoute
    {
        if ($fromZone === null || $toZone === null) {
            return null;
        }

        return IntercityRoute::where('is_active', true)
            ->where(function ($query) use ($fromZone, $toZone) {
                $query->where(function ($q) use ($fromZone, $toZone) {
                    $q->where('from_zone_id', $fromZone->id)->where('to_zone_id', $toZone->id);
                })->orWhere(function ($q) use ($fromZone, $toZone) {
                    $q->where('from_zone_id', $toZone->id)->where('to_zone_id', $fromZone->id);
                });
            })
            ->first();
    }

    protected function getDetourSurchargePerKm(): float
    {
        return $this->getSettingFloat('fare.detour_surcharge_per_km', 0.25);
    }

    protected function getMaxDetourKm(): float
    {
        return $this->getSettingFloat('fare.max_detour_km', 5.0);
    }

    protected function getReservationMultiplier(): float
    {
        return $this->getSettingFloat('fare.reservation_multiplier', 2.00);
    }

    protected function normalizeRating($rating): float
    {
        if ($rating === null || $rating === '') {
            return 0.0;
        }

        return (float) $rating;
    }

    protected function getSettingFloat(string $key, float $default): float
    {
        $value = Setting::where('key', $key)->value('value');
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) $value;
    }

    protected function getRouteDeviationThresholdKm(): float
    {
        return $this->getSettingFloat('fare.route_deviation_km', 0.75);
    }

    // Null (no cutoff enforced — every driver stays automatically
    // eligible, today's behavior) until explicitly set. Set once, at the
    // moment this opt-in requirement is introduced, to "now" — every
    // driver account created before that instant is grandfathered in;
    // every driver created from that instant onward must explicitly
    // enable each long-distance route themselves.
    protected function getLongDistanceOptInCutoff(): ?string
    {
        $value = Setting::where('key', 'driver.long_distance_opt_in_cutoff')->value('value');

        return $value !== null && $value !== '' ? $value : null;
    }

    // How far a driver's own price overrides are allowed to stray from the
    // default, as a percentage of it (admin-configurable, so a driver can
    // be allowed to charge a bit more/less without being able to set an
    // absurd price). Taxi and delivery have different base fare/per-km
    // defaults, but a single driver's override applies to whichever order
    // kind they take — so the allowed range envelopes both instead of
    // assuming one or the other.
    //
    // [$zone] is the driver's own *chosen working zone* (purely a display
    // preference — see the pricing_zone_id migration) — it makes the
    // "default" shown on the driver's own pricing screen relevant to where
    // they actually work, instead of always showing whichever number a
    // Beirut-centric global default happens to be. It has no bearing on
    // what a given ride actually gets charged — that's still resolved from
    // the real pickup location's zone every time in findMatchingDrivers().
    protected function getOverrideBounds(?PricingZone $zone = null): array
    {
        $percent = $this->getSettingFloat('fare.override_range_percent', 20.0) / 100;

        $baseFareTaxi = $this->getBaseFare(0, $zone);
        $baseFareDelivery = $this->getBaseFare(1, $zone);
        $perKmTaxi = $this->getNormalPricePerKm(0, $zone);
        $perKmDelivery = $this->getNormalPricePerKm(1, $zone);
        $detour = $this->getDetourSurchargePerKm();
        $reservationMultiplier = $this->getReservationMultiplier();

        $envelope = static function (float $a, float $b) use ($percent) {
            $low = min($a, $b);
            $high = max($a, $b);
            return [
                'min' => round($low * (1 - $percent), 2),
                'max' => round($high * (1 + $percent), 2),
            ];
        };

        return [
            'base_fare' => $envelope($baseFareTaxi, $baseFareDelivery) + [
                'default_taxi' => $baseFareTaxi,
                'default_delivery' => $baseFareDelivery,
            ],
            'price_per_km' => $envelope($perKmTaxi, $perKmDelivery) + [
                'default_taxi' => $perKmTaxi,
                'default_delivery' => $perKmDelivery,
            ],
            'detour_surcharge' => [
                'min' => round($detour * (1 - $percent), 2),
                'max' => round($detour * (1 + $percent), 2),
                'default' => $detour,
            ],
            'reservation_multiplier' => [
                'min' => round($reservationMultiplier * (1 - $percent), 2),
                'max' => round($reservationMultiplier * (1 + $percent), 2),
                'default' => $reservationMultiplier,
            ],
        ];
    }

    // How far a driver's own override for a specific intercity route is
    // allowed to stray from that route's admin-set fixed fare, for the
    // given order kind. Null bounds/default mean the admin hasn't set a
    // fare for this route+kind at all — nothing to bound an override
    // against, so callers should treat that as "no override allowed here".
    protected function getIntercityFareBounds(IntercityRoute $route, int $orderType): array
    {
        $percent = $this->getSettingFloat('fare.override_range_percent', 20.0) / 100;
        $base = $orderType === 0 ? $route->fixed_fare_taxi : $route->fixed_fare_delivery;

        if ($base === null) {
            return ['min' => null, 'max' => null, 'default' => null];
        }

        $base = (float) $base;
        return [
            'min' => round($base * (1 - $percent), 2),
            'max' => round($base * (1 + $percent), 2),
            'default' => $base,
        ];
    }
}
