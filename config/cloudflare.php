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
];