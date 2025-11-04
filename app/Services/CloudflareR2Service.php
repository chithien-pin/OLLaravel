<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Exception;

class CloudflareR2Service
{
    private $client;
    private $bucket;
    private $publicUrl;
    private $accountId;

    public function __construct()
    {
        $this->accountId = env('CLOUDFLARE_ACCOUNT_ID', '272f6b5e0613deef5bd36a14e6c76188');
        $this->bucket = env('R2_BUCKET_NAME', 'orange-videos');

        // R2 public URL via Cloudflare Worker (for public access without authentication)
        // Worker serves R2 files at: https://orange-r2-archiver.lbthuan917.workers.dev/video/{video_id}
        $this->publicUrl = env('R2_PUBLIC_URL', 'https://orange-r2-archiver.lbthuan917.workers.dev');

        // Initialize S3 client với R2 endpoint
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => "https://{$this->accountId}.r2.cloudflarestorage.com",
            'credentials' => [
                'key' => env('R2_ACCESS_KEY_ID'),
                'secret' => env('R2_SECRET_ACCESS_KEY'),
            ],
            // R2 specific settings
            'use_path_style_endpoint' => true,
            'http' => [
                'verify' => true,
                'timeout' => 120,
            ],
        ]);
    }

    /**
     * Upload file từ URL (download từ Stream và upload lên R2)
     */
    public function uploadFromUrl(string $videoId, string $sourceUrl): ?array
    {
        try {
            Log::info("[R2 Service] Starting upload for video: {$videoId}");

            // Download file từ URL
            $context = stream_context_create([
                'http' => [
                    'timeout' => 300, // 5 minutes timeout
                    'header' => "User-Agent: Mozilla/5.0\r\n"
                ]
            ]);

            $videoContent = @file_get_contents($sourceUrl, false, $context);

            if ($videoContent === false) {
                throw new Exception("Failed to download video from URL: {$sourceUrl}");
            }

            $fileSize = strlen($videoContent);
            Log::info("[R2 Service] Downloaded video, size: " . ($fileSize / 1024 / 1024) . " MB");

            // Upload to R2
            $key = "videos/{$videoId}.mp4";

            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $videoContent,
                'ContentType' => 'video/mp4',
                'Metadata' => [
                    'video_id' => $videoId,
                    'source' => 'cloudflare_stream',
                    'uploaded_at' => now()->toIso8601String(),
                ],
                // Make object public readable
                'ACL' => 'public-read',
            ]);

            // Worker URL format: /video/{video_id} (not /videos/{video_id}.mp4)
            $r2Url = "{$this->publicUrl}/video/{$videoId}";

            Log::info("[R2 Service] Successfully uploaded to R2: {$r2Url}");

            return [
                'success' => true,
                'r2_url' => $r2Url,
                'r2_key' => $key,
                'file_size' => $fileSize,
                'etag' => $result['ETag'] ?? null,
            ];

        } catch (AwsException $e) {
            Log::error("[R2 Service] AWS Exception: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            Log::error("[R2 Service] Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload file content trực tiếp lên R2
     */
    public function uploadContent(string $key, $content, string $contentType = 'video/mp4'): ?array
    {
        try {
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $content,
                'ContentType' => $contentType,
                'ACL' => 'public-read',
            ]);

            // Extract video ID from key (e.g., "videos/abc123.mp4" -> "abc123")
            $videoId = str_replace(['videos/', '.mp4'], '', $key);

            return [
                'success' => true,
                'r2_url' => "{$this->publicUrl}/video/{$videoId}",
                'r2_key' => $key,
                'etag' => $result['ETag'] ?? null,
            ];

        } catch (AwsException $e) {
            Log::error("[R2 Service] Upload failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get object từ R2
     */
    public function getObject(string $key)
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return $result['Body'];

        } catch (AwsException $e) {
            Log::error("[R2 Service] Get object failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if object exists in R2
     */
    public function objectExists(string $key): bool
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;

        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Delete object từ R2
     */
    public function deleteObject(string $key): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            Log::info("[R2 Service] Deleted object: {$key}");
            return true;

        } catch (AwsException $e) {
            Log::error("[R2 Service] Delete failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List objects in R2 bucket
     */
    public function listObjects(string $prefix = '', int $maxKeys = 100): array
    {
        try {
            $result = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => $maxKeys,
            ]);

            $objects = [];
            foreach ($result['Contents'] ?? [] as $object) {
                // Extract video ID from key (e.g., "videos/abc123.mp4" -> "abc123")
                $videoId = str_replace(['videos/', '.mp4'], '', $object['Key']);

                $objects[] = [
                    'key' => $object['Key'],
                    'size' => $object['Size'],
                    'last_modified' => $object['LastModified'],
                    'url' => "{$this->publicUrl}/video/{$videoId}",
                ];
            }

            return $objects;

        } catch (AwsException $e) {
            Log::error("[R2 Service] List objects failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get signed URL for private objects (if needed)
     */
    public function getSignedUrl(string $key, int $expiration = 3600): ?string
    {
        try {
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $request = $this->client->createPresignedRequest($cmd, "+{$expiration} seconds");
            return (string) $request->getUri();

        } catch (AwsException $e) {
            Log::error("[R2 Service] Get signed URL failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get bucket statistics
     */
    public function getBucketStats(): array
    {
        try {
            $objects = $this->listObjects('videos/', 1000);

            $totalSize = 0;
            $totalCount = count($objects);

            foreach ($objects as $object) {
                $totalSize += $object['size'];
            }

            return [
                'total_objects' => $totalCount,
                'total_size_bytes' => $totalSize,
                'total_size_gb' => round($totalSize / (1024 * 1024 * 1024), 2),
                'estimated_cost_usd' => round(($totalSize / (1024 * 1024 * 1024)) * 0.015, 2), // $0.015 per GB
            ];

        } catch (Exception $e) {
            Log::error("[R2 Service] Get bucket stats failed: " . $e->getMessage());
            return [
                'total_objects' => 0,
                'total_size_bytes' => 0,
                'total_size_gb' => 0,
                'estimated_cost_usd' => 0,
            ];
        }
    }

    /**
     * Download video từ Cloudflare Stream và upload lên R2
     */
    public function archiveFromStream(string $videoId): ?array
    {
        try {
            // Get download URL từ Cloudflare Stream
            $streamService = app(CloudflareStreamService::class);
            $videoInfo = $streamService->getVideoDetails($videoId);

            if (!$videoInfo || !isset($videoInfo['ready']) || !$videoInfo['ready']) {
                throw new Exception("Video not ready for download: {$videoId}");
            }

            // Build download URL - Cloudflare Stream MP4 endpoint
            $downloadUrl = "https://customer-72k8duo0n5kea9kp.cloudflarestream.com/{$videoId}/downloads/default.mp4";

            // Upload to R2
            $result = $this->uploadFromUrl($videoId, $downloadUrl);

            if ($result && $result['success']) {
                Log::info("[R2 Service] Successfully archived video {$videoId} to R2");
                return $result;
            }

            return null;

        } catch (Exception $e) {
            Log::error("[R2 Service] Archive from Stream failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get R2 URL cho video (via Worker for public access)
     */
    public function getVideoUrl(string $videoId): ?string
    {
        $key = "videos/{$videoId}.mp4";

        if ($this->objectExists($key)) {
            // Worker URL format: /video/{video_id}
            return "{$this->publicUrl}/video/{$videoId}";
        }

        return null;
    }
}