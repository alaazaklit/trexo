<?php

namespace Tests\Unit;

use App\Services\MapboxService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coordinate-order and address_components-shape correctness for the
 * Mapbox-to-Google normalization layer — the part MapProxyController's
 * fallback logic and address.dart's Lebanon-specific address composition
 * both depend on being right. Uses the real app container (Cache/Http
 * facades) rather than plain PHPUnit\Framework\TestCase, but never touches
 * the database — only Http::fake and the array cache driver already
 * configured for testing (see phpunit.xml).
 */
class MapboxServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mapbox.token' => 'test-mapbox-token']);
    }

    private function fakeFeature(): array
    {
        // A realistic Mapbox Geocoding v5 'address' feature for a Lebanese
        // point — center in [lng, lat] order, as Mapbox always returns it.
        return [
            'id' => 'address.123456789',
            'place_type' => ['address'],
            'text' => 'Rue Riad El Solh',
            'address' => '12',
            'place_name' => '12 Rue Riad El Solh, Saida, South Governorate, Lebanon',
            'center' => [35.3758, 33.5606], // [lng, lat]
            'context' => [
                ['id' => 'neighborhood.111', 'text' => 'Haret Saida'],
                ['id' => 'place.222', 'text' => 'Saida'],
                ['id' => 'region.333', 'text' => 'South Governorate', 'short_code' => 'LB-JA'],
                ['id' => 'country.444', 'text' => 'Lebanon', 'short_code' => 'lb'],
            ],
        ];
    }

    // --- coordinate swap -------------------------------------------------

    public function test_search_places_swaps_mapbox_lng_lat_to_google_lat_lng_via_retrieve_place(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeFeature()]], 200),
        ]);

        $service = new MapboxService();
        $search = $service->searchPlaces('Riad El Solh', country: 'lb');

        $this->assertCount(1, $search['predictions']);
        $placeId = $search['predictions'][0]['place_id'];
        $this->assertStringStartsWith('mapbox:', $placeId);

        $details = $service->retrievePlace($placeId);

        $this->assertNotNull($details);
        // Mapbox's center was [35.3758, 33.5606] ([lng, lat]) — the
        // normalized result must come back as latitude=33.5606,
        // longitude=35.3758, not swapped or passed through as-is.
        $this->assertSame(33.5606, $details['result']['geometry']['location']['lat']);
        $this->assertSame(35.3758, $details['result']['geometry']['location']['lng']);
    }

    public function test_reverse_geocode_swaps_coordinates_and_synthesizes_address_components(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeFeature()]], 200),
        ]);

        $service = new MapboxService();
        $result = $service->reverseGeocode(lat: 33.5606, lng: 35.3758);

        $this->assertSame('OK', $result['status']);
        $this->assertCount(1, $result['results']);

        $components = $result['results'][0]['address_components'];
        $byType = [];
        foreach ($components as $component) {
            foreach ($component['types'] as $type) {
                $byType[$type] = $component['long_name'];
            }
        }

        // These are exactly the component types address.dart's
        // _findFirstAcrossResults()/_buildComposedAddress() scan for.
        $this->assertSame('Rue Riad El Solh', $byType['route'] ?? null);
        $this->assertSame('12', $byType['street_number'] ?? null);
        $this->assertSame('Saida', $byType['locality'] ?? null);
        $this->assertSame('South Governorate', $byType['administrative_area_level_1'] ?? null);
        $this->assertSame('Haret Saida', $byType['neighborhood'] ?? null);
    }

    public function test_places_nearby_returns_google_shaped_results_with_swapped_coordinates(): void
    {
        $poiFeature = $this->fakeFeature();
        $poiFeature['place_type'] = ['poi'];
        $poiFeature['text'] = 'Sea Castle';

        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$poiFeature]], 200),
        ]);

        $service = new MapboxService();
        $result = $service->placesNearby(lat: 33.5606, lng: 35.3758);

        $this->assertSame('OK', $result['status']);
        $this->assertSame('Sea Castle', $result['results'][0]['name']);
        $this->assertSame(33.5606, $result['results'][0]['geometry']['location']['lat']);
        $this->assertSame(35.3758, $result['results'][0]['geometry']['location']['lng']);
    }

    // --- zero results is a real answer, not a failure --------------------

    public function test_reverse_geocode_returns_zero_results_status_without_throwing(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response(['features' => []], 200)]);

        $result = (new MapboxService())->reverseGeocode(lat: 0.0, lng: 0.0);

        $this->assertSame('ZERO_RESULTS', $result['status']);
        $this->assertSame([], $result['results']);
    }

    // --- failure signaling (for MapProxyController's fallback) -----------

    public function test_search_places_throws_on_http_failure_so_the_caller_can_fall_back(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response(['message' => 'invalid token'], 401)]);

        $this->expectException(\RuntimeException::class);
        (new MapboxService())->searchPlaces('anything');
    }

    public function test_retrieve_place_returns_null_for_an_unknown_or_expired_place_id(): void
    {
        $result = (new MapboxService())->retrievePlace('mapbox:does-not-exist');

        $this->assertNull($result);
    }

    // --- caching -----------------------------------------------------------

    public function test_reverse_geocode_result_is_cached_and_does_not_re_hit_http_on_repeat(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeFeature()]], 200),
        ]);

        $service = new MapboxService();
        $service->reverseGeocode(lat: 33.5606, lng: 35.3758);
        $service->reverseGeocode(lat: 33.5606, lng: 35.3758);

        Http::assertSentCount(1);
    }
}
