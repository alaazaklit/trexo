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
