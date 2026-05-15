<?php

declare(strict_types=1);

return [
    'session' => [
        'name' => env('SESSION_NAME', 'igreja_tefe_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'secure' => (bool) env('SESSION_SECURE', false),
        'same_site' => env('SESSION_SAMESITE', 'Lax'),
        'http_only' => true,
    ],

    'password' => [
        'algorithm' => PASSWORD_DEFAULT,
    ],

    'csrf' => [
        'field' => '_csrf_token',
    ],
];

