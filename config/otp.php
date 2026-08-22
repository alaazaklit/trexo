<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp OTP anti-abuse / anti-restriction settings
    |--------------------------------------------------------------------------
    |
    | Centralizes every tunable used by App\Services\OtpService so none of
    | it is hard-coded across UsersController / AccountDeletionController.
    | All values are overridable per-environment via .env — see
    | .env.example for the full list with explanations.
    |
    */

    // Whether requestOtp() actually calls the real WhatsApp provider.
    // 'mock' logs the attempt and reports success without any network
    // call; 'live' sends for real. Leave unset to auto-decide: the testing
    // environment is ALWAYS mocked regardless of this value (see
    // OtpService::resolveProviderMode()), production defaults to live,
    // and every other environment (local, staging, ...) defaults to mock
    // until this is explicitly set to 'live'. Never set this to 'live' in
    // a local/dev .env unless you specifically mean to fire real WhatsApp
    // messages from your machine.
    'provider_mode' => env('OTP_PROVIDER_MODE'),

    // How long a generated code stays valid.
    'expiration_minutes' => (int) env('OTP_EXPIRATION_MINUTES', 5),

    // Minimum gap between two sends to the same phone number.
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),

    // Rolling-window caps, per phone number, counting only OTPs that were
    // actually sent (blocked/failed attempts don't consume the budget).
    'max_per_phone_10_minutes' => (int) env('MAX_OTP_PER_PHONE_10_MINUTES', 3),
    'max_per_phone_per_day' => (int) env('MAX_OTP_PER_PHONE_PER_DAY', 5),

    // Rolling-window cap per IP address.
    'max_per_ip_per_hour' => (int) env('MAX_OTP_PER_IP_PER_HOUR', 15),

    // Rolling-window cap per device/session identifier (only enforced when
    // the caller actually sent one — device_id is optional/best-effort,
    // see OtpService::requestOtp()). Mirrors max_per_ip_per_hour so one
    // device can't out-volume its own IP's cap by spreading requests
    // across many phone numbers that each individually stay under every
    // other limit.
    'max_per_device_per_hour' => (int) env('MAX_OTP_PER_DEVICE_PER_HOUR', 15),

    // Soft abuse signal: an IP or device requesting OTPs for many distinct
    // phone numbers in a short window smells like enumeration/spam rather
    // than a real user. Set high enough that a shared IP (office wifi,
    // NAT) doesn't trip it under normal use.
    'max_distinct_phones_per_ip_per_hour' => (int) env('MAX_OTP_DISTINCT_PHONES_PER_IP_PER_HOUR', 8),
    'max_distinct_phones_per_device_per_hour' => (int) env('MAX_OTP_DISTINCT_PHONES_PER_DEVICE_PER_HOUR', 5),

    // Global outbound-send throttling, shared by every phone number, so a
    // burst (real traffic spike or an attack) can't fire a wall of WhatsApp
    // messages at once. 'burst_limit' caps sends in any 10-second slice;
    // the per-minute ceiling ramps from 'initial_per_minute' up to
    // 'max_per_minute' in steps of 'initial_per_minute', once every
    // 'increase_interval_hours', starting the first time this is
    // evaluated (or at 'ramp_started_at' if set) — lets a brand new
    // WhatsApp number warm up gradually instead of opening at full volume.
    'global_rate' => [
        'initial_per_minute' => (int) env('INITIAL_GLOBAL_OTP_RATE', 20),
        'max_per_minute' => (int) env('MAX_GLOBAL_OTP_RATE', 120),
        'increase_interval_hours' => (int) env('OTP_RATE_INCREASE_INTERVAL', 24),
        'ramp_started_at' => env('OTP_RATE_RAMP_STARTED_AT'),
        'burst_limit' => (int) env('GLOBAL_OTP_BURST_LIMIT', 10),
    ],

];
