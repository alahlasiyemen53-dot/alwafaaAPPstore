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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'firebase_web' => [
        'api_key' => env('FIREBASE_WEB_API_KEY', 'AIzaSyCOIjCXEnkzeAZr7DvtkHJIKMdwaQV8gyY'),
        'auth_domain' => env('FIREBASE_WEB_AUTH_DOMAIN', 'alwafaaapp.firebaseapp.com'),
        'project_id' => env('FIREBASE_WEB_PROJECT_ID', env('FCM_PROJECT_ID', 'alwafaaapp')),
        'storage_bucket' => env('FIREBASE_WEB_STORAGE_BUCKET', 'alwafaaapp.firebasestorage.app'),
        'messaging_sender_id' => env('FIREBASE_WEB_MESSAGING_SENDER_ID', '968310231225'),
        // Android app id works for Auth/Firestore client init in this project when web app is absent.
        'app_id' => env('FIREBASE_WEB_APP_ID', '1:968310231225:android:4f21b56bb39707c24a4b68'),
    ],

    'fcm' => [
        'enabled' => env('FCM_ENABLED', false),
        'project_id' => env('FCM_PROJECT_ID'),
        // Relative paths (recommended) resolve from the Laravel root — works on Windows and cPanel.
        // Absolute paths are still accepted for custom locations outside the project.
        'credentials_path' => (static function (): ?string {
            $path = env('FCM_CREDENTIALS_PATH', 'storage/app/private/firebase-adminsdk.json');
            if (! is_string($path) || trim($path) === '') {
                return null;
            }

            $path = trim(str_replace('\\', '/', $path));
            $isAbsolute = str_starts_with($path, '/')
                || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' && $path[2] === '/');

            return $isAbsolute ? $path : base_path($path);
        })(),
        'general_topic' => env('FCM_GENERAL_TOPIC', 'storeapp_general'),
        'connect_timeout' => (int) env('FCM_CONNECT_TIMEOUT', 5),
        'request_timeout' => (int) env('FCM_REQUEST_TIMEOUT', 15),
        'retry_backoff_seconds' => array_map(
            'intval',
            explode(',', env('FCM_RETRY_BACKOFF_SECONDS', '60,300,900')),
        ),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
