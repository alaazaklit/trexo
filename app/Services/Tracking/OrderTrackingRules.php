<?php

namespace App\Services\Tracking;

/**
 * Pure, framework-agnostic tracking rules: adaptive arrival radius,
 * consecutive-confirmation bookkeeping, status-transition validation, and
 * driver-inactivity classification.
 *
 * Deliberately has no Eloquent/Facade/config() calls of its own — every
 * tunable value is passed in by the caller (OrderController reads them from
 * config/tracking.php) — so this class can be exercised with plain PHPUnit
 * (see tests/Unit/OrderTrackingRulesTest.php) without booting the framework
 * or touching a database. This app's Unit suite boots no app/DB (phpunit.xml
 * bootstraps only vendor/autoload.php), so a class that only makes sense
 * with `config()`/Eloquent available would be untestable there.
 */
class OrderTrackingRules
{
    // The state machine a delivery/taxi order actually walks through end to
    // end. Mirrors the ad hoc rules that were previously scattered across
    // OrderController::updateOrderStatus (the driver_rejected/request_expired
    // special cases, the terminal-status guard, the "reopen after failed
    // delivery" carve-out) — collected into one explicit map so the server
    // can reject a transition it was previously just trusting the client to
    // never request (e.g. the manual status picker offering every
    // post-accept status regardless of the order's current one, which let a
    // driver jump straight from `on_way` to `delivered`, skipping `picked_up`
    // entirely).
    private const VALID_TRANSITIONS = [
        'waiting_driver_response' => ['on_way', 'driver_rejected', 'request_expired', 'canceled'],
        // Deliberately excludes `in_transit`/`delivered` — those must go
        // through `picked_up` first, not be reachable directly from
        // `on_way`.
        'on_way' => ['picked_up', 'canceled', 'failed_delivery'],
        'picked_up' => ['in_transit', 'delivered', 'failed_delivery', 'canceled'],
        'in_transit' => ['delivered', 'failed_delivery', 'canceled'],
        'driver_rejected' => ['canceled'],
        'request_expired' => ['canceled'],
        'failed_delivery' => ['canceled'],
        // 'delivered' and 'canceled' are terminal — no outgoing transitions.
    ];

    /**
     * Whether the order can move from $from to $to. A no-op ($from === $to)
     * is treated as valid here — it's not a state-machine violation, just a
     * duplicate request, which the caller is expected to detect separately
     * via isDuplicate() and short-circuit before any side effects run.
     */
    public static function isValidTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    public static function isDuplicate(string $from, string $to): bool
    {
        return $from === $to;
    }

    /**
     * Adaptive arrival radius (in kilometres) derived from the driver's last
     * reported GPS accuracy (metres, geolocator's `Position.accuracy`). A
     * precise fix earns a tighter radius so "arrived" isn't declared from
     * further away than necessary; a poor or missing fix falls back to the
     * most forgiving radius so a genuine arrival isn't missed just because
     * the phone's GPS is noisy indoors/under cover.
     *
     * Replaces the old single fixed 50m (then 150m) radius, which was
     * either too tight for noisy GPS (arrival never detected) or too loose
     * for a driver with a precise fix (arrival declared too early) —
     * there's no one constant that's right for every accuracy level.
     */
    public static function arrivalRadiusKm(
        ?float $accuracyMeters,
        float $accurateThresholdM,
        float $accurateRadiusM,
        float $moderateThresholdM,
        float $moderateRadiusM,
        float $poorRadiusM
    ): float {
        if ($accuracyMeters === null) {
            return $poorRadiusM / 1000;
        }

        if ($accuracyMeters <= $accurateThresholdM) {
            return $accurateRadiusM / 1000;
        }

        if ($accuracyMeters <= $moderateThresholdM) {
            return $moderateRadiusM / 1000;
        }

        return $poorRadiusM / 1000;
    }

    /**
     * Advances (or resets) the consecutive-inside-radius counter for one
     * GPS ping and says whether that's enough to auto-transition the order.
     *
     * A single ping inside the radius no longer auto-advances the order —
     * one noisy/bounced GPS fix right at the edge of the radius used to be
     * enough to flip the status, which could fire on a driver just passing
     * close by rather than genuinely arriving. Requiring several
     * consecutive in-radius pings in a row (and resetting to zero the
     * moment the driver reads outside the radius) means only a sustained
     * arrival trips it.
     *
     * @return array{count: int, shouldTransition: bool}
     */
    public static function nextConfirmationState(
        bool $withinRadius,
        int $currentCount,
        int $requiredConfirmations
    ): array {
        if (!$withinRadius) {
            return ['count' => 0, 'shouldTransition' => false];
        }

        $next = $currentCount + 1;

        return [
            'count' => $next,
            'shouldTransition' => $next >= max(1, $requiredConfirmations),
        ];
    }

    /**
     * Classifies how long it's been since the driver's last location ping,
     * for the "driver went quiet mid-trip" case a background service
     * silently dying can't otherwise surface. Returns null while the driver
     * is still pinging normally.
     */
    public static function inactivityLevel(
        int $secondsSinceLastUpdate,
        int $warningSeconds,
        int $staleSeconds
    ): ?string {
        if ($secondsSinceLastUpdate >= $staleSeconds) {
            return 'stale';
        }

        if ($secondsSinceLastUpdate >= $warningSeconds) {
            return 'warning';
        }

        return null;
    }

    /**
     * Whether an incoming location ping is a stale resend of one already
     * applied — the offline queue on the client retries a reading until it
     * gets a 2xx, so a response lost after the server actually saved it
     * would otherwise cause the same reading to be processed twice (double
     * "arrival" notifications, double auto-transition attempts).
     */
    public static function isDuplicatePing(?int $incomingCapturedAtMs, ?int $lastAppliedCapturedAtMs): bool
    {
        if ($incomingCapturedAtMs === null || $lastAppliedCapturedAtMs === null) {
            return false;
        }

        return $incomingCapturedAtMs <= $lastAppliedCapturedAtMs;
    }
}
