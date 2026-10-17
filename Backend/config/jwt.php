<?php

return [
    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'algo' => env('JWT_ALGO', 'HS256'),
    'issuer' => env('JWT_ISSUER', config('app.url', 'https://lamma.test')),
    'audience' => env('JWT_AUDIENCE', 'lamma-customer-client'),
    'ttl' => (int) env('JWT_TTL', 86400), // Access token expiration in minutes (60 days)
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 259200), // Refresh token expiration in minutes (180 days)
    'leeway' => (int) env('JWT_LEEWAY', 0), // Clock skew leeway in seconds
];
