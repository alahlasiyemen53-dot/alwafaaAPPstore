<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer OTP Configuration
    |--------------------------------------------------------------------------
    |
    | Defines security bounds, lifespan, rate limiting, and sender driver
    | for Customer OTP authentication infrastructure.
    |
    */

    'code_length' => (int) env('OTP_CODE_LENGTH', 6),

    'expiry_seconds' => (int) env('OTP_EXPIRY_SECONDS', 300), // 5 minutes

    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60), // 1 minute

    'retention_days' => (int) env('OTP_RETENTION_DAYS', 7),

    'driver' => env('OTP_SENDER_DRIVER', 'fake'), // 'fake', 'log', 'whatsapp', 'whapi'

    'whatsapp' => [
        'provider' => 'whapi',
        'url' => env('WHAPI_URL', 'https://whapi.alwafaaye.com'),
        'api_key' => env('WHAPI_API_KEY', env('WHATSAPP_API_KEY', '')),
        'timeout' => (int) env('WHAPI_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo customer OTP (QA / local testing)
    |--------------------------------------------------------------------------
    |
    | When enabled, the configured phone always receives / accepts the fixed
    | OTP code. Keep OTP_DEMO_ENABLED=false in production unless intentional.
    |
    */
    'demo' => [
        // Default ON so QA works on the live API the Flutter app uses.
        // Set OTP_DEMO_ENABLED=false to disable.
        'enabled' => filter_var(env('OTP_DEMO_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'phone' => env('OTP_DEMO_PHONE', '771771771'),
        'code' => env('OTP_DEMO_CODE', '123456'),
        'name' => env('OTP_DEMO_NAME', 'مستخدم تجريبي'),
    ],

];
