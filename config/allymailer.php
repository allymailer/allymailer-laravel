<?php

return [
    // User or project API key.
    'api_key' => env('ALLYMAILER_API_KEY'),

    // API host (datacenter: cp.allymailer.com) or a full base URL; null: https://cp.allymailer.com/ua/transactional/api/v1.
    'host' => env('ALLYMAILER_HOST'),

    // Request timeout, seconds.
    'timeout' => (float)env('ALLYMAILER_TIMEOUT', 30),

    'webhook' => [
        // Register the route of the webhook endpoint (GET: URL check, POST: notifications).
        'enabled' => (bool)env('ALLYMAILER_WEBHOOK_ENABLED', true),
        'path' => env('ALLYMAILER_WEBHOOK_PATH', '/allymailer/webhook'),
        // API key the notifications are signed with, null: api_key.
        'key' => env('ALLYMAILER_WEBHOOK_KEY'),
        'middleware' => [],
    ],
];
