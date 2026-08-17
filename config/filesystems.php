<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // 'local' => [
        //     'driver' => 'local',
        //     'root' => storage_path('app'),
        //     'throw' => false,
        // ],

        // 'public' => [
        //     'driver' => 'local',
        //     'root' => storage_path('app/public'),
        //     'url' => env('APP_URL').'/storage',
        //     'visibility' => 'public',
        //     'throw' => false,
        // ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

      
        'public' => [
            'driver' => 'local',
            // public_path() is deterministic (unlike $_SERVER['DOCUMENT_ROOT'], which can be
            // empty depending on how the web server invokes PHP, e.g. behind a proxy or in CLI).
            'root' => public_path('storage'),
            // The storage symlink is a static file served directly by the
            // webserver at .../public/storage/..., separate from how
            // APP_URL is used for routing/admin-link generation elsewhere
            // (those correctly have no /public segment — this host's
            // .htaccess forwards dynamic requests into public/index.php
            // regardless of path, but static files are only found on disk
            // under the literal public/ folder). Hardcoded here rather than
            // folded into APP_URL itself, since changing APP_URL to include
            // /public broke every admin-panel-generated link at once.
            'url' => env('APP_URL') . '/public/storage',
            'visibility' => 'public',
            ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
