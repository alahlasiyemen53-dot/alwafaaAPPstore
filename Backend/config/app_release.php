<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Customer app release / store links
    |--------------------------------------------------------------------------
    | latest_version is resolved automatically from (highest wins):
    | 1) Admin setting app_latest_version (optional override / client reports)
    | 2) apk/pubspec.yaml in the monorepo when present on the server
    | 3) APP_LATEST_VERSION env (optional seed only — leave empty in normal use)
    */
    'latest_version' => env('APP_LATEST_VERSION', ''),
    'min_version' => env('APP_MIN_VERSION', ''),
    'force_update' => env('APP_FORCE_UPDATE', false),
    'android_store_url' => env(
        'APP_ANDROID_STORE_URL',
        'https://play.google.com/store/apps/details?id=com.alwafaamed.storeapp'
    ),
    'ios_store_url' => env('APP_IOS_STORE_URL', ''),
    'pubspec_path' => env(
        'APP_PUBSPEC_PATH',
        dirname(base_path()).DIRECTORY_SEPARATOR.'pubspec.yaml'
    ),
];
