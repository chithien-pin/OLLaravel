<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudflare R2 Configuration
    |--------------------------------------------------------------------------
    */

    'account_id' => env('R2_ACCOUNT_ID'),
    'access_key_id' => env('R2_ACCESS_KEY_ID'),
    'secret_access_key' => env('R2_SECRET_ACCESS_KEY'),
    'bucket' => env('R2_BUCKET', 'gypsylive-media'),
    'endpoint' => env('R2_ENDPOINT'),
    'public_url' => env('R2_PUBLIC_URL', 'https://ol-media-worker.green-frost-2d64.workers.dev'),

    /*
    |--------------------------------------------------------------------------
    | Transcoding Configuration
    |--------------------------------------------------------------------------
    */

    'transcode' => [
        'redis_queue' => env('TRANSCODE_REDIS_QUEUE', 'transcode_jobs'),
        'callback_secret' => env('TRANSCODE_CALLBACK_SECRET'),
        'callback_url' => env('TRANSCODE_CALLBACK_URL', env('APP_URL') . '/api/r2/video/webhook'),
        'resolutions' => ['240p', '360p', '480p'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Variants Configuration
    |--------------------------------------------------------------------------
    */

    'image' => [
        'variants' => [
            'thumbnail' => ['width' => 200, 'height' => 200],
            'medium' => ['width' => 640, 'height' => 640],
            'large' => ['width' => 1080, 'height' => 1080],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Configuration
    |--------------------------------------------------------------------------
    */

    'upload' => [
        'presigned_url_expiry' => 3600, // 1 hour
        'max_video_size' => 500 * 1024 * 1024, // 500MB
        'max_image_size' => 10 * 1024 * 1024, // 10MB
        'allowed_video_types' => ['video/mp4', 'video/quicktime', 'video/webm'],
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],
];
