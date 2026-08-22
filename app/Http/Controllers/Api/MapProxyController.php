<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use TCG\Voyager\Models\Setting;
use App\Services\MapboxService;

/**
 * Proxies the Google Maps Platform calls the app needs (Geocoding, Places
 * Nearby Search, Autocomplete) through the backend instead of the mobile
 * client calling Google directly with an embedded key. The key that used to
 * be hardcoded across several Flutter files (extractable from the APK,
 * usable by anyone for any of these billed APIs, not just this app) now
 * only ever lives server-side — config('services.google_maps.key'), from
 * GOOGLE_MAPS_KEY in .env — and should be restricted by server IP in Google
 * Cloud Console. The client no longer needs a Maps-platform key for
 * anything except rendering its own map (Maps SDK for Android/iOS), which
 * is a separate, more narrowly restrictable key.
 *
 * Every method below has the same shape: which upstream provider serves it
 * (Google or Mapbox) is an admin-editable toggle — 'maps.directions_provider'
 * for directions(), 'maps.places_provider' for the other four — changeable
 * live from /admin/settings (group "Maps") or POST /admin/maps-config, see
 * MapsConfigController, instead of hardcoded. Google Maps still renders the
 * result either way (tiles, markers, the Polyline, the search box overlay —
 * all stay Google) since the client only ever consumed these as Google's own
 * plain response fields (points/distance/duration for directions;
 * address_components/formatted_address/predictions/geometry.location for
 * the rest), never either provider's raw response shape — MapboxService
 * reshapes Mapbox's GeoJSON-based data into that same field shape. If the
 * selected provider's call fails outright (not a legitimate zero-result),
 * each method automatically retries once against the other provider before
 * giving up, so a single provider outage doesn't take that feature down
 * entirely. placeDetails() is the one exception to the Setting-based
 * dispatch: a place_id is an opaque identifier issued by whichever provider
 * produced it at search time, so it's always resolved by that same provider
 * (detected from the id itself), never by whatever places_provider happens
 * to be configured at lookup time.
 *
 * Every endpoint here sits behind the same auth:api middleware as the rest
 * of routes/api.php, so this can't be used as an anonymous open relay for
 * either provider's paid APIs — only a logged-in app user can reach it.
 */
class MapProxyController extends Controller
{
    private const DIRECTIONS_MODES = ['driving', 'walking', 'cycling'];

    private function apiKey(): ?string
    {
        return config('services.google_maps.key');
    }

    private function mapboxToken(): ?string
    {
        return config('services.mapbox.token');
    }

    private function directionsProvider(): string
    {
        $value = Setting::where('key', 'maps.directions_provider')->value('value');
        return in_array($value, ['google', 'mapbox'], true) ? $value : 'mapbox';
    }

    private function placesProvider(): string
    {
        $value = Setting::where('key', 'maps.places_provider')->value('value');
        return in_array($value, ['google', 'mapbox'], true) ? $value : 'google';
    }

