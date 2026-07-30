<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public marketing site settings
    |--------------------------------------------------------------------------
    |
    | Contact details, store links, and social profiles used across the
    | landing page, footer, and SEO/JSON-LD tags. All overridable via .env
    | so they can be updated without touching Blade files.
    |
    */

    'contact_email' => env('MARKETING_CONTACT_EMAIL', 'support@trexo.com'),
    'contact_phone' => env('MARKETING_CONTACT_PHONE', '+961 71 435 691'),
    'contact_whatsapp' => env('MARKETING_CONTACT_WHATSAPP', '+961 71 435 691'),
    'address' => env('MARKETING_ADDRESS', 'Beirut, Lebanon'),

    'app_store_url' => env('MARKETING_APP_STORE_URL', '#'),
    'play_store_url' => env('MARKETING_PLAY_STORE_URL', '#'),

    'social' => [
        'facebook' => env('MARKETING_FACEBOOK_URL'),
        'instagram' => env('MARKETING_INSTAGRAM_URL'),
        'twitter' => env('MARKETING_TWITTER_URL'),
        'linkedin' => env('MARKETING_LINKEDIN_URL'),
        'tiktok' => env('MARKETING_TIKTOK_URL'),
    ],
];
