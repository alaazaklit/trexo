<?php

// Live driver-tracking tuning knobs (OrderController's "Live driver
// tracking" section + DriverLocationService on the Flutter side). Pulled
// out of hardcoded class constants so a value can be adjusted per
// environment (e.g. a looser radius while load-testing GPS in an area with
// poor signal) via .env without a code change/deploy.
return [

    // Minimum speed (km/h) assumed for ETA math so a stopped/low-GPS-speed
    // driver doesn't produce an infinite/misleadingly-large ETA.
    'min_speed_kmh' => env('TRACKING_MIN_SPEED_KMH', 15.0),

    // How often the driver app's background service pings its GPS position
    // to the backend, in seconds. Read by the Flutter client (see
    // lib/config/tracking_config.dart) — kept here too so the two values are
    // documented side by side and can be reasoned about together, even
    // though changing this alone doesn't move the app's compiled constant.
    'ping_interval_seconds' => env('TRACKING_PING_INTERVAL_SECONDS', 15),

    // Adaptive arrival radius: how close (metres) the driver's live GPS
    // position must be to the pickup/destination before it counts as
    // "within radius" for the arrival-confirmation counter below. Tiered by
    // the driver's last reported GPS accuracy — see
    // OrderTrackingRules::arrivalRadiusKm() for why a single fixed radius
    // doesn't work well across both precise and noisy fixes.
    'arrival_radius' => [
        // Accuracy at/under this (metres) is treated as a precise fix.
        'accurate_gps_threshold_m' => env('TRACKING_ACCURATE_GPS_THRESHOLD_M', 20),
        'accurate_radius_m' => env('TRACKING_ACCURATE_RADIUS_M', 75),

        // Accuracy at/under this (but over the accurate threshold) is
        // treated as a moderate fix.
        'moderate_gps_threshold_m' => env('TRACKING_MODERATE_GPS_THRESHOLD_M', 50),
        'moderate_radius_m' => env('TRACKING_MODERATE_RADIUS_M', 120),

        // Anything worse than the moderate threshold (or no accuracy
        // reported at all) falls back to this most-forgiving radius.
        'poor_radius_m' => env('TRACKING_POOR_RADIUS_M', 180),
    ],

    // How many consecutive location pings must land inside the arrival
    // radius before `picked_up`/`delivered` auto-fires. A single in-radius
    // ping is no longer enough — see
    // OrderTrackingRules::nextConfirmationState().
    'required_consecutive_confirmations' => env('TRACKING_REQUIRED_CONFIRMATIONS', 3),

    // Driver-inactivity thresholds (seconds since the driver's last
    // location ping while they have an active order). Under the warning
    // threshold is normal; at/over it is logged as a warning; at/over the
    // stale threshold the tracking is flagged stale to whoever's watching.
    'inactivity_warning_seconds' => env('TRACKING_INACTIVITY_WARNING_SECONDS', 120),
    'inactivity_stale_seconds' => env('TRACKING_INACTIVITY_STALE_SECONDS', 300),
];
