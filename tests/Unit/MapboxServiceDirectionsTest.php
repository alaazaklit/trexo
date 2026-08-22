<?php

namespace Tests\Unit;

use App\Services\MapboxService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers MapboxService::directions() — the Google-Routes-API-free distance/
 * duration lookup DriverProfileController::testPrice() (the driver-facing
 * Check Price screen) uses, so that screen never calls
 * routes.googleapis.com the way real order/reservation pricing does (see
 * MatchesDriverSchedules::resolveTripDistanceKm()/drivingDurationMinutes()).
 */
class MapboxServiceDirectionsTest extends TestCase
{
    public function test_returns_distance_and_duration_on_success(): void
    {
        config(['services.mapbox.token' => 'test-token']);

        Http::fake([
            'api.mapbox.com/directions/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 12345.6, 'duration' => 987.4]],
            ], 200),
        ]);

        $result = (new MapboxService())->directions(33.56, 35.37, 33.89, 35.50);

        $this->assertSame(12346, $result['distance_meters']);
        $this->assertSame(987, $result['duration_seconds']);
    }

    public function test_returns_null_when_token_is_not_configured(): void
    {
        config(['services.mapbox.token' => null]);

        Http::fake(['api.mapbox.com/*' => Http::response(['code' => 'Ok'], 200)]);

        $this->assertNull((new MapboxService())->directions(33.56, 35.37, 33.89, 35.50));
        Http::assertNothingSent();
    }

    public function test_returns_null_on_http_failure(): void
    {
        config(['services.mapbox.token' => 'test-token']);

        Http::fake(['api.mapbox.com/*' => Http::response(['message' => 'invalid token'], 401)]);

        $this->assertNull((new MapboxService())->directions(33.56, 35.37, 33.89, 35.50));
    }

    public function test_returns_null_when_no_route_found(): void
    {
        config(['services.mapbox.token' => 'test-token']);

        Http::fake(['api.mapbox.com/*' => Http::response(['code' => 'NoRoute', 'routes' => []], 200)]);

        $this->assertNull((new MapboxService())->directions(33.56, 35.37, 33.89, 35.50));
    }
}
