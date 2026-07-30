<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Proxies the Google Maps Platform calls the app needs (Directions,
 * Geocoding, Places Nearby Search) through the backend instead of the
 * mobile client calling Google directly with an embedded key. The key that
 * used to be hardcoded across several Flutter files (extractable from the
 * APK, usable by anyone for any of these billed APIs, not just this app) now
 * only ever lives server-side — config('services.google_maps.key'), from
 * GOOGLE_MAPS_KEY in .env — and should be restricted by server IP in Google
 * Cloud Console. The client no longer needs a Maps-platform key for
 * anything except rendering its own map (Maps SDK for Android/iOS), which
 * is a separate, more narrowly restrictable key.
 *
 * Every endpoint here sits behind the same auth:api middleware as the rest
 * of routes/api.php, so this can't be used as an anonymous open relay for
 * Google's paid APIs — only a logged-in app user can reach it.
 */
class MapProxyController extends Controller
{
    private function apiKey(): ?string
    {
        return config('services.google_maps.key');
    }

    // Used by map.dart (placing an order) and order_tracking.dart (live
    // driver tracking) — both only ever needed a plain list of route points
    // to draw, not Google's raw response shape, so this decodes the
    // polyline server-side and returns clean {lat, lng} pairs.
    public function directions(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'origin_lat' => 'required|numeric',
            'origin_lng' => 'required|numeric',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return response()->json(['result' => false, 'message' => 'Maps not configured'], 503);
        }

        $originLat = (float) $request->input('origin_lat');
        $originLng = (float) $request->input('origin_lng');
        $destLat = (float) $request->input('destination_lat');
        $destLng = (float) $request->input('destination_lng');

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destLat},{$destLng}",
                'mode' => 'driving',
                'key' => $apiKey,
            ]);

            if (!$response->successful()) {
                return response()->json(['result' => false, 'message' => 'Directions request failed'], 502);
            }

            $body = $response->json();
            $route = $body['routes'][0] ?? null;
            $encoded = $route['overview_polyline']['points'] ?? null;

            if (($body['status'] ?? null) !== 'OK' || !$encoded) {
                // Could be a legitimate "no route found", or a key/quota
                // problem on Google's side (wrong IP restriction, API not
                // enabled, etc.) — either way the caller just gets an empty
                // route rather than a failure it'd retry, but the real
                // reason must land in the log, not disappear silently.
                Log::warning('MapProxyController::directions: no route from Google', [
                    'status' => $body['status'] ?? null,
                    'error_message' => $body['error_message'] ?? null,
                ]);
                return response()->json(['result' => true, 'points' => []], 200);
            }

            $points = $this->decodePolyline($encoded);

            // Pin the ends to the exact requested coordinates — Google
            // snaps the decoded polyline's first/last vertex to the nearest
            // road, which can land a few metres off the true pickup/
            // destination point. Mirrors what the Flutter client used to do
            // itself against flutter_polyline_points' result.
            if (!empty($points)) {
                $points[0] = ['lat' => $originLat, 'lng' => $originLng];
                $points[count($points) - 1] = ['lat' => $destLat, 'lng' => $destLng];
            }

            return response()->json(['result' => true, 'points' => $points], 200);
        } catch (\Throwable $e) {
            Log::warning('MapProxyController::directions failed', ['error' => $e->getMessage()]);
            return response()->json(['result' => false, 'message' => 'Directions request failed'], 502);
        }
    }

    // Used by address.dart while the seller drags the map pin to pick an
    // address. Forwarded verbatim (status/results/address_components as
    // Google returns them) rather than reshaped — the client's existing
    // parsing logic already knows how to read Google's own response, and
    // reshaping it here would mean re-testing all of that for no benefit.
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

        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'REQUEST_DENIED', 'error_message' => 'Maps not configured'], 503);
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => $request->input('lat') . ',' . $request->input('lng'),
                'language' => $request->input('language', 'en'),
                'key' => $apiKey,
            ]);

            return response($response->body(), $response->status())
                ->header('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            Log::warning('MapProxyController::reverseGeocode failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'UNKNOWN_ERROR'], 502);
        }
    }

    // Used by address.dart to find the nearest named place/business for the
    // dragged pin (Geocoding alone rarely has a building/business name).
    // Same verbatim-forwarding reasoning as reverseGeocode above. `radius`/
    // `rankby` are mutually exclusive to Google — only one is ever sent,
    // same constraint the client already respected when building these
    // query fragments itself.
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

        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'REQUEST_DENIED', 'error_message' => 'Maps not configured'], 503);
        }

        $query = [
            'location' => $request->input('lat') . ',' . $request->input('lng'),
            'language' => $request->input('language', 'en'),
            'key' => $apiKey,
        ];

        if ($request->filled('rankby')) {
            $query['rankby'] = $request->input('rankby');
        } elseif ($request->filled('radius')) {
            $query['radius'] = $request->input('radius');
        }
        if ($request->filled('type')) {
            $query['type'] = $request->input('type');
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', $query);

            return response($response->body(), $response->status())
                ->header('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            Log::warning('MapProxyController::placesNearby failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'UNKNOWN_ERROR'], 502);
        }
    }

    // Used by the address search box (AddressAutocompleteField on the
    // Flutter side, replacing the google_places_flutter package — that
    // package called Google's Autocomplete/Place Details APIs directly with
    // an embedded key and had no proxy option, so the widget itself had to
    // be replaced, not just redirected). Country restriction hardcoded to
    // "lb" — matches the `countries: ["lb"]` the old widget was configured
    // with; this app only ever searches Lebanon addresses.
    public function placeAutocomplete(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'input' => 'required|string|max:200',
            'language' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'INVALID_REQUEST', 'errors' => $validator->errors()], 400);
        }

        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'REQUEST_DENIED', 'error_message' => 'Maps not configured'], 503);
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'input' => $request->input('input'),
                'language' => $request->input('language', 'en'),
                'components' => 'country:lb',
                'key' => $apiKey,
            ]);

            return response($response->body(), $response->status())
                ->header('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            Log::warning('MapProxyController::placeAutocomplete failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'UNKNOWN_ERROR'], 502);
        }
    }

    public function placeDetails(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $validator = Validator::make($request->all(), [
            'place_id' => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'INVALID_REQUEST', 'errors' => $validator->errors()], 400);
        }

        $apiKey = $this->apiKey();
        if (empty($apiKey)) {
            return response()->json(['status' => 'REQUEST_DENIED', 'error_message' => 'Maps not configured'], 503);
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                'placeid' => $request->input('place_id'),
                'key' => $apiKey,
            ]);

            return response($response->body(), $response->status())
                ->header('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            Log::warning('MapProxyController::placeDetails failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'UNKNOWN_ERROR'], 502);
        }
    }

    /**
     * Decodes a Google-encoded polyline string into {lat, lng} pairs — the
     * standard algorithm (see Google's own reference at
     * developers.google.com/maps/documentation/utilities/polylinealgorithm),
     * reimplemented here so this proxy doesn't need an extra package for
     * what's about 20 lines of well-established bit-twiddling.
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
}
