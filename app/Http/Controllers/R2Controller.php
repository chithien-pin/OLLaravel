<?php

namespace App\Http\Controllers;

use App\Models\PostMedia;
use App\Services\R2StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class R2Controller extends Controller
{
    protected $r2Service;

    public function __construct(R2StorageService $r2Service)
    {
        $this->r2Service = $r2Service;
    }

    /**
     * Get presigned URL for video upload
     * POST /api/r2/video/upload-url
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getVideoUploadUrl(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'filename' => 'nullable|string|max:255',
        ]);

        // Default mime_type based on filename extension
        $filename = $request->input('filename', 'video.mp4');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeType = match($extension) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => 'video/mp4',
        };

        $videoId = R2StorageService::generateId();
        $result = $this->r2Service->getVideoUploadUrl(
            $videoId,
            $filename,
            $mimeType
        );

        // Create PostMedia record immediately with r2_raw_path
        PostMedia::create([
            'r2_id' => $videoId,
            'media_type' => 1, // Video
            'r2_status' => 'uploading',
            'r2_raw_path' => $result['key'],
            'original_filename' => $filename,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Upload URL generated',
            'data' => $result,
        ]);
    }

    /**
     * Confirm video upload and queue transcoding
     * POST /api/r2/video/confirm-upload
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmVideoUpload(Request $request)
    {
        $request->validate([
            'video_id' => 'required|string',
            'user_id' => 'required',
        ]);

        $videoId = $request->input('video_id');

        // Find existing PostMedia record (created during getVideoUploadUrl)
        $media = PostMedia::where('r2_id', $videoId)->first();

        if (!$media) {
            // Create new record if not exists
            $media = PostMedia::create([
                'r2_id' => $videoId,
                'media_type' => 1, // Video
                'r2_status' => 'processing',
            ]);
        }

        // Get the raw path from media or construct it
        $rawPath = $media->r2_raw_path ?? "videos/{$videoId}/raw/original.mp4";

        $jobId = (string) Str::uuid();

        // Update media with job ID
        $media->update([
            'r2_status' => 'processing',
            'transcode_job_id' => $jobId,
        ]);

        // Queue transcoding job
        $queued = $this->r2Service->queueTranscodeJob(
            $videoId,
            $rawPath,
            $jobId
        );

        if (!$queued) {
            $media->update(['r2_status' => 'error', 'r2_error' => 'Failed to queue transcoding']);
            return response()->json([
                'status' => false,
                'message' => 'Failed to queue transcoding job',
            ], 500);
        }

        $media->update(['r2_status' => 'transcoding']);

        return response()->json([
            'status' => true,
            'message' => 'Video queued for transcoding',
            'data' => [
                'media_id' => $media->id,
                'video_id' => $media->r2_id,
                'status' => 'transcoding',
                'job_id' => $jobId,
            ],
        ]);
    }

    /**
     * Check video transcoding status
     * GET /api/r2/video/status/{id}
     *
     * @param string $id
     * @return JsonResponse
     */
    public function getVideoStatus($id)
    {
        $media = PostMedia::where('r2_id', $id)->first();

        if (!$media) {
            return response()->json([
                'status' => false,
                'message' => 'Video not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'video_id' => $media->r2_id,
                'status' => $media->r2_status,
                'progress' => $media->transcode_progress,
                'hls_url' => $media->r2_hls_url,
                'thumbnail_url' => $media->r2_thumbnail_url,
                'error' => $media->r2_error,
            ],
        ]);
    }

    /**
     * Webhook callback from transcoding server
     * POST /api/r2/video/webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function videoWebhook(Request $request)
    {
        // Verify callback secret
        $secret = $request->header('X-Callback-Secret');
        if ($secret !== config('r2.transcode.callback_secret')) {
            Log::warning('Invalid webhook secret', ['ip' => $request->ip()]);
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'job_id' => 'required|string',
            'video_id' => 'required|string',
            'status' => 'required|string|in:ready,error,progress',
            'progress' => 'nullable|integer|min:0|max:100',
            'hls_url' => 'nullable|string',
            'thumbnail_url' => 'nullable|string',
            'duration' => 'nullable|integer',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'error' => 'nullable|string',
        ]);

        $media = PostMedia::where('r2_id', $request->input('video_id'))->first();

        if (!$media) {
            Log::warning('Webhook for unknown video', ['video_id' => $request->input('video_id')]);
            return response()->json(['status' => false, 'message' => 'Video not found'], 404);
        }

        $status = $request->input('status');

        if ($status === 'progress') {
            $media->update([
                'transcode_progress' => $request->input('progress', 0),
            ]);
        } elseif ($status === 'ready') {
            $media->update([
                'r2_status' => 'ready',
                'r2_hls_url' => $request->input('hls_url'),
                'r2_thumbnail_url' => $request->input('thumbnail_url'),
                'duration' => $request->input('duration') ?? $media->duration,
                'width' => $request->input('width') ?? $media->width,
                'height' => $request->input('height') ?? $media->height,
                'transcode_progress' => 100,
            ]);
            Log::info('Video transcoding completed', ['video_id' => $media->r2_id]);
        } elseif ($status === 'error') {
            $media->update([
                'r2_status' => 'error',
                'r2_error' => $request->input('error', 'Unknown error'),
            ]);
            Log::error('Video transcoding failed', [
                'video_id' => $media->r2_id,
                'error' => $request->input('error'),
            ]);
        }

        return response()->json(['status' => true, 'message' => 'Webhook processed']);
    }

    /**
     * Get presigned URL for image upload
     * POST /api/r2/image/upload-url
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getImageUploadUrl(Request $request)
    {
        $request->validate([
            'filename' => 'required|string|max:255',
            'mime_type' => 'required|string|in:image/jpeg,image/png,image/webp,image/gif',
            'file_size' => 'nullable|integer|max:' . config('r2.upload.max_image_size'),
        ]);

        $imageId = R2StorageService::generateId();
        $result = $this->r2Service->getImageUploadUrl(
            $imageId,
            $request->input('filename'),
            $request->input('mime_type')
        );

        return response()->json([
            'status' => true,
            'message' => 'Upload URL generated',
            'data' => $result,
        ]);
    }

    /**
     * Confirm image upload and queue processing
     * POST /api/r2/image/confirm-upload
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmImageUpload(Request $request)
    {
        $request->validate([
            'image_id' => 'required|string',
            'post_id' => 'required|integer|exists:posts,id',
            'r2_raw_path' => 'required|string',
            'original_filename' => 'nullable|string|max:255',
            'file_size' => 'nullable|integer',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
        ]);

        // Check if file exists in R2
        if (!$this->r2Service->objectExists($request->input('r2_raw_path'))) {
            return response()->json([
                'status' => false,
                'message' => 'Image file not found in storage',
            ], 400);
        }

        $jobId = (string) Str::uuid();

        // Create PostMedia record
        $media = PostMedia::create([
            'post_id' => $request->input('post_id'),
            'media_type' => 0, // Image
            'r2_id' => $request->input('image_id'),
            'r2_status' => 'processing',
            'r2_raw_path' => $request->input('r2_raw_path'),
            'original_filename' => $request->input('original_filename'),
            'file_size' => $request->input('file_size'),
            'width' => $request->input('width'),
            'height' => $request->input('height'),
            'transcode_job_id' => $jobId,
        ]);

        // Queue image processing job
        $queued = $this->r2Service->queueImageJob(
            $request->input('image_id'),
            $request->input('r2_raw_path'),
            $jobId
        );

        if (!$queued) {
            $media->update(['r2_status' => 'error', 'r2_error' => 'Failed to queue processing']);
            return response()->json([
                'status' => false,
                'message' => 'Failed to queue image processing',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Image queued for processing',
            'data' => [
                'media_id' => $media->id,
                'image_id' => $media->r2_id,
                'status' => 'processing',
                'job_id' => $jobId,
            ],
        ]);
    }

    /**
     * Webhook callback for image processing
     * POST /api/r2/image/webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function imageWebhook(Request $request)
    {
        // Verify callback secret
        $secret = $request->header('X-Callback-Secret');
        if ($secret !== config('r2.transcode.callback_secret')) {
            Log::warning('Invalid image webhook secret', ['ip' => $request->ip()]);
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'job_id' => 'required|string',
            'image_id' => 'required|string',
            'status' => 'required|string|in:ready,error',
            'variants' => 'nullable|array',
            'variants.thumbnail' => 'nullable|string',
            'variants.medium' => 'nullable|string',
            'variants.large' => 'nullable|string',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
            'blurhash' => 'nullable|string',
            'error' => 'nullable|string',
        ]);

        $media = PostMedia::where('r2_id', $request->input('image_id'))->first();

        if (!$media) {
            Log::warning('Image webhook for unknown image', ['image_id' => $request->input('image_id')]);
            return response()->json(['status' => false, 'message' => 'Image not found'], 404);
        }

        $status = $request->input('status');

        if ($status === 'ready') {
            $media->update([
                'r2_status' => 'ready',
                'r2_image_variants' => $request->input('variants'),
                'r2_thumbnail_url' => $request->input('variants.thumbnail'),
                'width' => $request->input('width') ?? $media->width,
                'height' => $request->input('height') ?? $media->height,
                'blurhash' => $request->input('blurhash'),
            ]);
            Log::info('Image processing completed', ['image_id' => $media->r2_id]);
        } elseif ($status === 'error') {
            $media->update([
                'r2_status' => 'error',
                'r2_error' => $request->input('error', 'Unknown error'),
            ]);
            Log::error('Image processing failed', [
                'image_id' => $media->r2_id,
                'error' => $request->input('error'),
            ]);
        }

        return response()->json(['status' => true, 'message' => 'Webhook processed']);
    }

    /**
     * Delete media from R2
     * POST /api/r2/delete
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteMedia(Request $request)
    {
        $request->validate([
            'media_id' => 'required|integer|exists:post_media,id',
        ]);

        $media = PostMedia::find($request->input('media_id'));

        // Delete from R2
        if ($media->media_type === 1) {
            // Video: delete entire folder
            $this->r2Service->deleteByPrefix("videos/{$media->r2_id}/");
        } else {
            // Image: delete entire folder
            $this->r2Service->deleteByPrefix("images/{$media->r2_id}/");
        }

        // Delete database record
        $media->delete();

        return response()->json([
            'status' => true,
            'message' => 'Media deleted successfully',
        ]);
    }
}
