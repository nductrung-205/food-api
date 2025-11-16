<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'], // Áp dụng cho tất cả API routes và Sanctum CSRF

    'allowed_methods' => ['*'], // Cho phép tất cả các phương thức HTTP

    'allowed_origins' => [
        'https://ban-do-an.vercel.app',
        'http://localhost:5173', // Địa chỉ cục bộ của bạn
        'https://*.vercel.app', // Cho phép tất cả subdomains trên vercel
        'https://food-api-xl8n.onrender.com', // Địa chỉ render.com của bạn
    ],

    // allowed_origins_patterns có thể dùng để khớp các pattern phức tạp hơn,
    // nhưng `allowed_origins` là đủ cho các URL cụ thể.
    // Nếu bạn đang dùng `https://*.vercel.app`, thì `https://ban-do-an.vercel.app` là thừa nhưng không gây hại.
    'allowed_origins_patterns' => [], 

    'allowed_headers' => ['*'], // Cho phép tất cả các header

    'exposed_headers' => [], // Các header mà trình duyệt có thể truy cập

    'max_age' => 0, // Thời gian cache preflight request (đặt 0 để không cache, hoặc 600-3600 để cache)

    'supports_credentials' => true, // Cần thiết nếu bạn gửi cookie/authorization headers
];