    // Used by map.dart (placing an order), order_details.dart/
    // reservation_details.dart (past-trip route display), and
    // order_tracking.dart (live driver tracking) — all only ever needed a
    // plain list of route points to draw (plus, now, the trip's distance/
    // duration) rather than any provider-specific response shape, so this
    // returns the same normalized shape regardless of which provider
    // actually served the request.
    public function directions(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'origin_lat' => 'required|numeric',
            'origin_lng' => 'required|numeric',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
            'mode' => 'nullable|string|in:'.implode(',', self::DIRECTIONS_MODES),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $originLat = (float) $request->input('origin_lat');
        $originLng = (float) $request->input('origin_lng');
        $destLat = (float) $request->input('destination_lat');
        $destLng = (float) $request->input('destination_lng');
        $mode = $request->input('mode', 'driving');

        $primary = $this->directionsProvider();
        $fallback = $primary === 'google' ? 'mapbox' : 'google';

        $result = $this->fetchDirections($primary, $mode, $originLat, $originLng, $destLat, $destLng);

        if ($result === null) {
            // The primary provider either errored outright or isn't
            // configured (missing key/token) — try the other one before
            // giving up, so a single provider's outage/misconfiguration
            // doesn't take route display down entirely. Not entered when
            // $primary legitimately just found no route (that already
            // returns a result with an empty point list, not null).
            Log::warning("MapProxyController::directions: falling back from {$primary} to {$fallback}");
            $result = $this->fetchDirections($fallback, $mode, $originLat, $originLng, $destLat, $destLng);
        }

        if ($result === null) {
            return response()->json(['result' => false, 'message' => 'Directions request failed'], 502);
        }

        $points = $result['points'];

        // Pin the ends to the exact requested coordinates — both providers
        // snap the route's first/last vertex to the nearest road, which can
        // land a few metres off the true pickup/destination point.
        if (!empty($points)) {
            $points[0] = ['lat' => $originLat, 'lng' => $originLng];
            $points[count($points) - 1] = ['lat' => $destLat, 'lng' => $destLng];
        }

        return response()->json([
            'result' => true,
            'provider_used' => $result['provider'],
            'distance_meters' => $result['distance_meters'],
            'duration_seconds' => $result['duration_seconds'],
            'points' => $points,
        ], 200);
    }

    /**
     * Returns null on any failure (bad token/key, network error, malformed
     * response) so the caller can fall back to the other provider. A
     * legitimate "no route exists between these points" is NOT a failure —
     * that returns a real result with an empty points array, matching what
     * this endpoint always returned for that case.
     */
    private function fetchDirections(
        string $provider,
        string $mode,
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): ?array {
        try {
            return $provider === 'google'
                ? $this->fetchDirectionsFromGoogle($mode, $originLat, $originLng, $destLat, $destLng)
                : $this->fetchDirectionsFromMapbox($mode, $originLat, $originLng, $destLat, $destLng);
        } catch (\Throwable $e) {
            Log::warning("MapProxyController::directions ({$provider}) failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function fetchDirectionsFromMapbox(
        string $mode,
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): ?array {
        $mapboxToken = $this->mapboxToken();
        if (empty($mapboxToken)) {
            return null;
        }

        // Mapbox's driving/walking/cycling profiles line up 1:1 with the
        // modes this endpoint accepts — no remapping needed, unlike Google.
        $profile = $mode;

        // geometries=geojson + overview=full: full-resolution route
        // coordinates as [lng, lat] pairs (GeoJSON order), matching the
        // level of detail the old Google overview_polyline decode gave the
        // client. overview=full must be explicit — Mapbox defaults to a
        // simplified line otherwise.
        $coordinates = "{$originLng},{$originLat};{$destLng},{$destLat}";
        $response = Http::timeout(8)->get(
            "https://api.mapbox.com/directions/v5/mapbox/{$profile}/{$coordinates}",
            [
                'geometries' => 'geojson',
                'overview' => 'full',
                'access_token' => $mapboxToken,
            ]
        );

        if (!$response->successful()) {
            return null;
        }

        $body = $response->json();
        $route = $body['routes'][0] ?? null;
        $coords = $route['geometry']['coordinates'] ?? null;

        if (($body['code'] ?? null) !== 'Ok' || !$coords) {
            // Could be a legitimate "no route found", or a token/quota
            // problem on Mapbox's side — either way the caller just gets an
            // empty route rather than a failure it'd retry, but the real
            // reason must land in the log, not disappear silently.
            Log::warning('MapProxyController::directions: no route from Mapbox', [
                'code' => $body['code'] ?? null,
                'message' => $body['message'] ?? null,
            ]);
            return ['provider' => 'mapbox', 'distance_meters' => 0, 'duration_seconds' => 0, 'points' => []];
        }

        // GeoJSON coordinates come as [lng, lat] — flip to the {lat, lng}
        // shape the client already expects.
        $points = array_map(
            static fn (array $pair) => ['lat' => (float) $pair[1], 'lng' => (float) $pair[0]],
            $coords
        );

        return [
            'provider' => 'mapbox',
            'distance_meters' => (int) round($route['distance'] ?? 0),
            'duration_seconds' => (int) round($route['duration'] ?? 0),
            'points' => $points,
        ];
    }

    private function fetchDirectionsFromGoogle(
        string $mode,
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): ?array {
        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return null;
        }

        // Google calls this travel mode "bicycling", not "cycling" — the
        // only one of the three that doesn't match this endpoint's own
        // mode names verbatim.
        $googleMode = $mode === 'cycling' ? 'bicycling' : $mode;

        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/directions/json', [
            'origin' => "{$originLat},{$originLng}",
            'destination' => "{$destLat},{$destLng}",
            'mode' => $googleMode,
            'key' => $apiKey,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $body = $response->json();
        $route = $body['routes'][0] ?? null;
        $encoded = $route['overview_polyline']['points'] ?? null;

        if (($body['status'] ?? null) !== 'OK' || !$encoded) {
            // Could be a legitimate "no route found", or a key/quota
            // problem on Google's side (wrong IP restriction, API not
            // enabled, etc.) — either way the caller just gets an empty
            // route rather than a failure it'd retry, but the real reason
            // must land in the log, not disappear silently.
            Log::warning('MapProxyController::directions: no route from Google', [
                'status' => $body['status'] ?? null,
                'error_message' => $body['error_message'] ?? null,
            ]);
            return ['provider' => 'google', 'distance_meters' => 0, 'duration_seconds' => 0, 'points' => []];
        }

        $legs = $route['legs'] ?? [];
        $distanceMeters = 0;
        $durationSeconds = 0;
        foreach ($legs as $leg) {
            $distanceMeters += $leg['distance']['value'] ?? 0;
            $durationSeconds += $leg['duration']['value'] ?? 0;
        }

        return [
            'provider' => 'google',
            'distance_meters' => (int) $distanceMeters,
            'duration_seconds' => (int) $durationSeconds,
            'points' => $this->decodePolyline($encoded),
        ];
    }

    /**
     * Decodes a Google-encoded polyline string into {lat, lng} pairs — the
     * standard algorithm (see Google's own reference at
     * developers.google.com/maps/documentation/utilities/polylinealgorithm),
     * reimplemented here so this proxy doesn't need an extra package for
     * what's about 20 lines of well-established bit-twiddling. Only needed
     * when 'google' is (or is being tried as a fallback for) the active
     * directions_provider — Mapbox returns plain coordinates already.
     */
    private function decodePolyline(string $encoded): array
    {
        $points = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $deltaLat = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lat += $deltaLat;

            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $deltaLng = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lng += $deltaLng;

            $points[] = ['lat' => $lat / 1e5, 'lng' => $lng / 1e5];
        }

        return $points;
    }

