<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudflare Stream Configuration
    |--------------------------------------------------------------------------
    |
    | These settings are used for integrating with Cloudflare Stream API
    | for video uploads and streaming.
    |
    */

    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'stream_customer_subdomain' => env('CLOUDFLARE_STREAM_CUSTOMER_SUBDOMAIN'),

    'webhook_secret' => env('CLOUDFLARE_WEBHOOK_SECRET'),
    'watermark_uid' => env('CLOUDFLARE_WATERMARK_UID'),

    'allowed_origins' => [
        '*', // Allow all origins for now, restrict in production
    ],

    'max_video_duration' => 30, // Maximum video duration in seconds
    'upload_url_expiry_hours' => 2, // Upload URL expiry time in hours

    'thumbnail' => [
        'default_time' => '1s', // Default thumbnail time
        'width' => 640,
        'height' => 360,
        'fit' => 'crop',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Images Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Cloudflare Images API for image uploads and optimization.
    |
    */

    'images' => [
        'account_hash' => env('CLOUDFLARE_IMAGES_ACCOUNT_HASH'), // Account hash for image delivery URLs

        'variants' => [
            'thumbnail' => '200x200',   // Feed grid, story rings
            'medium' => '640x640',      // Post detail view
            'large' => '1080x1080',     // Full screen view
            'public' => 'original',     // Original uploaded image
        ],

        'max_upload_size_mb' => 10, // Maximum image size in MB

        'allowed_formats' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],

        'upload_url_expiry_hours' => 2, // Upload URL expiry time in hours
    ],
];