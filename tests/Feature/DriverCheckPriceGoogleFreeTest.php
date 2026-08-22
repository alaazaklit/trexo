<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Covers the driver-facing Check Price screen's core cost requirement:
 * POST /api/driver/pricing/test (DriverProfileController::testPrice()) must
 * generate zero requests to any Google Maps/Places/Routes endpoint,
 * regardless of what maps.places_provider/maps.directions_provider are set
 * to for the real customer flow — it always uses Mapbox for distance/
 * duration and city/region resolution, with a straight-line/null fallback
 * (never a fallback to Google) if Mapbox itself is unavailable.
 */
class DriverCheckPriceGoogleFreeTest extends TestCase
{
    use DatabaseTransactions;

    private function makeDriver(): User
    {
        $user = User::factory()->create([
            'phone' => '70' . random_int(100000, 999999),
            'type' => 'driver',
            'is_available' => true,
        ]);

        Driver::create(['user_id' => $user->id, 'approval_status' => 'approved']);

        $plan = SubscriptionPlan::first() ?? SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'monthly_price' => 0,
            'commission_percentage' => 10,
            'is_active' => true,
        ]);

        DriverSubscription::create([
            'driver_id' => Driver::where('user_id', $user->id)->value('id'),
            'plan_id' => $plan->id,
            'payment_status' => 'approved',
            'start_date' => now()->subDay(),
            'end_date' => null,
        ]);

        return $user;
    }

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    public function test_check_price_never_calls_google_even_with_google_as_the_configured_provider(): void
    {
        config(['services.mapbox.token' => 'test-token', 'services.google_maps.key' => 'test-google-key']);
        \TCG\Voyager\Models\Setting::updateOrCreate(['key' => 'maps.places_provider'], ['value' => 'google']);
        \TCG\Voyager\Models\Setting::updateOrCreate(['key' => 'maps.directions_provider'], ['value' => 'google']);

        Http::fake([
            'api.mapbox.com/directions/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 15000, 'duration' => 1200]],
            ], 200),
            'api.mapbox.com/geocoding/*' => Http::response(['features' => [[
                'id' => 'address.1',
                'place_type' => ['address'],
                'text' => 'Test Rd',
                'place_name' => 'Test Rd, Saida, Lebanon',
                'center' => [35.37, 33.56],
                'context' => [['id' => 'place.1', 'text' => 'Saida']],
            ]]], 200),
        ]);

        $driver = $this->makeDriver();

        $response = $this->withHeaders($this->authHeaders($driver))->postJson('/api/driver/pricing/test', [
            'pickup_lat' => 33.56,
            'pickup_lng' => 35.37,
            'destination_lat' => 33.89,
            'destination_lng' => 35.50,
            'order_type' => 0,
        ]);

        $response->assertStatus(200)->assertJson(['result' => true]);
        $this->assertEquals(15.0, $response->json('data.distance_km'));
        $this->assertSame(20, $response->json('data.duration_minutes'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_check_price_falls_back_to_straight_line_distance_when_mapbox_fails_never_google(): void
    {
        config(['services.mapbox.token' => 'test-token', 'services.google_maps.key' => 'test-google-key']);

        Http::fake([
            'api.mapbox.com/*' => Http::response(['message' => 'invalid token'], 401),
        ]);

        $driver = $this->makeDriver();

        $response = $this->withHeaders($this->authHeaders($driver))->postJson('/api/driver/pricing/test', [
            'pickup_lat' => 33.56,
            'pickup_lng' => 35.37,
            'destination_lat' => 33.89,
            'destination_lng' => 35.50,
            'order_type' => 0,
        ]);

        $response->assertStatus(200)->assertJson(['result' => true]);
        $this->assertNull($response->json('data.duration_minutes'));
        // Falls back to the haversine straight-line distance, not zero/null.
        $this->assertGreaterThan(0, $response->json('data.distance_km'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }
}
