<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverPriceBracket;
use App\Models\PricingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Covers DriverProfileController::getPriceBrackets()/updatePriceBrackets() —
 * the CRUD endpoints behind driver_price_brackets_page.dart. Real per-km
 * pricing behavior once brackets exist is covered separately by
 * DriverPriceBracketPricingTest.
 */
class DriverPriceBracketsEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        $token = JWTAuth::fromUser($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    private function makeDriverUser(array $overrides = []): array
    {
        static $counter = 0;
        $counter++;

        $user = User::factory()->create([
            'phone' => '7300' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'type' => 'driver',
        ]);
        $driver = Driver::create(array_merge(['user_id' => $user->id, 'approval_status' => 'approved'], $overrides));

        return [$user, $driver];
    }

    public function test_get_returns_zones_with_hub_coordinates_and_the_drivers_own_brackets(): void
    {
        $zone = PricingZone::create([
            'name' => 'Test Zone (Saida)',
            'keywords' => 'Saida',
            'hub_lat' => 33.5571,
            'hub_lng' => 35.3734,
            'base_fare_taxi' => 1.25,
            'per_km_taxi' => 0.20,
            'priority' => 10,
            'is_active' => true,
        ]);

        [$user, $driver] = $this->makeDriverUser(['pricing_zone_id' => $zone->id]);
        DriverPriceBracket::create(['user_id' => $user->id, 'lower_km' => 0, 'upper_km' => 5, 'tier_total_price' => 2.0, 'price_per_km' => 0.30]);

        $response = $this->getJson('/api/driver/pricing/brackets', $this->authHeaders($user));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data['brackets']);
        $this->assertNotEmpty($data['zones']);
        $matchingZone = collect($data['zones'])->firstWhere('id', $zone->id);
        $this->assertNotNull($matchingZone['hub_lat']);
        $this->assertNotNull($matchingZone['hub_lng']);
    }

    public function test_post_replaces_the_full_bracket_list(): void
    {
        [$user, $driver] = $this->makeDriverUser();
        DriverPriceBracket::create(['user_id' => $user->id, 'lower_km' => 0, 'upper_km' => 5, 'tier_total_price' => 1.0, 'price_per_km' => 0.99]);

        $response = $this->postJson('/api/driver/pricing/brackets', [
            'brackets' => [
                ['lower_km' => 0, 'upper_km' => 5, 'reference_text' => 'Haret Saida, Abra أو ما يعادلها في نفس النطاق', 'tier_total_price' => 2.79, 'price_per_km' => 0.30, 'anchor_distance_km' => 3.8],
                ['lower_km' => 5, 'upper_km' => 10, 'tier_total_price' => 4.47, 'price_per_km' => 0.22],
            ],
        ], $this->authHeaders($user));

        $response->assertStatus(200);
        $this->assertCount(2, DriverPriceBracket::where('user_id', $user->id)->get());
        $this->assertDatabaseMissing('driver_price_brackets', ['user_id' => $user->id, 'price_per_km' => 0.99]);
    }

    public function test_post_rejects_overlapping_ranges(): void
    {
        [$user, $driver] = $this->makeDriverUser();

        $response = $this->postJson('/api/driver/pricing/brackets', [
            'brackets' => [
                ['lower_km' => 0, 'upper_km' => 6, 'tier_total_price' => 2.0, 'price_per_km' => 0.30],
                ['lower_km' => 5, 'upper_km' => 10, 'tier_total_price' => 3.0, 'price_per_km' => 0.22],
            ],
        ], $this->authHeaders($user));

        $response->assertStatus(422);
        $this->assertSame(0, DriverPriceBracket::where('user_id', $user->id)->count());
    }

    public function test_post_with_empty_array_clears_all_brackets(): void
    {
        [$user, $driver] = $this->makeDriverUser();
        DriverPriceBracket::create(['user_id' => $user->id, 'lower_km' => 0, 'upper_km' => 5, 'tier_total_price' => 1.0, 'price_per_km' => 0.30]);

        $response = $this->postJson('/api/driver/pricing/brackets', ['brackets' => []], $this->authHeaders($user));

        $response->assertStatus(200);
        $this->assertSame(0, DriverPriceBracket::where('user_id', $user->id)->count());
    }
}
