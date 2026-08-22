<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverPriceBracket;
use App\Models\DriverSubscription;
use App\Models\PricingZone;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Covers FareCalculator::bracketPricePerKm() wired into the real driver-
 * matching endpoint (getOrderDrivers -> findMatchingDrivers): once a driver
 * has at least one row in driver_price_brackets, it fully replaces their
 * flat price_per_km_override for real billing (per_km_rate in the response
 * below is exactly $normalPricePerKm from MatchesDriverSchedules — see its
 * docblock). A driver with zero brackets is priced exactly as before this
 * feature existed. Helper methods mirror OutOfZonePricingTest's fixtures.
 */
class DriverPriceBracketPricingTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        $token = JWTAuth::fromUser($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    private function makeZone(string $keyword, float $baseFareTaxi = 1.25, float $perKmTaxi = 0.20): PricingZone
    {
        return PricingZone::create([
            'name' => "Test Zone ({$keyword})",
            'keywords' => $keyword,
            'base_fare_taxi' => $baseFareTaxi,
            'base_fare_delivery' => $baseFareTaxi + 0.25,
            'per_km_taxi' => $perKmTaxi,
            'per_km_delivery' => $perKmTaxi - 0.03,
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    private function makeDriver(array $overrides = []): array
    {
        static $counter = 0;
        $counter++;

        $user = User::factory()->create([
            'phone' => '7100' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'type' => 'driver',
            'is_available' => true,
        ]);

        $driver = Driver::create(array_merge([
            'user_id' => $user->id,
            'approval_status' => 'approved',
        ], $overrides));

        $plan = SubscriptionPlan::first() ?? SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'monthly_price' => 0,
            'commission_percentage' => 10,
            'is_active' => true,
        ]);

        DriverSubscription::create([
            'driver_id' => $driver->id,
            'plan_id' => $plan->id,
            'payment_status' => 'approved',
            'start_date' => now()->subDay(),
            'end_date' => null,
        ]);

        return [$user, $driver];
    }

    private function makeOrder(User $seller, array $pickup, array $destination, float $routeDistanceKm): Order
    {
        $order = Order::create([
            'user_id' => $seller->id,
            'order_kind' => 'taxi',
            'status' => 'pending_driver_selection',
            'route_distance_km' => $routeDistanceKm,
        ]);

        \DB::table('addresses')->insert([
            [
                'user_id' => $seller->id,
                'order_id' => $order->id,
                'direction' => 'start_address',
                'address_line1' => $pickup['label'],
                'city' => $pickup['city'],
                'region' => $pickup['region'] ?? null,
                'latitude' => $pickup['lat'],
                'longitude' => $pickup['lng'],
                'state' => '',
                'postal_code' => '',
                'country' => 'Lebanon',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $seller->id,
                'order_id' => $order->id,
                'direction' => 'destination_address',
                'address_line1' => $destination['label'],
                'city' => $destination['city'],
                'region' => $destination['region'] ?? null,
                'latitude' => $destination['lat'],
                'longitude' => $destination['lng'],
                'state' => '',
                'postal_code' => '',
                'country' => 'Lebanon',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return $order;
    }

    private function findDriverEntry(array $responseDrivers, int $driverUserId): ?array
    {
        foreach ($responseDrivers as $entry) {
            if ((int) $entry['driver_id'] === $driverUserId) {
                return $entry;
            }
        }

        return null;
    }

    public function test_distance_inside_a_bracket_uses_that_brackets_rate_over_the_flat_override(): void
    {
        $seller = User::factory()->create(['phone' => '72000001']);
        $zone = $this->makeZone('Saida');
        [$driverUser, $driver] = $this->makeDriver([
            'pricing_zone_id' => $zone->id,
            // Deliberately different from either bracket's rate, to prove
            // it's ignored once brackets exist.
            'price_per_km_override' => 0.99,
        ]);

        DriverPriceBracket::create(['user_id' => $driverUser->id, 'lower_km' => 0, 'upper_km' => 5, 'tier_total_price' => 2.0, 'price_per_km' => 0.30]);
        DriverPriceBracket::create(['user_id' => $driverUser->id, 'lower_km' => 5, 'upper_km' => 10, 'tier_total_price' => 3.0, 'price_per_km' => 0.22]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Saida dropoff', 'city' => 'Saida', 'lat' => 33.60, 'lng' => 35.40],
            routeDistanceKm: 7.0,
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(0.22, (float) $entry['per_km_rate'], 0.0001);
    }

    public function test_distance_beyond_every_bracket_clamps_to_the_highest_ones_rate(): void
    {
        $seller = User::factory()->create(['phone' => '72000002']);
        $zone = $this->makeZone('Saida');
        [$driverUser, $driver] = $this->makeDriver([
            'pricing_zone_id' => $zone->id,
            'price_per_km_override' => 0.99,
        ]);

        DriverPriceBracket::create(['user_id' => $driverUser->id, 'lower_km' => 0, 'upper_km' => 5, 'tier_total_price' => 2.0, 'price_per_km' => 0.30]);
        DriverPriceBracket::create(['user_id' => $driverUser->id, 'lower_km' => 5, 'upper_km' => 10, 'tier_total_price' => 3.0, 'price_per_km' => 0.22]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Far dropoff', 'city' => 'Saida', 'lat' => 33.80, 'lng' => 35.60],
            routeDistanceKm: 20.0,
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(0.22, (float) $entry['per_km_rate'], 0.0001);
    }

    public function test_driver_with_zero_brackets_is_priced_exactly_as_before(): void
    {
        $seller = User::factory()->create(['phone' => '72000003']);
        $zone = $this->makeZone('Saida');
        [$driverUser, $driver] = $this->makeDriver([
            'pricing_zone_id' => $zone->id,
            'price_per_km_override' => 0.35,
        ]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Saida dropoff', 'city' => 'Saida', 'lat' => 33.60, 'lng' => 35.40],
            routeDistanceKm: 7.0,
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(0.35, (float) $entry['per_km_rate'], 0.0001);
    }
}
