<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareStreamService
{
    protected $accountId;
    protected $apiToken;
    protected $apiBaseUrl;
    protected $customerSubdomain;

    public function __construct()
    {
        $this->accountId = config('cloudflare.account_id');
        $this->apiToken = config('cloudflare.api_token');
        $this->customerSubdomain = config('cloudflare.stream_customer_subdomain');
        $this->apiBaseUrl = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}";
    }

    /**
     * Request a one-time upload URL for Direct Creator Upload
     *
     * @param array $metadata Optional metadata for the video
     * @return array
     */
    public function requestUploadUrl($metadata = [])
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->apiBaseUrl}/stream/direct_upload", [
                'maxDurationSeconds' => 30, // Max 30 seconds for posts
                'expiry' => now()->addHours(2)->toIso8601String(), // URL expires in 2 hours
                'requireSignedURLs' => false,
                'allowedOrigins' => config('cloudflare.allowed_origins', ['*']),
                'thumbnailTimestampPct' => 0.1, // Generate thumbnail at 10% of video
                'watermark' => [
                    'uid' => config('cloudflare.watermark_uid'), // Optional watermark
                ],
                'meta' => array_merge([
                    'name' => 'Orange App Video ' . now()->timestamp,
                    'type' => 'post',
                ], $metadata),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'uid' => $data['result']['uid'],
                    'uploadURL' => $data['result']['uploadURL'],
                ];
            }

            Log::error('Cloudflare Stream request upload URL failed', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get upload URL',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Stream request upload URL exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get video details from Cloudflare Stream
     *
     * @param string $videoId
     * @return array
     */
    public function getVideoDetails($videoId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->get("{$this->apiBaseUrl}/stream/{$videoId}");

            if ($response->successful()) {
                $data = $response->json();
                $video = $data['result'];

                return [
                    'success' => true,
                    'uid' => $video['uid'],
                    'status' => $this->mapVideoStatus($video),
                    'duration' => $video['duration'] ?? 0,
                    'size' => $video['size'] ?? 0,
                    'thumbnail' => $this->getThumbnailUrl($videoId),
                    'hls' => $this->getHlsUrl($videoId),
                    'dash' => $this->getDashUrl($videoId),
                    'preview' => $video['preview'] ?? null,
                    'ready' => $video['readyToStream'] ?? false,
                    'created' => $video['created'] ?? null,
                    'modified' => $video['modified'] ?? null,
                    'meta' => $video['meta'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get video details',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Stream get video details exception', [
                'videoId' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete video from Cloudflare Stream
     *
     * @param string $videoId
     * @return bool
     */
    public function deleteVideo($videoId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->delete("{$this->apiBaseUrl}/stream/{$videoId}");

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Cloudflare Stream delete video exception', [
                'videoId' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get HLS manifest URL for a video
     *
     * @param string $videoId
     * @return string
     */
    public function getHlsUrl($videoId)
    {
        if ($this->customerSubdomain) {
            return "https://{$this->customerSubdomain}.cloudflarestream.com/{$videoId}/manifest/video.m3u8";
        }
        return "https://videodelivery.net/{$videoId}/manifest/video.m3u8";
    }

    /**
     * Get DASH manifest URL for a video
     *
     * @param string $videoId
     * @return string
     */
    public function getDashUrl($videoId)
    {
        if ($this->customerSubdomain) {
            return "https://{$this->customerSubdomain}.cloudflarestream.com/{$videoId}/manifest/video.mpd";
        }
        return "https://videodelivery.net/{$videoId}/manifest/video.mpd";
    }

    /**
     * Get thumbnail URL for a video
     *
     * @param string $videoId
     * @param array $options Optional parameters (time, height, width, fit)
     * @return string
     */
    public function getThumbnailUrl($videoId, $options = [])
    {
        $params = array_merge([
            'time' => '1s', // Default at 1 second
            'height' => 640,
            'width' => 360,
            'fit' => 'crop',
        ], $options);

        $query = http_build_query($params);

        if ($this->customerSubdomain) {
            return "https://{$this->customerSubdomain}.cloudflarestream.com/{$videoId}/thumbnails/thumbnail.jpg?{$query}";
        }
        return "https://videodelivery.net/{$videoId}/thumbnails/thumbnail.jpg?{$query}";
    }

    /**
     * Map Cloudflare video status to our internal status
     *
     * @param array $video
     * @return string
     */
    protected function mapVideoStatus($video)
    {
        if (!isset($video['readyToStream'])) {
            return 'pending';
        }

        if ($video['readyToStream'] === true) {
            return 'ready';
        }

        if (isset($video['status']['state'])) {
            switch ($video['status']['state']) {
                case 'inprogress':
                case 'queued':
                    return 'processing';
                case 'error':
                    return 'error';
                default:
                    return 'pending';
            }
        }

        return 'processing';
    }

    /**
     * Create a webhook subscription for video status updates
     *
     * @param string $webhookUrl
     * @return array
     */
    public function createWebhookSubscription($webhookUrl)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
                'Content-Type' => 'application/json',
            ])->put("{$this->apiBaseUrl}/stream/webhook", [
                'notificationUrl' => $webhookUrl,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['result'],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create webhook subscription',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Stream create webhook exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate webhook signature from Cloudflare
     *
     * @param string $signature
     * @param string $body
     * @return bool
     */
    public function validateWebhookSignature($signature, $body)
    {
        $webhookSecret = config('cloudflare.webhook_secret');
        if (!$webhookSecret) {
            return true; // Skip validation if no secret is configured
        }

        $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Upload video from URL (for migration purposes)
     *
     * @param string $videoUrl
     * @param array $metadata
     * @return array
     */
    public function uploadFromUrl($videoUrl, $metadata = [])
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->apiBaseUrl}/stream/copy", [
                'url' => $videoUrl,
                'meta' => array_merge([
                    'name' => 'Migrated Video ' . now()->timestamp,
                ], $metadata),
                'requireSignedURLs' => false,
                'thumbnailTimestampPct' => 0.1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'uid' => $data['result']['uid'],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to upload from URL',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Stream upload from URL exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get download URL for a video
     * Generates a temporary download link for MP4 file
     *
     * @param string $videoId Cloudflare Stream video ID
     * @return array
     */
    public function getDownloadUrl($videoId)
    {
        try {
            Log::info('Requesting Cloudflare download URL', [
                'video_id' => $videoId,
            ]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->get("{$this->apiBaseUrl}/stream/{$videoId}/downloads");

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Cloudflare download URL response', [
                    'video_id' => $videoId,
                    'success' => true,
                    'response' => $data,
                ]);

                // Check if downloads array is empty - need to create download first
                if (empty($data['result']) || !isset($data['result']['default'])) {
                    Log::info('Download not found, creating download for video', [
                        'video_id' => $videoId,
                    ]);

                    // Create/generate download
                    $createResponse = Http::withHeaders([
                        'Authorization' => "Bearer {$this->apiToken}",
                    ])->post("{$this->apiBaseUrl}/stream/{$videoId}/downloads");

                    if ($createResponse->successful()) {
                        $createData = $createResponse->json();

                        Log::info('Download created, response', [
                            'video_id' => $videoId,
                            'response' => $createData,
                        ]);

                        // After creating, check if download is ready
                        if (isset($createData['result']['default'])) {
                            $downloadStatus = $createData['result']['default']['status'] ?? 'unknown';

                            if ($downloadStatus === 'ready') {
                                // Download is ready, return URL
                                return [
                                    'success' => true,
                                    'download_url' => $createData['result']['default']['url'],
                                    'expires_at' => $createData['result']['default']['expires'] ?? null,
                                    'file_size' => $createData['result']['default']['size'] ?? null,
                                    'format' => 'mp4',
                                ];
                            } else if ($downloadStatus === 'inprogress') {
                                // Download is being generated
                                $percentComplete = $createData['result']['default']['percentComplete'] ?? 0;

                                Log::info('Download is being generated', [
                                    'video_id' => $videoId,
                                    'status' => $downloadStatus,
                                    'percent_complete' => $percentComplete,
                                ]);

                                return [
                                    'success' => false,
                                    'error' => 'Video download is being prepared. Please try again in a few moments.',
                                    'status' => 'processing',
                                    'percent_complete' => $percentComplete,
                                ];
                            } else {
                                // Unknown status
                                return [
                                    'success' => false,
                                    'error' => "Download status: {$downloadStatus}",
                                ];
                            }
                        }
                    }

                    return [
                        'success' => false,
                        'error' => 'Failed to create download for this video',
                    ];
                }

                // Downloads exist, check status before returning
                if (isset($data['result']['default'])) {
                    $downloadStatus = $data['result']['default']['status'] ?? 'unknown';

                    if ($downloadStatus === 'ready') {
                        return [
                            'success' => true,
                            'download_url' => $data['result']['default']['url'],
                            'expires_at' => $data['result']['default']['expires'] ?? null,
                            'file_size' => $data['result']['default']['size'] ?? null,
                            'format' => 'mp4',
                        ];
                    } else if ($downloadStatus === 'inprogress') {
                        $percentComplete = $data['result']['default']['percentComplete'] ?? 0;

                        Log::info('Existing download is still processing', [
                            'video_id' => $videoId,
                            'percent_complete' => $percentComplete,
                        ]);

                        return [
                            'success' => false,
                            'error' => 'Video download is being prepared. Please try again in a few moments.',
                            'status' => 'processing',
                            'percent_complete' => $percentComplete,
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => "Download status: {$downloadStatus}",
                        ];
                    }
                }

                return [
                    'success' => false,
                    'error' => 'Download not available for this video',
                ];
            }

            Log::error('Cloudflare download URL request failed', [
                'video_id' => $videoId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get download URL',
                'status_code' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Stream get download URL exception', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}