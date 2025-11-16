<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://ban-do-an.vercel.app',
        'http://localhost:5173',
        'http://localhost:3000',
        'https://food-api-xl8n.onrender.com',
    ],

    // ✅ DI CHUYỂN WILDCARD VÀO ĐÂY
    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.vercel\.app$/',
    ], 

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600, // Cache preflight request 10 phút

    'supports_credentials' => true,
];