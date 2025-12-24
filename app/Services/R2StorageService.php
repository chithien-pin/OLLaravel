<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class R2StorageService
{
    protected $accountId;
    protected $accessKeyId;
    protected $secretAccessKey;
    protected $bucket;
    protected $endpoint;
    protected $publicUrl;
    protected $region = 'auto';

    public function __construct()
    {
        $this->accountId = config('r2.account_id');
        $this->accessKeyId = config('r2.access_key_id');
        $this->secretAccessKey = config('r2.secret_access_key');
        $this->bucket = config('r2.bucket');
        $this->endpoint = config('r2.endpoint');
        $this->publicUrl = config('r2.public_url');
    }

    /**
     * Generate presigned URL for video upload using AWS Signature V4
     *
     * @param string $videoId
     * @param string $filename
     * @param string $mimeType
     * @return array
     */
    public function getVideoUploadUrl($videoId, $filename, $mimeType)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4';
        $key = "videos/{$videoId}/raw/original.{$extension}";

        $presignedUrl = $this->generatePresignedUrl('PUT', $key, $mimeType);

        return [
            'upload_url' => $presignedUrl,
            'media_id' => $videoId,
            'key' => $key,
        ];
    }

    /**
     * Generate presigned URL for image upload
     *
     * @param string $imageId
     * @param string $filename
     * @param string $mimeType
     * @return array
     */
    public function getImageUploadUrl($imageId, $filename, $mimeType)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
        $key = "images/{$imageId}/original.{$extension}";

        $presignedUrl = $this->generatePresignedUrl('PUT', $key, $mimeType);

        return [
            'upload_url' => $presignedUrl,
            'image_id' => $imageId,
            'r2_raw_path' => $key,
        ];
    }

    /**
     * Generate AWS Signature V4 presigned URL
     */
    protected function generatePresignedUrl($method, $key, $contentType = null, $expiresIn = 3600)
    {
        // R2 uses virtual-hosted style: bucket.account.r2.cloudflarestorage.com
        $host = $this->bucket . '.' . $this->accountId . '.r2.cloudflarestorage.com';
        $timestamp = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');

        // Canonical request components - virtual-hosted style has no bucket in URI
        $canonicalUri = '/' . $key;

        $queryParams = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKeyId . '/' . $datestamp . '/' . $this->region . '/s3/aws4_request',
            'X-Amz-Date' => $timestamp,
            'X-Amz-Expires' => $expiresIn,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($queryParams);
        $canonicalQuerystring = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders = 'host';

        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQuerystring,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        // String to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = $datestamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            $algorithm,
            $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        // Calculate signature
        $signingKey = $this->getSignatureKey($datestamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        // Build presigned URL using virtual-hosted style
        $presignedUrl = 'https://' . $host . $canonicalUri . '?' . $canonicalQuerystring . '&X-Amz-Signature=' . $signature;

        return $presignedUrl;
    }

    /**
     * Derive signing key for AWS Signature V4
     */
    protected function getSignatureKey($datestamp)
    {
        $kDate = hash_hmac('sha256', $datestamp, 'AWS4' . $this->secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }

    /**
     * Queue transcoding job
     *
     * @param string $videoId
     * @param string $r2RawPath
     * @param string $jobId
     * @return bool
     */
    public function queueTranscodeJob($videoId, $r2RawPath, $jobId)
    {
        $job = [
            'job_id' => $jobId,
            'video_id' => $videoId,
            'r2_raw_path' => $r2RawPath,
            'callback_url' => config('r2.transcode.callback_url'),
            'callback_secret' => config('r2.transcode.callback_secret'),
            'resolutions' => config('r2.transcode.resolutions'),
            'created_at' => now()->toIso8601String(),
        ];

        try {
            Redis::rpush(config('r2.transcode.redis_queue'), json_encode($job));
            Log::info('Queued transcode job', ['job_id' => $jobId, 'video_id' => $videoId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to queue transcode job', [
                'job_id' => $jobId,
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Queue image processing job
     *
     * @param string $imageId
     * @param string $r2RawPath
     * @param string $jobId
     * @return bool
     */
    public function queueImageJob($imageId, $r2RawPath, $jobId)
    {
        $job = [
            'job_id' => $jobId,
            'image_id' => $imageId,
            'r2_raw_path' => $r2RawPath,
            'callback_url' => config('r2.transcode.callback_url'),
            'callback_secret' => config('r2.transcode.callback_secret'),
            'variants' => config('r2.image.variants'),
            'created_at' => now()->toIso8601String(),
        ];

        try {
            Redis::rpush('image_processing_jobs', json_encode($job));
            Log::info('Queued image processing job', ['job_id' => $jobId, 'image_id' => $imageId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to queue image job', [
                'job_id' => $jobId,
                'image_id' => $imageId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if object exists in R2 using HEAD request
     *
     * @param string $key
     * @return bool
     */
    public function objectExists($key)
    {
        try {
            $url = $this->publicUrl . '/' . $key;
            $response = Http::head($url);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete object from R2 (requires signed request)
     * For now, this will be handled by the transcoding server
     *
     * @param string $key
     * @return bool
     */
    public function deleteObject($key)
    {
        // TODO: Implement DELETE with AWS Signature V4
        Log::info('Delete object requested', ['key' => $key]);
        return true;
    }

    /**
     * Delete all objects with prefix
     * For now, this will be handled by the transcoding server
     *
     * @param string $prefix
     * @return bool
     */
    public function deleteByPrefix($prefix)
    {
        // TODO: Implement with list + delete
        Log::info('Delete by prefix requested', ['prefix' => $prefix]);
        return true;
    }

    /**
     * Get public URL for a key
     *
     * @param string $key
     * @return string
     */
    public function getPublicUrl($key)
    {
        return rtrim($this->publicUrl, '/') . '/' . ltrim($key, '/');
    }

    /**
     * Generate UUID for new media
     *
     * @return string
     */
    public static function generateId()
    {
        return (string) Str::uuid();
    }
}
