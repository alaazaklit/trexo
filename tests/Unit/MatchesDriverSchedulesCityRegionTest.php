<?php

namespace Tests\Unit;

use App\Traits\MatchesDriverSchedules;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers resolveCityRegion() — used only by DriverProfileController::
 * testPrice() (the driver-facing Check Price screen), which is required to
 * generate zero Google Maps/Places requests. Confirms it's hardcoded to
 * Mapbox with no fallback to Google, unlike MapProxyController's
 * Setting-driven Places/Geocoding endpoints (covered by
 * MapProxyPlacesProviderTest), which serve the real customer flow and are
 * allowed to fall back to Google on a Mapbox outage.
 */
class MatchesDriverSchedulesCityRegionTest extends TestCase
{
    private function harness(): object
    {
        return new class {
            use MatchesDriverSchedules;

            public function resolve(float $lat, float $lng): array
            {
                return $this->resolveCityRegion($lat, $lng);
            }
        };
    }

    private function fakeMapboxFeature(): array
    {
        return [
            'id' => 'address.999',
            'place_type' => ['address'],
            'text' => 'Main Street',
            'address' => '5',
            'place_name' => '5 Main Street, Saida, Lebanon',
            'center' => [35.37, 33.56],
            'context' => [
                ['id' => 'place.1', 'text' => 'Saida'],
                ['id' => 'region.1', 'text' => 'South Governorate'],
                ['id' => 'country.1', 'text' => 'Lebanon', 'short_code' => 'lb'],
            ],
        ];
    }

    public function test_resolves_via_mapbox(): void
    {
        config(['services.mapbox.token' => 'test-token', 'services.google_maps.key' => 'test-google-key']);

        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeMapboxFeature()]], 200),
        ]);

        $result = $this->harness()->resolve(33.56, 35.37);

        $this->assertSame('Saida', $result['city']);
        $this->assertSame('South Governorate', $result['region']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_never_calls_google_when_mapbox_fails(): void
    {
        config(['services.mapbox.token' => 'test-token', 'services.google_maps.key' => 'test-google-key']);

        Http::fake([
            'api.mapbox.com/*' => Http::response(['message' => 'invalid token'], 401),
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [['address_components' => [
                    ['long_name' => 'Beirut', 'types' => ['locality']],
                ]]],
            ], 200),
        ]);

        $result = $this->harness()->resolve(33.89, 35.50);

        // No fallback to Google at all — a Mapbox failure just yields nulls,
        // it never reaches for the Google response faked above.
        $this->assertNull($result['city']);
        $this->assertNull($result['region']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_never_calls_google_when_mapbox_token_is_not_configured(): void
    {
        config(['services.mapbox.token' => null, 'services.google_maps.key' => 'test-google-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'results' => []], 200),
        ]);

        $result = $this->harness()->resolve(33.56, 35.37);

        $this->assertNull($result['city']);
        $this->assertNull($result['region']);
        Http::assertNothingSent();
    }
}
