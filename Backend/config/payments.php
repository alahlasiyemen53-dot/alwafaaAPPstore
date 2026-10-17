<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Driver: live | fake
    |--------------------------------------------------------------------------
    | fake binds Jaib/Floosak codes to FakePaymentProvider for tests/CI.
    */
    'driver' => env('PAYMENTS_DRIVER', 'live'),

    'providers' => [
        'jaib' => env('PAYMENTS_DRIVER', 'live') === 'fake'
            ? App\Payments\Providers\FakePaymentProvider::class
            : App\Payments\Providers\JaibPaymentProvider::class,
        'floosak' => env('PAYMENTS_DRIVER', 'live') === 'fake'
            ? App\Payments\Providers\FakePaymentProvider::class
            : App\Payments\Providers\FloosakPaymentProvider::class,
    ],

    'fake' => [
        'provider_code' => env('PAYMENTS_FAKE_PROVIDER_CODE', 'jaib'),
        'flow_type' => env('PAYMENTS_FAKE_FLOW_TYPE', 'step1'),
        'otp' => env('PAYMENTS_FAKE_OTP', '123456'),
        'fail_initiate' => env('PAYMENTS_FAKE_FAIL_INITIATE', false),
    ],

    'jaib' => [
        'base_url' => env('JAIB_BASE_URL', ''),
        'username' => env('JAIB_USERNAME', ''),
        'password' => env('JAIB_PASSWORD', ''),
        'agent_code' => env('JAIB_AGENT_CODE', ''),
        'timeout' => (int) env('JAIB_TIMEOUT', 30),
    ],

    'floosak' => [
        'base_url' => env('FLOOSAK_BASE_URL', 'https://staging.fintech-expert.net'),
        'api_key' => env('FLOOSAK_API_KEY', ''),
        'channel' => env('FLOOSAK_CHANNEL', 'merchant'),
        'source_wallet_id' => env('FLOOSAK_SOURCE_WALLET_ID', ''),
        'timeout' => (int) env('FLOOSAK_TIMEOUT', 30),
    ],
];
