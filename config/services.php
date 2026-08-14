<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'otp_template' => env('WHATSAPP_OTP_TEMPLATE_NAME', 'otp_verification'),
        'otp_template_lang' => env('WHATSAPP_OTP_TEMPLATE_LANG', 'en_US'),
    ],

    // Google Play review / demo account — a single dedicated phone number
    // that logs in with a fixed OTP instead of a real WhatsApp code, so
    // reviewers aren't blocked on a code that expires or changes. Leave
    // either value empty to disable the feature entirely. See
    // docs/demo-account.md for the full flow and how to rotate/disable it.
    'demo_account' => [
        'phone' => env('DEMO_ACCOUNT_PHONE'),
        'otp' => env('DEMO_ACCOUNT_OTP'),
        'name' => env('DEMO_ACCOUNT_NAME', 'Google Play Reviewer'),
    ],

];
