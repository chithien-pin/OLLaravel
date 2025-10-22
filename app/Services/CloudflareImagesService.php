<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareImagesService
{
    protected $accountId;
    protected $apiToken;
    protected $accountHash;
    protected $apiBaseUrl;

    public function __construct()
    {
        $this->accountId = config('cloudflare.account_id');
        $this->apiToken = config('cloudflare.api_token');
        $this->accountHash = config('cloudflare.images.account_hash');
        $this->apiBaseUrl = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}";
    }

    /**
     * Request a one-time upload URL for Direct Creator Upload
     *
     * @param array $metadata Optional metadata for the image
     * @return array
     */
    public function requestUploadUrl($metadata = [])
    {
        try {
            // Prepare metadata
            $metadataToSend = array_merge([
                'uploaded_at' => now()->toIso8601String(),
            ], $metadata);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->asMultipart()->post("{$this->apiBaseUrl}/images/v2/direct_upload", [
                [
                    'name' => 'requireSignedURLs',
                    'contents' => 'false',
                ],
                [
                    'name' => 'metadata',
                    'contents' => json_encode($metadataToSend),
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true) {
                    return [
                        'success' => true,
                        'id' => $data['result']['id'],
                        'uploadURL' => $data['result']['uploadURL'],
                    ];
                }
            }

            Log::error('Cloudflare Images request upload URL failed', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get upload URL',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Images request upload URL exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get image details from Cloudflare Images
     *
     * @param string $imageId
     * @return array
     */
    public function getImageDetails($imageId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->get("{$this->apiBaseUrl}/images/v1/{$imageId}");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true) {
                    $image = $data['result'];

                    return [
                        'success' => true,
                        'id' => $image['id'],
                        'filename' => $image['filename'] ?? null,
                        'uploaded' => $image['uploaded'] ?? null,
                        'variants' => $this->getImageVariants($imageId),
                        'meta' => $image['meta'] ?? [],
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Failed to get image details',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Images get image details exception', [
                'imageId' => $imageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete image from Cloudflare Images
     *
     * @param string $imageId
     * @return bool
     */
    public function deleteImage($imageId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->delete("{$this->apiBaseUrl}/images/v1/{$imageId}");

            if ($response->successful()) {
                Log::info('Cloudflare image deleted', [
                    'image_id' => $imageId,
                ]);
                return true;
            }

            Log::error('Cloudflare Images delete failed', [
                'image_id' => $imageId,
                'response' => $response->json(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Cloudflare Images delete exception', [
                'imageId' => $imageId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get image URL for a specific variant
     *
     * @param string $imageId
     * @param string $variant Variant name (thumbnail, medium, large, public)
     * @return string
     */
    public function getImageUrl($imageId, $variant = 'public')
    {
        if (!$this->accountHash) {
            Log::warning('Cloudflare Images account hash not configured');
            return '';
        }

        return "https://imagedelivery.net/{$this->accountHash}/{$imageId}/{$variant}";
    }

    /**
     * Get all variant URLs for an image
     *
     * @param string $imageId
     * @return array
     */
    public function getImageVariants($imageId)
    {
        $variants = config('cloudflare.images.variants', []);
        $urls = [];

        foreach (array_keys($variants) as $variantName) {
            $urls[$variantName] = $this->getImageUrl($imageId, $variantName);
        }

        return $urls;
    }

    /**
     * Upload image from URL (for migration purposes)
     *
     * @param string $imageUrl
     * @param array $metadata
     * @return array
     */
    public function uploadFromUrl($imageUrl, $metadata = [])
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->post("{$this->apiBaseUrl}/images/v1", [
                'url' => $imageUrl,
                'requireSignedURLs' => false,
                'metadata' => array_merge([
                    'migrated_at' => now()->toIso8601String(),
                ], $metadata),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true) {
                    $image = $data['result'];

                    return [
                        'success' => true,
                        'id' => $image['id'],
                        'variants' => $this->getImageVariants($image['id']),
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Failed to upload from URL',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Images upload from URL exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload image from file path (for migration purposes)
     *
     * @param string $filePath
     * @param array $metadata
     * @return array
     */
    public function uploadFromFile($filePath, $metadata = [])
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'error' => 'File not found',
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->attach(
                'file',
                file_get_contents($filePath),
                basename($filePath)
            )->post("{$this->apiBaseUrl}/images/v1", [
                'requireSignedURLs' => false,
                'metadata' => json_encode(array_merge([
                    'migrated_at' => now()->toIso8601String(),
                ], $metadata)),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true) {
                    $image = $data['result'];

                    return [
                        'success' => true,
                        'id' => $image['id'],
                        'variants' => $this->getImageVariants($image['id']),
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Failed to upload from file',
            ];

        } catch (\Exception $e) {
            Log::error('Cloudflare Images upload from file exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
