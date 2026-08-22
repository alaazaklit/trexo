<?php

namespace Tests\Unit;

use App\Services\Pricing\FareCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Plain-PHPUnit unit tests (no app/DB boot — see phpunit.xml) for the pure
 * fare math shared by real-trip pricing (MatchesDriverSchedules) and the
 * driver-facing Test Price simulator (DriverProfileController::testPrice).
 */
class FareCalculatorTest extends TestCase
{
    // --- calculate() ---------------------------------------------------

    public function test_base_fare_plus_distance_at_the_normal_rate(): void
    {
        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: 1.2,
            sharedRidePricePerKm: 0.84,
            detourSurchargePerKm: 0.25,
            distanceKm: 8.5,
            onRoute: false,
            detourKm: 0.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: null,
        );

        $this->assertSame(12.7, $price);
    }

    public function test_on_route_uses_the_shared_ride_rate_instead_of_the_normal_rate(): void
    {
        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: 1.2,
            sharedRidePricePerKm: 0.84,
            detourSurchargePerKm: 0.25,
            distanceKm: 10.0,
            onRoute: true,
            detourKm: 0.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: null,
        );

        $this->assertSame(10.9, $price);
    }

    public function test_detour_surcharge_is_added_on_top_of_the_base_calculation(): void
    {
        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: 1.2,
            sharedRidePricePerKm: 0.84,
            detourSurchargePerKm: 0.5,
            distanceKm: 5.0,
            onRoute: false,
            detourKm: 2.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: null,
        );

        // (2.5 + 5*1.2) + 2*0.5 = 8.5 + 1 = 9.5
        $this->assertSame(9.5, $price);
    }

    public function test_reservation_multiplier_scales_the_whole_price(): void
    {
        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: 1.2,
            sharedRidePricePerKm: 0.84,
            detourSurchargePerKm: 0.25,
            distanceKm: 10.0,
            onRoute: false,
            detourKm: 0.0,
            reservationMultiplier: 2.0,
            intercityFixedFare: null,
        );

        // (2.5 + 10*1.2) * 2 = 14.5 * 2 = 29
        $this->assertSame(29.0, $price);
    }

    public function test_intercity_fixed_fare_short_circuits_the_distance_formula_entirely(): void
    {
        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: 1.2,
            sharedRidePricePerKm: 0.84,
            detourSurchargePerKm: 0.25,
            distanceKm: 120.0,
            onRoute: false,
            detourKm: 0.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: 45.0,
        );

        $this->assertSame(45.0, $price);
    }

    public function test_intercity_fixed_fare_still_gets_detour_and_reservation_multiplier_applied(): void
    {
        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: 1.2,
            sharedRidePricePerKm: 0.84,
            detourSurchargePerKm: 0.5,
            distanceKm: 120.0,
            onRoute: false,
            detourKm: 1.0,
            reservationMultiplier: 2.0,
            intercityFixedFare: 45.0,
        );

        // (45 + 1*0.5) * 2 = 45.5 * 2 = 91
        $this->assertSame(91.0, $price);
    }

    public function test_result_is_rounded_to_two_decimal_places(): void
    {
        $price = FareCalculator::calculate(
            baseFare: 2.333,
            normalPricePerKm: 1.111,
            sharedRidePricePerKm: 0.7,
            detourSurchargePerKm: 0.0,
            distanceKm: 3.0,
            onRoute: false,
            detourKm: 0.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: null,
        );

        $this->assertSame(5.67, $price);
    }

    public function test_a_cross_zone_trip_with_a_configured_intercity_fare_ignores_the_per_km_guardrail_entirely(): void
    {
        // The guardrail would pick 25.0 (the higher of the two zone rates)
        // as the per-km rate, but an explicit intercity fixed fare must
        // still short-circuit the whole distance formula, exactly as it did
        // before this guardrail existed.
        $effectiveRate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 10.0,
            destinationZoneRate: 25.0,
            globalRate: 12.0,
            crossesZones: true,
        );

        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: $effectiveRate,
            sharedRidePricePerKm: $effectiveRate * 0.7,
            detourSurchargePerKm: 0.25,
            distanceKm: 45.0,
            onRoute: false,
            detourKm: 0.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: 32.0,
        );

        $this->assertSame(32.0, $price);
    }

    public function test_a_cross_zone_trip_with_a_very_long_distance_does_not_blindly_use_the_low_pickup_rate(): void
    {
        // Pickup zone's own (low, local) rate alone would give:
        // 2.5 + 60*0.20 = 14.5 — badly underpriced for a 60km trip.
        $effectiveRate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 0.20,
            destinationZoneRate: 0.25,
            globalRate: 0.22,
            crossesZones: true,
        );

        $price = FareCalculator::calculate(
            baseFare: 2.5,
            normalPricePerKm: $effectiveRate,
            sharedRidePricePerKm: $effectiveRate * 0.7,
            detourSurchargePerKm: 0.0,
            distanceKm: 60.0,
            onRoute: false,
            detourKm: 0.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: null,
        );

        // 2.5 + 60*0.25 = 17.5, not 14.5.
        $this->assertSame(17.5, $price);
    }

    // --- bracketPricePerKm() -----------------------------------------------

    public function test_empty_bracket_list_returns_null(): void
    {
        $this->assertNull(FareCalculator::bracketPricePerKm([], 7.0));
    }

    public function test_distance_inside_a_bracket_uses_that_brackets_rate(): void
    {
        $brackets = [
            ['lower_km' => 0.0, 'upper_km' => 5.0, 'price_per_km' => 50000.0],
            ['lower_km' => 5.0, 'upper_km' => 10.0, 'price_per_km' => 40000.0],
        ];

        $this->assertSame(50000.0, FareCalculator::bracketPricePerKm($brackets, 3.0));
        $this->assertSame(40000.0, FareCalculator::bracketPricePerKm($brackets, 7.0));
    }

    public function test_distance_exactly_on_a_boundary_belongs_to_the_upper_bracket(): void
    {
        // upper_km is an exclusive bound — 5.0 belongs to [5,10), not [0,5).
        $brackets = [
            ['lower_km' => 0.0, 'upper_km' => 5.0, 'price_per_km' => 50000.0],
            ['lower_km' => 5.0, 'upper_km' => 10.0, 'price_per_km' => 40000.0],
        ];

        $this->assertSame(40000.0, FareCalculator::bracketPricePerKm($brackets, 5.0));
    }

    public function test_distance_beyond_the_highest_bracket_clamps_to_it(): void
    {
        $brackets = [
            ['lower_km' => 0.0, 'upper_km' => 5.0, 'price_per_km' => 50000.0],
            ['lower_km' => 5.0, 'upper_km' => 10.0, 'price_per_km' => 40000.0],
        ];

        $this->assertSame(40000.0, FareCalculator::bracketPricePerKm($brackets, 18.0));
    }

    public function test_distance_below_the_lowest_bracket_clamps_to_it(): void
    {
        // Only reachable when the driver's lowest defined bracket doesn't
        // start at 0.
        $brackets = [
            ['lower_km' => 5.0, 'upper_km' => 10.0, 'price_per_km' => 40000.0],
            ['lower_km' => 10.0, 'upper_km' => 15.0, 'price_per_km' => 35000.0],
        ];

        $this->assertSame(40000.0, FareCalculator::bracketPricePerKm($brackets, 2.0));
    }

    public function test_unsorted_input_still_resolves_correctly(): void
    {
        $brackets = [
            ['lower_km' => 10.0, 'upper_km' => 15.0, 'price_per_km' => 35000.0],
            ['lower_km' => 0.0, 'upper_km' => 5.0, 'price_per_km' => 50000.0],
            ['lower_km' => 5.0, 'upper_km' => 10.0, 'price_per_km' => 40000.0],
        ];

        $this->assertSame(40000.0, FareCalculator::bracketPricePerKm($brackets, 7.0));
    }

    public function test_single_bracket_driver_uses_it_for_any_distance(): void
    {
        $brackets = [['lower_km' => 0.0, 'upper_km' => 5.0, 'price_per_km' => 50000.0]];

        $this->assertSame(50000.0, FareCalculator::bracketPricePerKm($brackets, 1.0));
        $this->assertSame(50000.0, FareCalculator::bracketPricePerKm($brackets, 40.0));
    }

    // --- effectivePerKmRate() ---------------------------------------------

    public function test_same_zone_trip_keeps_using_the_pickup_zone_rate_unchanged(): void
    {
        $rate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 0.20,
            destinationZoneRate: 0.20,
            globalRate: 0.22,
            crossesZones: false,
        );

        $this->assertSame(0.20, $rate);
    }

    public function test_cross_zone_trip_uses_the_pickup_zone_rate_when_it_is_highest(): void
    {
        $rate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 0.30,
            destinationZoneRate: 0.20,
            globalRate: 0.22,
            crossesZones: true,
        );

        $this->assertSame(0.30, $rate);
    }

    public function test_cross_zone_trip_uses_the_destination_zone_rate_when_it_is_highest(): void
    {
        $rate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 0.20,
            destinationZoneRate: 0.30,
            globalRate: 0.22,
            crossesZones: true,
        );

        $this->assertSame(0.30, $rate);
    }

    public function test_cross_zone_trip_uses_the_global_rate_when_it_is_highest(): void
    {
        $rate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 0.17,
            destinationZoneRate: 0.20,
            globalRate: 0.25,
            crossesZones: true,
        );

        $this->assertSame(0.25, $rate);
    }

    public function test_matches_the_saida_beirut_worked_example_from_the_spec(): void
    {
        // Saida 10,000 LBP/km, Beirut 15,000 LBP/km, global 12,000 LBP/km
        // (expressed here in whole-LBP units directly since the guardrail
        // itself is currency-agnostic).
        $rate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 10000.0,
            destinationZoneRate: 15000.0,
            globalRate: 12000.0,
            crossesZones: true,
        );

        $this->assertSame(15000.0, $rate);
    }

    public function test_one_zone_with_no_configured_rate_already_resolved_to_global_by_the_caller(): void
    {
        // getNormalPricePerKm() already falls back to the global rate before
        // this method ever sees it when a zone has no rate column set — so
        // from here, "zone has no configured rate" and "zone rate equals
        // global" are indistinguishable, and correctly so.
        $rate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 0.20,
            destinationZoneRate: 0.22, // destination zone had no rate set, already resolved to global
            globalRate: 0.22,
            crossesZones: true,
        );

        $this->assertSame(0.22, $rate);
    }

    public function test_both_zones_with_no_configured_rate_preserves_existing_global_behavior(): void
    {
        $rate = FareCalculator::effectivePerKmRate(
            pickupZoneRate: 0.22,
            destinationZoneRate: 0.22,
            globalRate: 0.22,
            crossesZones: true,
        );

        $this->assertSame(0.22, $rate);
    }

    // --- roundToNearestLbp() ---------------------------------------------

    public function test_rounds_down_to_the_nearest_20000_when_closer_to_the_lower_note(): void
    {
        // Matches the feature's own worked example: 182,000 -> 180,000.
        $this->assertSame(180000, FareCalculator::roundToNearestLbp(182000));
    }

    public function test_rounds_up_to_the_nearest_20000_when_closer_to_the_higher_note(): void
    {
        $this->assertSame(200000, FareCalculator::roundToNearestLbp(190000));
    }

    public function test_exact_multiple_of_20000_is_unchanged(): void
    {
        $this->assertSame(160000, FareCalculator::roundToNearestLbp(160000));
    }

    public function test_a_value_below_the_first_increment_rounds_to_zero(): void
    {
        $this->assertSame(0, FareCalculator::roundToNearestLbp(9000));
    }

    /** @dataProvider roundingTableProvider */
    public function test_rounding_table_from_the_spec(int $calculated, int $expectedFinal): void
    {
        $this->assertSame($expectedFinal, FareCalculator::roundToNearestLbp($calculated));
    }

    public static function roundingTableProvider(): array
    {
        return [
            [182000, 180000],
            [190000, 200000],
            [229000, 220000],
            [230000, 240000],
            [500000, 500000],
            [1000000, 1000000],
        ];
    }

    // --- roundPriceUsdToNearestLbpNote() -----------------------------------
    // Real-trip prices (findMatchingDrivers()) round the same way Test
    // Price already does, converting back to USD so wallet/commission/
    // refund math downstream keeps working in USD unchanged.

    public function test_real_trip_price_matching_the_users_reported_example(): void
    {
        // 2.1676 USD * 89,500 LBP/USD = 194,000 LBP -> rounds to 200,000.
        $priceUsd = FareCalculator::roundPriceUsdToNearestLbpNote(2.1676, 89500.0);

        // 200,000 / 89,500 = 2.2346...
        $this->assertSame(2.23, $priceUsd);
    }

    public function test_real_trip_price_rounds_down_when_closer_to_the_lower_note(): void
    {
        // 2.0 USD * 89,500 = 179,000 LBP -> rounds to 180,000.
        $priceUsd = FareCalculator::roundPriceUsdToNearestLbpNote(2.0, 89500.0);

        $this->assertSame(round(180000 / 89500, 2), $priceUsd);
    }

    public function test_real_trip_price_used_by_an_intercity_fixed_fare_still_gets_rounded(): void
    {
        // The admin's $32 fixed fare converts to 2,864,000 LBP, which isn't
        // itself an exact multiple of 20,000 -- it still rounds, same as
        // any other final price, matching Test Price's own behavior.
        $priceUsd = FareCalculator::roundPriceUsdToNearestLbpNote(32.0, 89500.0);

        $this->assertSame(round(2860000 / 89500, 2), $priceUsd);
    }

    // --- applyOutOfZonePercent() --------------------------------------------
    // Pure multiplication used once MatchesDriverSchedules::findMatchingDrivers
    // has already decided a trip counts as out-of-zone (destination didn't
    // resolve to any pricing zone at all, driver has a chosen working zone,
    // and no intercity fixed fare applies) — the zone-lookup/eligibility
    // decision itself needs a DB and is covered by Feature tests instead.

    public function test_out_of_zone_percent_increases_the_per_km_rate(): void
    {
        // 50,000 LBP/km at +20% -> 60,000 LBP/km, matching the feature's own
        // worked example exactly.
        $rate = FareCalculator::applyOutOfZonePercent(50000.0, 20.0);

        $this->assertSame(60000.0, $rate);
    }

    public function test_out_of_zone_percent_of_zero_leaves_the_rate_unchanged(): void
    {
        $rate = FareCalculator::applyOutOfZonePercent(50000.0, 0.0);

        $this->assertSame(50000.0, $rate);
    }

    public function test_out_of_zone_surcharge_feeds_into_calculate_and_still_rounds_correctly(): void
    {
        // Driver: 200,000 LBP base + 50,000 LBP/km, 20% out-of-zone increase,
        // a 10km out-of-zone trip -> effective rate 60,000 LBP/km.
        $effectiveRate = FareCalculator::applyOutOfZonePercent(50000.0, 20.0);

        $price = FareCalculator::calculate(
            baseFare: 200000.0,
            normalPricePerKm: $effectiveRate,
            sharedRidePricePerKm: $effectiveRate * 0.7,
            detourSurchargePerKm: 0.0,
            distanceKm: 10.0,
            onRoute: false,
            detourKm: 0.0,
            reservationMultiplier: 1.0,
            intercityFixedFare: null,
        );

        // 200,000 + 10*60,000 = 800,000 -- already an exact multiple of
        // 20,000, so rounding is a no-op here.
        $this->assertSame(800000.0, $price);
        $this->assertSame(800000, FareCalculator::roundToNearestLbp((int) $price));
    }
}
