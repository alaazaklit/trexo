<?php

namespace App\Services\Pricing;

/**
 * Pure per-driver fare math (no DB/Eloquent), extracted out of
 * MatchesDriverSchedules::findMatchingDrivers() so it can be unit tested
 * without booting the app, and so the driver-facing "Test Price" simulator
 * (DriverProfileController::testPrice) prices a trip through the exact same
 * function a real order/reservation uses — not a second, hand-copied
 * formula that could quietly drift out of sync with it.
 */
class FareCalculator
{
    public static function calculate(
        float $baseFare,
        float $normalPricePerKm,
        float $sharedRidePricePerKm,
        float $detourSurchargePerKm,
        float $distanceKm,
        bool $onRoute,
        float $detourKm,
        float $reservationMultiplier,
        ?float $intercityFixedFare
    ): float {
        $price = $intercityFixedFare !== null
            ? $intercityFixedFare
            : $baseFare + ($distanceKm * ($onRoute ? $sharedRidePricePerKm : $normalPricePerKm));
        $price += $detourKm * $detourSurchargePerKm;
        $price *= $reservationMultiplier;

        return round($price, 2);
    }

    /**
     * Cross-zone/intercity guardrail. A trip that stays within one pricing
     * zone (or where zone info isn't available) keeps using that zone's own
     * rate unchanged — this only kicks in for a trip that genuinely crosses
     * from one resolved zone into a different one. Blindly charging the
     * pickup zone's own rate (often tuned low, for short local trips) across
     * the whole distance of a long cross-zone trip can badly underprice it;
     * this instead uses whichever of the pickup zone, destination zone, or
     * global rate is highest. Only ever consulted as a fallback — an
     * explicit intercity fixed fare (checked first, in calculate() above)
     * or a driver's own per-km override still both take priority over this
     * entirely, exactly as they did before this guardrail existed.
     *
     * $pickupZoneRate/$destinationZoneRate are expected to already be fully
     * resolved (zone-specific-or-global-fallback) via
     * MatchesDriverSchedules::getNormalPricePerKm() — this method makes no
     * assumption about *why* a rate is what it is, only picks between them.
     */
    public static function effectivePerKmRate(
        float $pickupZoneRate,
        float $destinationZoneRate,
        float $globalRate,
        bool $crossesZones
    ): float {
        if (!$crossesZones) {
            return $pickupZoneRate;
        }

        return max($pickupZoneRate, $destinationZoneRate, $globalRate);
    }

    /**
     * Resolves a driver's own distance-tiered price list (see the
     * driver_price_brackets table / driver_price_brackets_page.dart builder)
     * into the single per-km rate that applies to one specific trip
     * distance — the bracket analog of effectivePerKmRate(), consulted
     * *before* it (and before the flat price_per_km_override) at both real
     * call sites (MatchesDriverSchedules::findMatchingDrivers(),
     * DriverProfileController::testPrice()), since once a driver has
     * defined even one bracket it fully replaces their flat per-km rate for
     * real billing.
     *
     * Returns null when $brackets is empty — the one signal both call sites
     * use to fall through to the pre-existing flat-override/zone-guardrail
     * logic entirely unchanged, so a driver who has never touched the
     * bracket builder is unaffected by this method's existence.
     *
     * A distance inside more than one bracket's range can't happen (the
     * brackets endpoint rejects overlapping ranges on save), but a distance
     * outside every bracket the driver *did* define is expected — a driver
     * who has adopted brackets almost certainly hasn't enumerated every
     * possible trip length. Rather than silently reverting to a different
     * pricing scheme mid-range, this clamps to whichever edge bracket is
     * closest: the highest bracket's rate for anything beyond it, the
     * lowest bracket's rate for anything below it (only reachable if the
     * lowest bracket's own lower_km is above zero).
     *
     * @param array<int, array{lower_km: float, upper_km: float, price_per_km: float}> $brackets
     *        Plain arrays, not Eloquent models — keeps this method DB-agnostic
     *        like the rest of the class, and unit-testable without booting
     *        the app. Order doesn't matter; sorted internally.
     */
    public static function bracketPricePerKm(array $brackets, float $distanceKm): ?float
    {
        if (empty($brackets)) {
            return null;
        }

        usort($brackets, static fn (array $a, array $b) => $a['lower_km'] <=> $b['lower_km']);

        if ($distanceKm < $brackets[0]['lower_km']) {
            return (float) $brackets[0]['price_per_km'];
        }

        foreach ($brackets as $bracket) {
            if ($distanceKm < $bracket['upper_km']) {
                return (float) $bracket['price_per_km'];
            }
        }

        // Distance is at or beyond every bracket's upper bound — clamp to
        // the highest one rather than falling through to a different
        // pricing scheme for an unusually long trip.
        return (float) $brackets[count($brackets) - 1]['price_per_km'];
    }

    /**
     * A driver's normal per-km rate, increased by their own out-of-zone
     * percentage when a trip's destination falls outside their chosen
     * working zone (see MatchesDriverSchedules::findMatchingDrivers — the
     * decision of *whether* a trip counts as out-of-zone depends on DB zone
     * lookups and stays there; this is just the pure multiplication once
     * that's already been decided, kept separate so it's unit-testable
     * without booting the app).
     */
    public static function applyOutOfZonePercent(float $normalPricePerKm, float $outOfZonePercent): float
    {
        return $normalPricePerKm * (1 + $outOfZonePercent / 100);
    }

    /**
     * Rounds a whole-LBP amount to the nearest 20,000 LBP note. LBP has no
     * fractional unit in practice, so this works in integers throughout
     * rather than float — money should never be a float.
     */
    public static function roundToNearestLbp(int $lbp, int $incrementLbp = 20000): int
    {
        return (int) round($lbp / $incrementLbp) * $incrementLbp;
    }

    /**
     * Real trips (findMatchingDrivers()) keep every downstream calculation —
     * wallet, commission, refunds — in USD, so this rounds the fully-computed
     * final USD price to the nearest 20,000 LBP note (this app is cash-to-
     * driver, so that's the actual denomination handed over) and converts
     * it straight back to USD, rather than changing what currency anything
     * is stored in. Applied once, to the final price, regardless of whether
     * it came from the linear per-km formula, an intercity fixed fare, or
     * the cross-zone guardrail — matching the same final rounding Test
     * Price already applies, so a real trip and its Test Price preview
     * both land on the same round LBP number.
     */
    public static function roundPriceUsdToNearestLbpNote(float $priceUsd, float $lbpPerUsd, int $incrementLbp = 20000): float
    {
        $priceLbp = (int) round($priceUsd * $lbpPerUsd);
        $roundedLbp = self::roundToNearestLbp($priceLbp, $incrementLbp);

        return round($roundedLbp / $lbpPerUsd, 2);
    }
}
