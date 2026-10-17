<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Deep Link Base URL
    |--------------------------------------------------------------------------
    | Configurable base URL for sharing and deep-linking.
    | Defaults to https://wafapp.alwafaamed.com, can be customized via .env.
    */
    'base_url' => rtrim(env('DEEP_LINK_BASE_URL', 'https://wafapp.alwafaamed.com'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Custom URL Scheme
    |--------------------------------------------------------------------------
    | Used for universal fallback on web/social in-app browsers.
    */
    'scheme' => env('DEEP_LINK_SCHEME', 'storeapp'),

    /*
    |--------------------------------------------------------------------------
    | Android App Links Configuration
    |--------------------------------------------------------------------------
    */
    'android' => [
        'package_name' => env('DEEP_LINK_ANDROID_PACKAGE', 'com.alwafaamed.storeapp'),
        'sha256_cert_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('DEEP_LINK_ANDROID_SHA256', ''))
        ))),
        'store_url' => env(
            'APP_ANDROID_STORE_URL',
            'https://play.google.com/store/apps/details?id=com.alwafaamed.storeapp'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | iOS Universal Links Configuration
    |--------------------------------------------------------------------------
    */
    'ios' => [
        'bundle_id' => env('DEEP_LINK_IOS_BUNDLE_ID', 'com.alwafaamed.storeapp'),
        'team_id' => env('DEEP_LINK_IOS_TEAM_ID', ''),
        'store_url' => env('APP_IOS_STORE_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Fallback Auto-Redirect Delay (Milliseconds)
    |--------------------------------------------------------------------------
    | Time to allow the native OS / app scheme to open before redirecting to store.
    */
    'redirect_delay_ms' => (int) env('DEEP_LINK_REDIRECT_DELAY_MS', 0),
];
