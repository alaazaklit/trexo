<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Mapbox-backed equivalents of the Places/Geocoding calls
 * MapProxyController proxies to Google — used only when the
 * 'maps.places_provider' Setting is 'mapbox' (see MapProxyController's
 * placesProvider()/fetch*() dispatch), so a fresh install with that Setting
 * still at its default 'google' never touches any of this.
 *
 * Every method here returns data already reshaped into Google's own
 * response fields (address_components, formatted_address, geometry.location,
 * predictions[].place_id/description, etc.) — never Mapbox's raw GeoJSON
 * shape — because address.dart's address-composition logic (road/building/
 * neighborhood merging, the Lebanese "حارة" name-splitting handling, etc.)
 * is written entirely against Google's field names and reads them regardless
 * of which provider actually produced them. That mapping is inherently
 * lossy: Mapbox's data model doesn't have a direct equivalent for some of
 * what Google exposes (notably Nearby-Search's POI *sub-category* filtering
 * — tourist_attraction vs. establishment — which Mapbox's reverse-geocode
 * 'poi' type filter doesn't distinguish), and Lebanon's address coverage
 * differs between the two providers. Both known gaps are called out at the
 * call sites below, not silently papered over.
 */
class MapboxService
{
    private const BASE_URL = 'https://api.mapbox.com/geocoding/v5/mapbox.places';

    // "Repeat queries cost $0.00" per the caching requirement — 30 days,
    // far longer than any of the existing Google endpoints' TTLs (1 hour to
    // 30 days depending on endpoint), because this cache is the only thing
    // standing between a popular search and a re-billed Mapbox call.
    private const CACHE_TTL_DAYS = 30;

    private function token(): ?string
    {
        return config('services.mapbox.token');
    }

    /**
     * Forward/text search — the Mapbox analog of Google's Autocomplete.
     * Returns `['predictions' => [['description' => ..., 'place_id' => ...], ...]]`,
     * exactly the shape AddressAutocompleteField already reads off Google's
     * response. Each matched feature is also cached individually (see
     * cacheFeature()) so a later retrievePlace() call for its place_id can
     * resolve without another billed Mapbox request.
     */
    public function searchPlaces(
        string $query,
        ?string $country = null,
        ?float $biasLat = null,
        ?float $biasLng = null,
        string $language = 'en',
        ?string $types = null
    ): array {
        $token = $this->token();
        if (empty($token) || trim($query) === '') {
            return ['predictions' => []];
        }

        $hasBias = $biasLat !== null && $biasLng !== null;
        $cacheKeyParts = [
            mb_strtolower(trim($query)),
            $country ?? '',
            $language,
            $types ?? '',
        ];
        if ($hasBias) {
            // Same ~1km bucketing Google's own autocomplete proxy uses —
            // keeps the bias from fragmenting the cache on every small map
            // nudge while still meaningful at this search radius.
            $cacheKeyParts[] = round($biasLat, 2) . ',' . round($biasLng, 2);
        }
        $cacheKey = 'mapbox_search:' . md5(implode('|', $cacheKeyParts));

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use (
            $token,
            $query,
            $country,
            $biasLat,
            $biasLng,
            $hasBias,
            $language,
            $types
        ) {
            $params = [
                'access_token' => $token,
                'autocomplete' => 'true',
                'language' => $language,
                'limit' => 5,
            ];
            if ($country) {
                $params['country'] = $country;
            }
            if ($hasBias) {
                $params['proximity'] = "{$biasLng},{$biasLat}";
            }
            if ($types) {
                $params['types'] = $types;
            }

            $response = Http::timeout(8)->get(
                self::BASE_URL . '/' . rawurlencode($query) . '.json',
                $params
            );

            if (!$response->successful()) {
                throw new \RuntimeException("Mapbox search failed: HTTP {$response->status()}");
            }

            $features = $response->json('features') ?? [];
            $predictions = [];

            foreach ($features as $feature) {
                $placeId = $this->cacheFeature($feature);
                $description = $feature['place_name'] ?? $feature['text'] ?? '';
                if ($description === '') {
                    continue;
                }
                $predictions[] = ['description' => $description, 'place_id' => $placeId];
            }

            return ['predictions' => $predictions];
        });
    }

    /**
     * Resolves a place_id previously handed out by searchPlaces() (or
     * opportunistically cached by reverseGeocode()/placesNearby() below)
     * back into coordinates/name — the Mapbox analog of Google's Place
     * Details. Unlike Google, Mapbox's forward search already returns full
     * coordinates, so this never makes a second billed request: it's a
     * pure cache lookup, and returns null if that cache entry has expired
     * (mirrors a Google Details call landing after its session expired —
     * MapProxyController::placeDetails already treats a failed lookup as
     * "no coordinates resolved" rather than an error).
     */
    public function retrievePlace(string $placeId): ?array
    {
        $feature = Cache::get($this->featureCacheKey($placeId));
        if ($feature === null) {
            return null;
        }

        [$lat, $lng] = $this->coordinatesOf($feature);
        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'result' => [
                'place_id' => $placeId,
                'name' => $feature['text'] ?? null,
                'formatted_address' => $feature['place_name'] ?? null,
                'geometry' => ['location' => ['lat' => $lat, 'lng' => $lng]],
            ],
        ];
    }

    /**
     * Reverse geocode — converts a GPS point into address_components the
     * same way Google's Geocoding API does. Returns
     * `['status' => 'OK'|'ZERO_RESULTS', 'results' => [...]]`.
     *
     * Google returns one top-level result per precision level (building,
     * road, neighborhood, city, ...); Mapbox instead returns one feature per
     * level but each feature already carries its own full ancestor chain in
     * `context`, so results[0] alone typically carries everything
     * address.dart's `_findFirstAcrossResults()` scans for anyway — the
     * remaining features are still included for parity, in the same
     * most-specific-first order Mapbox returns them.
     */
    public function reverseGeocode(float $lat, float $lng, string $language = 'en'): array
    {
        $token = $this->token();
        if (empty($token)) {
            return ['status' => 'REQUEST_DENIED', 'results' => []];
        }

        // Same ~11m bucketing (4 decimal places) the Google reverse-geocode
        // proxy already uses for its own (much shorter) cache — most pin
        // nudges land in the same bucket, so they hit this cache too.
        $latBucket = round($lat, 4);
        $lngBucket = round($lng, 4);
        $cacheKey = 'mapbox_reverse_geocode:' . md5("{$latBucket},{$lngBucket}|{$language}");

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use (
            $token,
            $lat,
            $lng,
            $language
        ) {
            $response = Http::timeout(8)->get(
                self::BASE_URL . "/{$lng},{$lat}.json",
                ['access_token' => $token, 'language' => $language]
            );

            if (!$response->successful()) {
                throw new \RuntimeException("Mapbox reverse geocode failed: HTTP {$response->status()}");
            }

            $features = $response->json('features') ?? [];
            if (empty($features)) {
                return ['status' => 'ZERO_RESULTS', 'results' => []];
            }

            $results = [];
            foreach ($features as $feature) {
                $this->cacheFeature($feature);
                $results[] = [
                    'formatted_address' => $feature['place_name'] ?? '',
                    'address_components' => $this->featureToAddressComponents($feature),
                ];
            }

            return ['status' => 'OK', 'results' => $results];
        });
    }

    /**
     * Nearest-named-place lookup — the Mapbox analog of Google's Places
     * Nearby Search, used by address.dart purely as a display enrichment
     * (finding a building/business name a plain Geocoding result wouldn't
     * have). Implemented as reverse geocoding filtered to `types=poi`.
     *
     * Known gap: Mapbox's reverse geocode only filters down to the single
     * broad 'poi' type — it has no equivalent of Google's finer
     * `type=tourist_attraction` vs `type=establishment` split, so the
     * three-tier priority search address.dart runs (prominent nearby →
     * nearest establishment → tourist attraction) collapses to one
     * "nearest POI" query here regardless of which `type`/`rankby` the
     * caller asked for. Distance-based ranking is preserved (Mapbox's
     * reverse geocode is always nearest-first), just not the category
     * distinction.
     */
    public function placesNearby(float $lat, float $lng, string $language = 'en'): array
    {
        $token = $this->token();
        if (empty($token)) {
            return ['status' => 'REQUEST_DENIED', 'results' => []];
        }

        $latBucket = round($lat, 4);
        $lngBucket = round($lng, 4);
        $cacheKey = 'mapbox_places_nearby:' . md5("{$latBucket},{$lngBucket}|{$language}");

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use (
            $token,
            $lat,
            $lng,
            $language
        ) {
            $response = Http::timeout(8)->get(
                self::BASE_URL . "/{$lng},{$lat}.json",
                ['access_token' => $token, 'language' => $language, 'types' => 'poi', 'limit' => 5]
            );

            if (!$response->successful()) {
                throw new \RuntimeException("Mapbox places nearby failed: HTTP {$response->status()}");
            }

            $features = $response->json('features') ?? [];
            if (empty($features)) {
                return ['status' => 'ZERO_RESULTS', 'results' => []];
            }

            $results = [];
            foreach ($features as $feature) {
                $this->cacheFeature($feature);
                [$flat, $flng] = $this->coordinatesOf($feature);
                $results[] = [
                    'name' => $feature['text'] ?? '',
                    'types' => ['point_of_interest', 'establishment'],
                    'geometry' => ['location' => ['lat' => $flat, 'lng' => $flng]],
                ];
            }

            return ['status' => 'OK', 'results' => $results];
        });
    }

    /**
     * Driving distance/duration between two points via Mapbox's Directions
     * API — used by DriverProfileController::testPrice() (the driver-facing
     * Check Price screen), which must never call Google's Routes API the
     * way real order/reservation pricing does (see
     * MatchesDriverSchedules::resolveTripDistanceKm()/drivingDurationMinutes()).
     * Returns null on any failure so the caller can fall back to a
     * straight-line estimate instead — deliberately no cross-provider
     * fallback to Google here, unlike the rest of this class's methods,
     * because Check Price is required to generate zero Google requests even
     * when Mapbox itself is unavailable.
     */
    public function directions(float $originLat, float $originLng, float $destLat, float $destLng, string $mode = 'driving'): ?array
    {
        $token = $this->token();
        if (empty($token)) {
            return null;
        }

        $coordinates = "{$originLng},{$originLat};{$destLng},{$destLat}";

        try {
            $response = Http::timeout(5)->get(
                "https://api.mapbox.com/directions/v5/mapbox/{$mode}/{$coordinates}",
                [
                    // No geometry needed — testPrice only reads
                    // distance/duration, never route points.
                    'overview' => 'false',
                    'access_token' => $token,
                ]
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $body = $response->json();
        $route = $body['routes'][0] ?? null;

        if (($body['code'] ?? null) !== 'Ok' || $route === null) {
            return null;
        }

        return [
            'distance_meters' => (int) round($route['distance'] ?? 0),
            'duration_seconds' => (int) round($route['duration'] ?? 0),
        ];
    }

    /**
     * Caches one raw feature under its own place_id (30 days, same
     * reasoning as the class-level cache TTL) so retrievePlace() — or a
     * feature encountered again via a different endpoint — can resolve it
     * without another billed request. Returns the place_id it was cached
     * under.
     */
    private function cacheFeature(array $feature): string
    {
        // Prefixed so MapProxyController::placeDetails can tell a Mapbox
        // place_id apart from a Google one on sight (a place_id always
        // belongs to whichever provider originally issued it, independent
        // of whatever places_provider is configured *now*), and so it never
        // collides with the Google cache namespace ('place_details:...').
        $placeId = 'mapbox:' . ($feature['id'] ?? md5(json_encode($feature)));

        Cache::put($this->featureCacheKey($placeId), $feature, now()->addDays(self::CACHE_TTL_DAYS));

        return $placeId;
    }

    private function featureCacheKey(string $placeId): string
    {
        return 'mapbox_feature:' . $placeId;
    }

    /**
     * Mapbox coordinates are always [lng, lat] (GeoJSON order) — every
     * place this class reads coordinates from goes through here so that
     * flip happens exactly once, consistently, before anything is handed
     * back in Google's {lat, lng} shape.
     */
    private function coordinatesOf(array $feature): array
    {
        $pair = $feature['center'] ?? ($feature['geometry']['coordinates'] ?? null);
        if (!is_array($pair) || count($pair) < 2) {
            return [null, null];
        }

        return [(float) $pair[1], (float) $pair[0]];
    }

    /**
     * Synthesizes Google-style address_components for one Mapbox feature —
     * its own place_type/text (the most specific level, e.g. the house
     * number + street for an 'address' feature) plus every ancestor in its
     * `context` array (neighborhood/locality/place/region/country), each
     * translated to the closest Google component type. This is the piece
     * address.dart's Lebanon-specific composition logic actually reads:
     * `route`/`street_number` for the road segment, `locality` for city,
     * `administrative_area_level_1` for region, and the sublocality/
     * neighborhood types for the neighborhood phrase.
     */
    private function featureToAddressComponents(array $feature): array
    {
        $components = [];
        $placeType = $feature['place_type'][0] ?? null;
        $text = $feature['text'] ?? null;

        if ($placeType === 'address') {
            $houseNumber = $feature['address'] ?? null;
            if (!empty($houseNumber)) {
                $components[] = $this->component((string) $houseNumber, (string) $houseNumber, ['street_number']);
            }
            if (!empty($text)) {
                $components[] = $this->component($text, $text, ['route']);
            }
        } elseif ($placeType === 'poi') {
            if (!empty($text)) {
                $components[] = $this->component($text, $text, ['point_of_interest', 'establishment']);
            }
        } elseif (!empty($text) && $placeType !== null) {
            $components[] = $this->component($text, $text, $this->googleTypesFor($placeType));
        }

        foreach (($feature['context'] ?? []) as $context) {
            $id = $context['id'] ?? '';
            $prefix = strstr($id, '.', true);
            $prefix = $prefix === false ? $id : $prefix;
            $name = $context['text'] ?? null;
            if (empty($name) || $prefix === '') {
                continue;
            }
            $components[] = $this->component($name, $context['short_code'] ?? $name, $this->googleTypesFor($prefix));
        }

        return $components;
    }

    private function component(string $longName, string $shortName, array $types): array
    {
        return ['long_name' => $longName, 'short_name' => $shortName, 'types' => $types];
    }

    /**
     * Mapbox's place-type hierarchy translated to the closest Google
     * address_components type(s) address.dart actually looks for. 'place'
     * (Mapbox's city/town level) maps to Google's 'locality' — NOT
     * Mapbox's own 'locality' type, which is a finer division *within* a
     * place (borough-like) and maps instead to Google's 'sublocality'.
     * Easy to get backwards since the names look alike but mean different
     * levels in each provider's hierarchy.
     */
    private function googleTypesFor(string $mapboxType): array
    {
        return match ($mapboxType) {
            'country' => ['country', 'political'],
            'region' => ['administrative_area_level_1', 'political'],
            'district' => ['administrative_area_level_2', 'political'],
            'postcode' => ['postal_code'],
            'place' => ['locality', 'political'],
            'locality' => ['sublocality', 'sublocality_level_1', 'political'],
            'neighborhood' => ['neighborhood', 'political'],
            default => ['political'],
        };
    }
}
