<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the public marketing site's locale-prefixed
    | routes ("/ar/...", "/en/..."). Keyed by the URL segment used in routes.
    |
    */
    'locales' => [
        'ar' => [
            'name' => 'العربية',
            'native' => 'AR',
            'dir' => 'rtl',
            'locale' => 'ar',
        ],
        'en' => [
            'name' => 'English',
            'native' => 'EN',
            'dir' => 'ltr',
            'locale' => 'en',
        ],
    ],

    'default' => 'ar',
];
