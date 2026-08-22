<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverSubscription;
use App\Models\PricingZone;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Covers the out-of-zone per-km surcharge end to end: a driver who's chosen
 * a working zone gets their normal per-km rate increased by their own
 * out_of_zone_percent_override whenever a trip's destination doesn't
 * resolve to any pricing zone at all (the Kfar Filo / Majdelyoun case — an
 * unlisted village name from Google, not a registered *different* zone,
 * which is a separate, unchanged case still handled by the existing
 * eligibility check / intercity routes).
 */
class OutOfZonePricingTest extends TestCase
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
            'phone' => '7000' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
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

    private function makeOrder(User $seller, array $pickup, array $destination): Order
    {
        $order = Order::create([
            'user_id' => $seller->id,
            'order_kind' => 'taxi',
            'status' => 'pending_driver_selection',
            'route_distance_km' => 10.0,
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

    // $driverId here is the driver's *user* id — the API's `driver_id`
    // field is aliased from `users.id` (see MatchesDriverSchedules'
    // `'users.id as driver_id'` select), not the `drivers` table's own
    // primary key, matching how ChooseDriver/ChooseReservationDriver
    // resolve it via User::find($driver_id) too.
    private function findDriverEntry(array $responseDrivers, int $driverUserId): ?array
    {
        foreach ($responseDrivers as $entry) {
            if ((int) $entry['driver_id'] === $driverUserId) {
                return $entry;
            }
        }

        return null;
    }

    // 1. Pickup and destination inside zone -> normal price.
    public function test_pickup_and_destination_inside_zone_uses_normal_zone_rate(): void
    {
        $seller = User::factory()->create(['phone' => '71000001']);
        $this->makeZone('Saida');
        [$driverUser] = $this->makeDriver(['pricing_zone_id' => PricingZone::first()->id]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Saida dropoff', 'city' => 'Saida', 'lat' => 33.57, 'lng' => 35.39],
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $response->assertStatus(200);

        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);
        $this->assertNotNull($entry);
        $this->assertFalse($entry['is_out_of_zone']);
        $this->assertSame(0.0, (float) $entry['out_of_zone_percent']);
        $this->assertEqualsWithDelta(0.20, (float) $entry['effective_per_km_rate'], 0.0001);
    }

    // 2. Pickup inside zone, destination outside (no zone match at all) ->
    // out-of-zone price.
    public function test_destination_matching_no_zone_at_all_applies_out_of_zone_surcharge(): void
    {
        $seller = User::factory()->create(['phone' => '71000002']);
        $zone = $this->makeZone('Saida', perKmTaxi: 0.20);
        [$driverUser] = $this->makeDriver([
            'pricing_zone_id' => $zone->id,
            'out_of_zone_percent_override' => 25.0,
        ]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            // "Kfar Filo" deliberately matches no zone's keywords.
            ['label' => 'Kfar Filo', 'city' => 'Kfar Filo', 'lat' => 33.60, 'lng' => 35.45],
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $response->assertStatus(200);

        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);
        $this->assertNotNull($entry);
        $this->assertTrue($entry['is_out_of_zone']);
        $this->assertEqualsWithDelta(25.0, (float) $entry['out_of_zone_percent'], 0.0001);
        // 0.20 * 1.25 = 0.25
        $this->assertEqualsWithDelta(0.25, (float) $entry['effective_per_km_rate'], 0.0001);
    }

    // 3 & 10. Driver custom Base Fare + custom Per KM -> respected, and not
    // silently overridden by any hardcoded/global fallback.
    public function test_driver_custom_base_fare_and_per_km_are_respected(): void
    {
        $seller = User::factory()->create(['phone' => '71000003']);
        $this->makeZone('Saida');
        [$driverUser] = $this->makeDriver([
            'pricing_zone_id' => PricingZone::first()->id,
            'base_fare_override' => 9.99,
            'price_per_km_override' => 3.33,
        ]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Saida dropoff', 'city' => 'Saida', 'lat' => 33.57, 'lng' => 35.39],
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(9.99, (float) $entry['base_fare'], 0.0001);
        $this->assertEqualsWithDelta(3.33, (float) $entry['per_km_rate'], 0.0001);
        // Nowhere near the $2.50/$1.20 hardcoded global fallback nor the
        // zone's own $1.25/$0.20 -- confirms the override, not some other
        // fallback layer, is what actually won.
        $this->assertNotEqualsWithDelta(2.50, (float) $entry['base_fare'], 0.0001);
        $this->assertNotEqualsWithDelta(1.25, (float) $entry['base_fare'], 0.0001);
    }

    // base_fare_override / price_per_km_override / out_of_zone_percent_override
    // are no longer bounded to an admin-computed ±% envelope — a value far
    // outside the old range must be accepted, while a plain sanity floor
    // (non-negative) still applies.
    public function test_simple_pricing_fields_accept_values_outside_the_old_bounds_envelope(): void
    {
        $zone = $this->makeZone('Saida', baseFareTaxi: 1.25, perKmTaxi: 0.20);
        [$driverUser] = $this->makeDriver(['pricing_zone_id' => $zone->id]);

        // Zone default is 1.25/0.20 -- the old ±20% envelope would have
        // rejected 50.0/10.0 outright.
        $this->postJson('/api/driver/pricing', [
            'base_fare_override' => 50.0,
            'price_per_km_override' => 10.0,
            'out_of_zone_percent_override' => 500.0,
        ], $this->authHeaders($driverUser))->assertStatus(201);

        $saved = $this->getJson('/api/driver/pricing', $this->authHeaders($driverUser));
        $this->assertEqualsWithDelta(50.0, (float) $saved->json('data.base_fare_override'), 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $saved->json('data.price_per_km_override'), 0.0001);
        $this->assertEqualsWithDelta(500.0, (float) $saved->json('data.out_of_zone_percent_override'), 0.0001);

        // A negative value still isn't accepted -- that's a basic sanity
        // floor, not the removed allowed-range restriction.
        $this->postJson('/api/driver/pricing', [
            'base_fare_override' => -5.0,
        ], $this->authHeaders($driverUser))->assertStatus(422);
    }

    // 4. Driver has no custom price -> zone defaults used.
    public function test_no_custom_price_falls_back_to_zone_defaults(): void
    {
        $seller = User::factory()->create(['phone' => '71000004']);
        $zone = $this->makeZone('Saida', baseFareTaxi: 1.40, perKmTaxi: 0.22);
        [$driverUser] = $this->makeDriver(['pricing_zone_id' => $zone->id]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Saida dropoff', 'city' => 'Saida', 'lat' => 33.57, 'lng' => 35.39],
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(1.40, (float) $entry['base_fare'], 0.0001);
        $this->assertEqualsWithDelta(0.22, (float) $entry['per_km_rate'], 0.0001);
    }

    // 5. Changing zone before saving custom pricing -> zone defaults update
    // (getOverrideBounds/getPricingOverrides reflect the newly chosen zone).
    public function test_changing_zone_before_any_custom_price_updates_the_shown_default(): void
    {
        $zoneA = $this->makeZone('Saida', baseFareTaxi: 1.25, perKmTaxi: 0.20);
        $zoneB = $this->makeZone('Beirut', baseFareTaxi: 1.50, perKmTaxi: 0.25);
        [$driverUser, $driver] = $this->makeDriver(['pricing_zone_id' => $zoneA->id]);

        $before = $this->getJson('/api/driver/pricing', $this->authHeaders($driverUser));
        $this->assertEqualsWithDelta(1.25, (float) $before->json('data.bounds.base_fare.default_taxi'), 0.0001);

        $this->postJson('/api/driver/pricing', ['pricing_zone_id' => $zoneB->id], $this->authHeaders($driverUser))
            ->assertStatus(201);

        $after = $this->getJson('/api/driver/pricing', $this->authHeaders($driverUser));
        $this->assertEqualsWithDelta(1.50, (float) $after->json('data.bounds.base_fare.default_taxi'), 0.0001);
        // No override was ever set, so it must still read back as null.
        $this->assertNull($after->json('data.base_fare_override'));
    }

    // 6. Changing zone after saving custom pricing -> custom pricing is
    // preserved (pre-existing behavior, unmodified by this feature).
    public function test_changing_zone_after_saving_custom_pricing_preserves_it(): void
    {
        $zoneA = $this->makeZone('Saida');
        $zoneB = $this->makeZone('Beirut', baseFareTaxi: 1.50, perKmTaxi: 0.25);
        [$driverUser] = $this->makeDriver(['pricing_zone_id' => $zoneA->id]);

        $this->postJson('/api/driver/pricing', [
            'base_fare_override' => 1.33,
            'price_per_km_override' => 0.21,
        ], $this->authHeaders($driverUser))->assertStatus(201);

        $this->postJson('/api/driver/pricing', ['pricing_zone_id' => $zoneB->id], $this->authHeaders($driverUser))
            ->assertStatus(201);

        $after = $this->getJson('/api/driver/pricing', $this->authHeaders($driverUser));
        $this->assertEqualsWithDelta(1.33, (float) $after->json('data.base_fare_override'), 0.0001);
        $this->assertEqualsWithDelta(0.21, (float) $after->json('data.price_per_km_override'), 0.0001);
    }

    // 7. Out-of-Zone percentage = 0% -> same as normal per-km rate.
    public function test_zero_percent_out_of_zone_override_leaves_rate_unchanged(): void
    {
        $seller = User::factory()->create(['phone' => '71000007']);
        $zone = $this->makeZone('Saida', perKmTaxi: 0.20);
        [$driverUser] = $this->makeDriver([
            'pricing_zone_id' => $zone->id,
            'out_of_zone_percent_override' => 0.0,
        ]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Kfar Filo', 'city' => 'Kfar Filo', 'lat' => 33.60, 'lng' => 35.45],
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);

        $this->assertNotNull($entry);
        $this->assertTrue($entry['is_out_of_zone']);
        $this->assertEqualsWithDelta(0.20, (float) $entry['effective_per_km_rate'], 0.0001);
        $this->assertEqualsWithDelta(0.20, (float) $entry['per_km_rate'], 0.0001);
    }

    // 9. Existing completed/chosen trip's price snapshot does not change
    // after the driver edits their pricing afterward.
    public function test_choosing_a_driver_freezes_the_pricing_snapshot_against_later_changes(): void
    {
        $seller = User::factory()->create(['phone' => '71000009']);
        $zone = $this->makeZone('Saida', baseFareTaxi: 1.25, perKmTaxi: 0.20);
        [$driverUser] = $this->makeDriver(['pricing_zone_id' => $zone->id]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Saida dropoff', 'city' => 'Saida', 'lat' => 33.57, 'lng' => 35.39],
        );

        $listing = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($listing->json('data'), $driverUser->id);
        $this->assertNotNull($entry);

        $this->postJson('/api/ChooseDriver', [
            'order_id' => $order->id,
            'driver_id' => $driverUser->id,
            'price' => $entry['price'],
            'base_fare' => $entry['base_fare'],
            'per_km_rate' => $entry['per_km_rate'],
            'effective_per_km_rate' => $entry['effective_per_km_rate'],
            'out_of_zone_percent' => $entry['out_of_zone_percent'],
            'is_out_of_zone' => $entry['is_out_of_zone'],
            'pricing_zone_id' => $entry['pricing_zone_id'],
        ], $this->authHeaders($seller))->assertStatus(201);

        $frozenBaseFare = $order->fresh()->base_fare;
        $frozenPerKm = $order->fresh()->per_km_rate;
        $frozenPrice = $order->fresh()->price;

        // Driver drastically changes their own pricing after being chosen —
        // base_fare_override/price_per_km_override are unbounded now (no
        // admin-computed envelope restricts these two anymore), so any
        // value is accepted; the point of this test is that it doesn't
        // matter, since the order's own snapshot is already frozen.
        $this->postJson('/api/driver/pricing', [
            'base_fare_override' => 50.0,
            'price_per_km_override' => 10.0,
        ], $this->authHeaders($driverUser))->assertStatus(201);

        $order->refresh();
        $this->assertEqualsWithDelta((float) $frozenBaseFare, (float) $order->base_fare, 0.0001);
        $this->assertEqualsWithDelta((float) $frozenPerKm, (float) $order->per_km_rate, 0.0001);
        $this->assertEqualsWithDelta((float) $frozenPrice, (float) $order->price, 0.0001);
        $this->assertNotEqualsWithDelta(50.0, (float) $order->base_fare, 0.0001);
    }

    // 8. Price is rounded to the nearest 20,000 LBP (using the default
    // 89,500 LBP/USD exchange rate this app seeds with).
    public function test_returned_price_converts_to_a_multiple_of_20000_lbp(): void
    {
        $seller = User::factory()->create(['phone' => '71000008']);
        $this->makeZone('Saida', baseFareTaxi: 1.25, perKmTaxi: 0.20);
        [$driverUser] = $this->makeDriver(['pricing_zone_id' => PricingZone::first()->id]);

        $order = $this->makeOrder(
            $seller,
            ['label' => 'Saida pickup', 'city' => 'Saida', 'lat' => 33.56, 'lng' => 35.38],
            ['label' => 'Saida dropoff', 'city' => 'Saida', 'lat' => 33.57, 'lng' => 35.39],
        );

        $response = $this->getJson('/api/getOrderDrivers/' . $order->id, $this->authHeaders($seller));
        $entry = $this->findDriverEntry($response->json('data'), $driverUser->id);
        $this->assertNotNull($entry);

        $lbp = (float) $entry['price'] * 89500.0;
        $nearestMultiple = round($lbp / 20000) * 20000;
        // The USD price is itself already rounded to an LBP multiple of
        // 20,000 server-side, then converted back to USD and rounded to 2
        // decimal places for storage/display — that last step re-quantizes
        // to whole-cent USD steps (~895 LBP each at this rate), reopening a
        // gap of up to roughly half that (~450 LBP) versus the original
        // exact multiple. A wide tolerance still confirms the rounding
        // actually happened (a totally unrounded price would be off by much
        // more) without asserting more precision than 2-decimal-USD storage
        // can actually preserve.
        $this->assertEqualsWithDelta($nearestMultiple, $lbp, 500.0);
    }
}