    // Used by address.dart while the seller drags the map pin to pick an
    // address. Which upstream provider serves this — Google or Mapbox — is
    // the same admin-editable 'maps.places_provider' Setting used by the
    // other three Places/Geocoding endpoints below (see placesProvider()),
    // with the same automatic-fallback-to-the-other-provider behavior as
    // directions(). The Google path is untouched from before this Setting
    // existed — forwarded verbatim (status/results/address_components as
    // Google returns them) rather than reshaped, since the client's parsing
    // logic already knows how to read Google's own response. The Mapbox
    // path (MapboxService::reverseGeocode) reshapes Mapbox's data into that
    // same address_components/formatted_address shape instead, so
    // address.dart never needs to know which provider actually answered.
    public function reverseGeocode(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'language' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'INVALID_REQUEST', 'errors' => $validator->errors()], 400);
        }

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $language = $request->input('language', 'en');

        $primary = $this->placesProvider();
        $fallback = $primary === 'google' ? 'mapbox' : 'google';

        $response = $this->fetchReverseGeocode($primary, $lat, $lng, $language);
        if ($response === null) {
            Log::warning("MapProxyController::reverseGeocode: falling back from {$primary} to {$fallback}");
            $response = $this->fetchReverseGeocode($fallback, $lat, $lng, $language);
        }

        return $response ?? response()->json(['status' => 'UNKNOWN_ERROR'], 502);
    }

    /**
     * Returns null on any failure (missing key/token, network error, or a
     * real provider-side error status — REQUEST_DENIED/OVER_QUERY_LIMIT/
     * INVALID_REQUEST/UNKNOWN_ERROR) so the caller can try the other
     * provider. A legitimate ZERO_RESULTS is NOT a failure — same
     * "empty is a real answer, not an error" philosophy as
     * fetchDirections() above.
     */
    private function fetchReverseGeocode(string $provider, float $lat, float $lng, string $language)
    {
        try {
            return $provider === 'google'
                ? $this->reverseGeocodeFromGoogle($lat, $lng, $language)
                : $this->reverseGeocodeFromMapbox($lat, $lng, $language);
        } catch (\Throwable $e) {
            Log::warning("MapProxyController::reverseGeocode ({$provider}) failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function reverseGeocodeFromGoogle(float $lat, float $lng, string $language)
    {
        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return null;
        }

        // The client calls this on every pin drag/camera-idle while the
        // seller is still nudging the pin around the same spot — rounding to
        // ~11m buckets (4 decimal places, same "same place" precision the
        // client itself uses to dedupe addresses) means most of those nudges
        // hit the cache instead of paying for another Google round trip.
        $latBucket = round($lat, 4);
        $lngBucket = round($lng, 4);
        $cacheKey = 'reverse_geocode:' . md5("{$latBucket},{$lngBucket}|{$language}");

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response($cached['body'], $cached['status'])
                ->header('Content-Type', 'application/json');
        }

        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => "{$lat},{$lng}",
            'language' => $language,
            'key' => $apiKey,
        ]);
        $status = $response->json('status');

        if (!$response->successful() || !in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            Log::warning('MapProxyController::reverseGeocode: Google returned no usable result', ['status' => $status]);
            return null;
        }

        Cache::put($cacheKey, ['body' => $response->body(), 'status' => $response->status()], now()->addMinutes(30));

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }

    private function reverseGeocodeFromMapbox(float $lat, float $lng, string $language)
    {
        if (empty($this->mapboxToken())) {
            return null;
        }

        $normalized = (new MapboxService())->reverseGeocode($lat, $lng, $language);
        if (!in_array($normalized['status'] ?? null, ['OK', 'ZERO_RESULTS'], true)) {
            return null;
        }

        return response()->json($normalized, 200);
    }

    // Used by address.dart to find the nearest named place/business for the
    // dragged pin (Geocoding alone rarely has a building/business name).
    // Same provider-dispatch/fallback shape as reverseGeocode() above.
    // `radius`/`rankby` are mutually exclusive to Google — only one is ever
    // sent, same constraint the client already respected when building
    // these query fragments itself. See MapboxService::placesNearby()'s
    // docblock for the one real capability gap on the Mapbox side: it can't
    // distinguish Google's `type=tourist_attraction` vs `type=establishment`
    // sub-categories, only "nearest POI" broadly.
    public function placesNearby(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'language' => 'nullable|string|max:10',
            'radius' => 'nullable|integer|min:1|max:50000',
            'type' => 'nullable|string|max:60',
            'rankby' => 'nullable|string|in:distance,prominence',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'INVALID_REQUEST', 'errors' => $validator->errors()], 400);
        }

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $language = $request->input('language', 'en');
        $rankby = $request->filled('rankby') ? $request->input('rankby') : null;
        $radius = (!$rankby && $request->filled('radius')) ? $request->input('radius') : null;
        $type = $request->filled('type') ? $request->input('type') : null;

        $primary = $this->placesProvider();
        $fallback = $primary === 'google' ? 'mapbox' : 'google';

        $response = $this->fetchPlacesNearby($primary, $lat, $lng, $language, $rankby, $radius, $type);
        if ($response === null) {
            Log::warning("MapProxyController::placesNearby: falling back from {$primary} to {$fallback}");
            $response = $this->fetchPlacesNearby($fallback, $lat, $lng, $language, $rankby, $radius, $type);
        }

        return $response ?? response()->json(['status' => 'UNKNOWN_ERROR'], 502);
    }

    private function fetchPlacesNearby(
        string $provider,
        float $lat,
        float $lng,
        string $language,
        ?string $rankby,
        $radius,
        ?string $type
    ) {
        try {
            return $provider === 'google'
                ? $this->placesNearbyFromGoogle($lat, $lng, $language, $rankby, $radius, $type)
                : $this->placesNearbyFromMapbox($lat, $lng, $language);
        } catch (\Throwable $e) {
            Log::warning("MapProxyController::placesNearby ({$provider}) failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function placesNearbyFromGoogle(
        float $lat,
        float $lng,
        string $language,
        ?string $rankby,
        $radius,
        ?string $type
    ) {
        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return null;
        }

        // The client fires this (now up to three variants in parallel, see
        // address.dart's `_fetchNearestPlaceName`) on every pin drag/camera-
        // idle — named places/landmarks don't move, so the same ~11m bucket
        // (4 decimal places, matching reverseGeocode's bucketing above) plus
        // the exact search variant (radius/type/rankby) can be cached far
        // longer than a search-text-driven endpoint like autocomplete.
        $latBucket = round($lat, 4);
        $lngBucket = round($lng, 4);
        $cacheKeyParts = ["{$latBucket},{$lngBucket}", $language, $rankby ?? '', $radius ?? '', $type ?? ''];
        $cacheKey = 'places_nearby:' . md5(implode('|', $cacheKeyParts));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response($cached['body'], $cached['status'])
                ->header('Content-Type', 'application/json');
        }

        $query = [
            'location' => "{$lat},{$lng}",
            'language' => $language,
            'key' => $apiKey,
        ];

        if ($rankby) {
            $query['rankby'] = $rankby;
        } elseif ($radius) {
            $query['radius'] = $radius;
        }
        if ($type) {
            $query['type'] = $type;
        }

        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', $query);
        $status = $response->json('status');

        if (!$response->successful() || !in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            Log::warning('MapProxyController::placesNearby: Google returned no usable result', ['status' => $status]);
            return null;
        }

        Cache::put($cacheKey, ['body' => $response->body(), 'status' => $response->status()], now()->addHours(6));

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }

    private function placesNearbyFromMapbox(float $lat, float $lng, string $language)
    {
        if (empty($this->mapboxToken())) {
            return null;
        }

        $normalized = (new MapboxService())->placesNearby($lat, $lng, $language);
        if (!in_array($normalized['status'] ?? null, ['OK', 'ZERO_RESULTS'], true)) {
            return null;
        }

        return response()->json($normalized, 200);
    }

    // Used by the address search box (AddressAutocompleteField on the
    // Flutter side, replacing the google_places_flutter package — that
    // package called Google's Autocomplete/Place Details APIs directly with
    // an embedded key and had no proxy option, so the widget itself had to
    // be replaced, not just redirected). Country restriction hardcoded to
    // "lb" — matches the `countries: ["lb"]` the old widget was configured
    // with; this app only ever searches Lebanon addresses. Same provider-
    // dispatch/fallback shape as reverseGeocode()/placesNearby() above.
    public function placeAutocomplete(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'input' => 'required|string|max:200',
            'language' => 'nullable|string|max:10',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'radius' => 'nullable|integer|min:1000|max:50000',
            // '(regions)' biases toward neighborhoods/localities/areas
            // rather than a specific address or point of interest — e.g.
            // a school-bus route's pickup AREA, where "Haret Saida" is
            // wanted, not one exact building on that street. Restricted to
            // Google's own allowed autocomplete type-collection values.
            'types' => 'nullable|string|in:geocode,address,establishment,(regions),(cities)',
            // Forwarded straight through to Google so a whole search
            // session (every keystroke-driven autocomplete request plus the
            // eventual Details lookup) bills as one bundled unit instead of
            // each autocomplete request billing separately. Deliberately
            // NOT part of the cache key below — it's a billing-correlation
            // value, not part of what makes two searches "the same search".
            'sessiontoken' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'INVALID_REQUEST', 'errors' => $validator->errors()], 400);
        }

        $input = trim($request->input('input'));
        $language = $request->input('language', 'en');

        // Location bias: a soft ranking hint (not a hard restriction, so a
        // legitimate search outside the seller's immediate area still
        // works) that stops a same-named place in another part of the
        // country from outranking — or crowding out — the one actually near
        // them (e.g. "Masjed Lsona" in Tripoli vs. the one in Saida).
        $biasLat = $request->input('lat');
        $biasLng = $request->input('lng');
        $biasRadius = $request->input('radius', 50000);
        $types = $request->input('types');
        $sessionToken = $request->input('sessiontoken');

        $primary = $this->placesProvider();
        $fallback = $primary === 'google' ? 'mapbox' : 'google';

        $response = $this->fetchPlaceAutocomplete($primary, $input, $language, $biasLat, $biasLng, $biasRadius, $types, $sessionToken);
        if ($response === null) {
            Log::warning("MapProxyController::placeAutocomplete: falling back from {$primary} to {$fallback}");
            $response = $this->fetchPlaceAutocomplete($fallback, $input, $language, $biasLat, $biasLng, $biasRadius, $types, $sessionToken);
        }

        return $response ?? response()->json(['status' => 'UNKNOWN_ERROR'], 502);
    }

    private function fetchPlaceAutocomplete(
        string $provider,
        string $input,
        string $language,
        $biasLat,
        $biasLng,
        $biasRadius,
        ?string $types,
        ?string $sessionToken
    ) {
        try {
            return $provider === 'google'
                ? $this->placeAutocompleteFromGoogle($input, $language, $biasLat, $biasLng, $biasRadius, $types, $sessionToken)
                : $this->placeAutocompleteFromMapbox($input, $language, $biasLat, $biasLng);
        } catch (\Throwable $e) {
            Log::warning("MapProxyController::placeAutocomplete ({$provider}) failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function placeAutocompleteFromGoogle(
        string $input,
        string $language,
        $biasLat,
        $biasLng,
        $biasRadius,
        ?string $types,
        ?string $sessionToken
    ) {
        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return null;
        }

        $hasBias = $biasLat !== null && $biasLng !== null;

        // The bias coordinates drift by small amounts as the seller nudges
        // the map (drags, re-centers), which would otherwise fragment the
        // cache into near-duplicate entries for the same effective search —
        // rounding to ~1km buckets keeps caching effective without the bias
        // itself losing meaningful precision at a 50km-scale radius.
        $cacheKeyParts = [mb_strtolower($input), $language, $types ?? ''];
        if ($hasBias) {
            $cacheKeyParts[] = round((float) $biasLat, 2) . ',' . round((float) $biasLng, 2) . ',' . $biasRadius;
        }
        $cacheKey = 'places_autocomplete:' . md5(implode('|', $cacheKeyParts));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response($cached['body'], $cached['status'])
                ->header('Content-Type', 'application/json');
        }

        $query = [
            'input' => $input,
            'language' => $language,
            'components' => 'country:lb',
            'key' => $apiKey,
        ];
        if ($hasBias) {
            $query['location'] = "{$biasLat},{$biasLng}";
            $query['radius'] = $biasRadius;
        }
        if (!empty($sessionToken)) {
            $query['sessiontoken'] = $sessionToken;
        }
        if (!empty($types)) {
            $query['types'] = $types;
        }

        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', $query);
        $status = $response->json('status');

        if (!$response->successful() || !in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            Log::warning('MapProxyController::placeAutocomplete: Google returned no usable result', ['status' => $status]);
            return null;
        }

        Cache::put($cacheKey, ['body' => $response->body(), 'status' => $response->status()], now()->addHour());

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }

    private function placeAutocompleteFromMapbox(string $input, string $language, $biasLat, $biasLng)
    {
        if (empty($this->mapboxToken())) {
            return null;
        }

        // Mapbox has no equivalent of Google's autocomplete `types`
        // collection values ('(regions)', '(cities)', etc.) used to bias
        // toward e.g. a school-bus route's pickup-AREA search — Mapbox's
        // own `types` filter is a different vocabulary (its place-type
        // hierarchy, not Google's category groupings), so it's deliberately
        // left unmapped rather than passed through to silently do the
        // wrong thing.
        $normalized = (new MapboxService())->searchPlaces(
            query: $input,
            country: 'lb',
            biasLat: $biasLat !== null ? (float) $biasLat : null,
            biasLng: $biasLng !== null ? (float) $biasLng : null,
            language: $language
        );

        return response()->json($normalized, 200);
    }

    public function placeDetails(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'place_id' => 'required|string|max:200',
            'language' => 'nullable|string|max:10',
            // Same session as the autocomplete requests that led here — see
            // placeAutocomplete() above. Only actually sent to Google below
            // on a cache miss; a cached place_id never generates a billed
            // request at all, session or not.
            'sessiontoken' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'INVALID_REQUEST', 'errors' => $validator->errors()], 400);
        }

        $placeId = $request->input('place_id');
        $language = $request->input('language', 'en');
        $sessionToken = $request->input('sessiontoken');

        // A place_id is an opaque identifier issued by whichever provider
        // produced it at search time (placeAutocomplete above) — it must
        // always be resolved by THAT provider, regardless of whatever
        // places_provider is configured *now* (which could have changed in
        // between). Mapbox place_ids are tagged with a 'mapbox:' prefix
        // specifically so this can tell them apart from Google's on sight
        // instead of needing to guess-and-retry against both.
        if (str_starts_with($placeId, 'mapbox:')) {
            return $this->placeDetailsFromMapbox($placeId);
        }

        return $this->placeDetailsFromGoogle($placeId, $language, $sessionToken);
    }

    private function placeDetailsFromMapbox(string $placeId)
    {
        $normalized = (new MapboxService())->retrievePlace($placeId);
        if ($normalized === null) {
            // The cached feature behind this place_id has expired (or never
            // existed) — the same situation as a Google Details call
            // landing after its session expired. No fallback provider makes
            // sense here: a Google lookup has no idea what a Mapbox
            // place_id refers to.
            return response()->json(['status' => 'NOT_FOUND'], 200);
        }

        return response()->json(array_merge(['status' => 'OK'], $normalized), 200);
    }

    private function placeDetailsFromGoogle(string $placeId, string $language, ?string $sessionToken)
    {
        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'REQUEST_DENIED', 'error_message' => 'Maps not configured'], 503);
        }

        // Unlike autocomplete text, a place_id's coordinates/name don't
        // change between lookups, so this can be cached far longer — mainly
        // helps popular/landmark places that keep getting picked by
        // different sellers, letting the client's post-selection lookup
        // resolve instantly instead of waiting on another Google round-trip.
        // Keyed by language too, since the returned name/formatted_address
        // vary by it — a cache hit must not hand back the wrong language.
        $cacheKey = 'place_details:' . $placeId . ':' . $language;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response($cached['body'], $cached['status'])
                ->header('Content-Type', 'application/json');
        }

        try {
            // Restricted to Basic Data fields only — this proxy's only
            // consumer (AddressAutocompleteField) reads just the resolved
            // coordinates. Leaving `fields` unset makes Google return (and
            // bill) the full default set, which spans the pricier Contact +
            // Atmosphere Data SKUs for data nothing here ever uses.
            $detailsQuery = [
                'placeid' => $placeId,
                'language' => $language,
                'fields' => 'place_id,name,formatted_address,geometry',
                'key' => $apiKey,
            ];
            if (!empty($sessionToken)) {
                $detailsQuery['sessiontoken'] = $sessionToken;
            }

            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/details/json', $detailsQuery);

            if ($response->successful() && $response->json('status') === 'OK') {
                // A place's name/address/coordinates practically never
                // change, so cache far longer than the old 6 hours — every
                // hit here across every seller is one fewer billed Google
                // request for the same place_id.
                Cache::put($cacheKey, ['body' => $response->body(), 'status' => $response->status()], now()->addDays(30));
            }

            return response($response->body(), $response->status())
                ->header('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            Log::warning('MapProxyController::placeDetails failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'UNKNOWN_ERROR'], 502);
        }
    }

}
