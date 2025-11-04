<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PostContent;
use App\Services\CloudflareStreamService;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CloudflareController extends Controller
{
    protected $cloudflareService;
    protected $cloudflareImagesService;

    public function __construct(CloudflareStreamService $cloudflareService, CloudflareImagesService $cloudflareImagesService)
    {
        $this->cloudflareService = $cloudflareService;
        $this->cloudflareImagesService = $cloudflareImagesService;
    }

    /**
     * Get upload URL for Direct Creator Upload
     * Client will use this URL to upload video directly to Cloudflare
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUploadUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'post_id' => 'integer', // Optional, if uploading for existing post
            'metadata' => 'array', // Optional metadata
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            // Request upload URL from Cloudflare
            $metadata = $request->metadata ?? [];
            $metadata['user_id'] = $request->user_id;
            $metadata['uploaded_at'] = now()->toIso8601String();

            $result = $this->cloudflareService->requestUploadUrl($metadata);

            if ($result['success']) {
                // Log the upload request
                Log::info('Cloudflare upload URL requested', [
                    'user_id' => $request->user_id,
                    'video_id' => $result['uid'],
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Upload URL generated successfully',
                    'data' => [
                        'upload_url' => $result['uploadURL'],
                        'video_id' => $result['uid'],
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => $result['error'] ?? 'Failed to generate upload URL',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error generating upload URL', [
                'error' => $e->getMessage(),
                'user_id' => $request->user_id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while generating upload URL',
            ], 500);
        }
    }

    /**
     * Check video status on Cloudflare
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkVideoStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            $result = $this->cloudflareService->getVideoDetails($request->video_id);

            if ($result['success']) {
                return response()->json([
                    'status' => true,
                    'message' => 'Video status retrieved',
                    'data' => [
                        'video_id' => $result['uid'],
                        'status' => $result['status'],
                        'ready' => $result['ready'],
                        'duration' => $result['duration'],
                        'thumbnail' => $result['thumbnail'],
                        'hls_url' => $result['ready'] ? $result['hls'] : null,
                        'dash_url' => $result['ready'] ? $result['dash'] : null,
                    ],
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Failed to get video status',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error checking video status', [
                'error' => $e->getMessage(),
                'video_id' => $request->video_id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while checking video status',
            ], 500);
        }
    }

    /**
     * Webhook endpoint for Cloudflare Stream notifications
     * Called by Cloudflare when video status changes
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        // Validate webhook signature if configured
        $signature = $request->header('X-Webhook-Signature');
        if ($signature && !$this->cloudflareService->validateWebhookSignature($signature, $request->getContent())) {
            Log::warning('Invalid Cloudflare webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();

        Log::info('Cloudflare webhook received', $data);

        try {
            // Handle different webhook events
            if (isset($data['uid']) && isset($data['readyToStream'])) {
                $videoId = $data['uid'];

                // Find the post content by cloudflare_video_id
                $postContent = PostContent::where('cloudflare_video_id', $videoId)->first();

                if ($postContent) {
                    // Update video status
                    if ($data['readyToStream'] === true) {
                        // Video is ready - but add small delay to ensure Cloudflare is truly serving
                        // Sometimes readyToStream=true but video not immediately available (race condition)
                        $postContent->cloudflare_status = 'ready';
                        $postContent->cloudflare_duration = $data['duration'] ?? 0;
                        $postContent->cloudflare_hls_url = $this->cloudflareService->getHlsUrl($videoId);
                        $postContent->cloudflare_dash_url = $this->cloudflareService->getDashUrl($videoId);
                        $postContent->cloudflare_thumbnail_url = $this->cloudflareService->getThumbnailUrl($videoId);
                        $postContent->cloudflare_stream_url = $this->cloudflareService->getHlsUrl($videoId); // Use HLS as default

                        Log::info('Video ready on Cloudflare', [
                            'video_id' => $videoId,
                            'post_content_id' => $postContent->id,
                            'ready_to_stream' => true,
                            'note' => 'Video marked as ready, but may need 2-3s for CDN propagation',
                        ]);

                        // Pre-generate download URL in background for faster user downloads
                        try {
                            Log::info('Pre-generating download URL for video', [
                                'video_id' => $videoId,
                            ]);

                            // Call getDownloadUrl to trigger Cloudflare to start generating the download
                            // We don't need to wait for the result, just trigger the generation
                            $downloadResult = $this->cloudflareService->getDownloadUrl($videoId);

                            if ($downloadResult['success']) {
                                Log::info('Download URL pre-generated successfully', [
                                    'video_id' => $videoId,
                                    'download_url' => $downloadResult['download_url'] ?? null,
                                ]);
                            } else {
                                Log::warning('Download URL pre-generation started (processing)', [
                                    'video_id' => $videoId,
                                    'status' => $downloadResult['status'] ?? 'unknown',
                                    'message' => $downloadResult['error'] ?? 'Processing',
                                ]);
                            }
                        } catch (\Exception $e) {
                            // Don't fail webhook if download generation fails
                            Log::error('Failed to pre-generate download URL', [
                                'video_id' => $videoId,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // 🔥 CDN WARMUP: Dispatch background job to warm up CDN cache globally
                        // This will trigger Cloudflare edge servers worldwide to cache the video
                        // Result: Users globally get instant playback (no cold start)
                        try {
                            Log::info('🔥 [CDN_WARMUP] Dispatching warmup job for video', [
                                'video_id' => $videoId,
                            ]);

                            \App\Jobs\WarmupCloudflareVideo::dispatch($videoId);

                            Log::info('🔥 [CDN_WARMUP] Warmup job dispatched successfully', [
                                'video_id' => $videoId,
                            ]);
                        } catch (\Exception $e) {
                            // Don't fail webhook if warmup dispatch fails
                            Log::error('🔥 [CDN_WARMUP] Failed to dispatch warmup job', [
                                'video_id' => $videoId,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // 💾 R2 ARCHIVE: Dispatch job to archive video to R2 for free bandwidth
                        // This downloads MP4 from Stream and saves to R2 bucket
                        // Result: Video served from R2 (free bandwidth) instead of Stream (paid)
                        try {
                            Log::info('💾 [R2_ARCHIVE] Dispatching R2 archive job for video', [
                                'video_id' => $videoId,
                                'post_content_id' => $postContent->id,
                            ]);

                            // Delay by 30 seconds to ensure Stream has fully processed the video
                            \App\Jobs\ArchiveVideoToR2::dispatch($videoId, $postContent->id)
                                ->delay(now()->addSeconds(30));

                            Log::info('💾 [R2_ARCHIVE] R2 archive job dispatched successfully', [
                                'video_id' => $videoId,
                                'delayed_by' => '30 seconds',
                            ]);
                        } catch (\Exception $e) {
                            // Don't fail webhook if R2 archive dispatch fails
                            Log::error('💾 [R2_ARCHIVE] Failed to dispatch R2 archive job', [
                                'video_id' => $videoId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else if (isset($data['status']['state'])) {
                        // Update processing status
                        switch ($data['status']['state']) {
                            case 'error':
                                $postContent->cloudflare_status = 'error';
                                $postContent->cloudflare_error = $data['status']['errorReasonText'] ?? 'Unknown error';
                                Log::error('Video processing error on Cloudflare', [
                                    'video_id' => $videoId,
                                    'error' => $postContent->cloudflare_error,
                                ]);
                                break;
                            case 'queued':
                            case 'inprogress':
                                $postContent->cloudflare_status = 'processing';
                                break;
                        }
                    }

                    $postContent->save();
                } else {
                    Log::warning('PostContent not found for Cloudflare video', [
                        'video_id' => $videoId,
                    ]);
                }
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Error processing Cloudflare webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Delete video from Cloudflare
     * Called when a post is deleted
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            $success = $this->cloudflareService->deleteVideo($request->video_id);

            if ($success) {
                Log::info('Video deleted from Cloudflare', [
                    'video_id' => $request->video_id,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Video deleted successfully',
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete video',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error deleting video from Cloudflare', [
                'error' => $e->getMessage(),
                'video_id' => $request->video_id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting video',
            ], 500);
        }
    }

    /**
     * Get upload URL for Cloudflare Images (Direct Creator Upload)
     * Client will use this URL to upload image directly to Cloudflare
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImageUploadUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'metadata' => 'array', // Optional metadata
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            // Request upload URL from Cloudflare Images
            $metadata = $request->metadata ?? [];
            $metadata['user_id'] = $request->user_id;
            $metadata['uploaded_at'] = now()->toIso8601String();

            $result = $this->cloudflareImagesService->requestUploadUrl($metadata);

            if ($result['success']) {
                // Log the upload request
                Log::info('Cloudflare Images upload URL requested', [
                    'user_id' => $request->user_id,
                    'image_id' => $result['id'],
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Upload URL generated successfully',
                    'data' => [
                        'upload_url' => $result['uploadURL'],
                        'image_id' => $result['id'],
                        'expires_at' => now()->addHours(2)->toIso8601String(),
                    ],
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => $result['error'] ?? 'Failed to generate upload URL',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error generating image upload URL', [
                'error' => $e->getMessage(),
                'user_id' => $request->user_id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while generating upload URL',
            ], 500);
        }
    }

    /**
     * Delete image from Cloudflare Images
     * Called when a post is deleted
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            $success = $this->cloudflareImagesService->deleteImage($request->image_id);

            if ($success) {
                Log::info('Image deleted from Cloudflare', [
                    'image_id' => $request->image_id,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Image deleted successfully',
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete image',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error deleting image from Cloudflare', [
                'error' => $e->getMessage(),
                'image_id' => $request->image_id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting image',
            ], 500);
        }
    }

    /**
     * Get download URL for a Cloudflare Stream video
     * Generates a temporary MP4 download link
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDownloadUrl(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'video_id' => 'required|string',
                'user_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                ], 400);
            }

            Log::info('Generating download URL', [
                'user_id' => $request->user_id,
                'video_id' => $request->video_id,
            ]);

            // Get download URL from Cloudflare
            $result = $this->cloudflareService->getDownloadUrl($request->video_id);

            if ($result['success']) {
                Log::info('Download URL generated successfully', [
                    'user_id' => $request->user_id,
                    'video_id' => $request->video_id,
                    'expires_at' => $result['expires_at'] ?? null,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Download URL generated successfully',
                    'data' => [
                        'download_url' => $result['download_url'],
                        'video_id' => $request->video_id,
                        'expires_at' => $result['expires_at'] ?? null,
                        'file_size' => $result['file_size'] ?? null,
                        'format' => $result['format'] ?? 'mp4',
                    ],
                ]);
            }

            Log::warning('Failed to generate download URL', [
                'user_id' => $request->user_id,
                'video_id' => $request->video_id,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            return response()->json([
                'status' => false,
                'message' => $result['error'] ?? 'Failed to generate download URL',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error generating download URL', [
                'error' => $e->getMessage(),
                'video_id' => $request->video_id ?? null,
                'user_id' => $request->user_id ?? null,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while generating download URL',
            ], 500);
        }
    }
}