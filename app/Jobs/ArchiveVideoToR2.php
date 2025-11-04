<?php

namespace App\Jobs;

use App\Models\PostContent;
use App\Services\CloudflareR2Service;
use App\Services\CloudflareStreamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ArchiveVideoToR2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $videoId;
    protected $postContentId;

    /**
     * Maximum số lần retry nếu job fail
     */
    public $tries = 3;

    /**
     * Timeout cho job (5 phút)
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param string $videoId Cloudflare Stream video ID
     * @param int|null $postContentId Optional post content ID
     * @return void
     */
    public function __construct(string $videoId, ?int $postContentId = null)
    {
        $this->videoId = $videoId;
        $this->postContentId = $postContentId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("[R2 Archive Job] Starting archive for video: {$this->videoId}");

        try {
            // Get post content record nếu có ID
            $postContent = null;
            if ($this->postContentId) {
                $postContent = PostContent::find($this->postContentId);
            } else {
                // Try to find by video ID
                $postContent = PostContent::where('cloudflare_video_id', $this->videoId)->first();
            }

            if ($postContent) {
                // Update status to processing
                $postContent->update([
                    'r2_status' => 'processing'
                ]);
            }

            // Initialize services
            $r2Service = app(CloudflareR2Service::class);
            $streamService = app(CloudflareStreamService::class);

            // Check if video already in R2
            $existingUrl = $r2Service->getVideoUrl($this->videoId);
            if ($existingUrl) {
                Log::info("[R2 Archive Job] Video already exists in R2: {$existingUrl}");

                if ($postContent) {
                    $postContent->update([
                        'r2_mp4_url' => $existingUrl,
                        'r2_key' => "videos/{$this->videoId}.mp4",
                        'r2_status' => 'ready',
                        'r2_uploaded_at' => now(),
                        'use_r2' => true
                    ]);
                }

                return;
            }

            // Get video info from Cloudflare Stream
            $videoInfo = $streamService->getVideoDetails($this->videoId);

            if (!$videoInfo || !isset($videoInfo['ready']) || !$videoInfo['ready']) {
                throw new Exception("Video not ready for download from Stream");
            }

            // Try multiple download URLs
            $downloadUrls = [
                // Default MP4 download URL
                "https://customer-72k8duo0n5kea9kp.cloudflarestream.com/{$this->videoId}/downloads/default.mp4",
                // Alternative with quality
                "https://customer-72k8duo0n5kea9kp.cloudflarestream.com/{$this->videoId}/downloads/720p.mp4",
                // Via API endpoint
                $streamService->getDownloadUrl($this->videoId),
            ];

            $result = null;
            foreach ($downloadUrls as $url) {
                if (!$url) continue;

                try {
                    Log::info("[R2 Archive Job] Trying download URL: {$url}");
                    $result = $r2Service->uploadFromUrl($this->videoId, $url);

                    if ($result && $result['success']) {
                        break; // Success, exit loop
                    }
                } catch (Exception $e) {
                    Log::warning("[R2 Archive Job] Download failed from URL: {$url}, Error: " . $e->getMessage());
                    continue; // Try next URL
                }
            }

            if (!$result || !$result['success']) {
                throw new Exception("Failed to download and upload video to R2 after trying all URLs");
            }

            // Update database with R2 info
            if ($postContent) {
                $postContent->update([
                    'r2_mp4_url' => $result['r2_url'],
                    'r2_key' => $result['r2_key'],
                    'r2_file_size' => $result['file_size'],
                    'r2_uploaded_at' => now(),
                    'r2_status' => 'ready',
                    'use_r2' => true // Automatically use R2 once uploaded
                ]);

                Log::info("[R2 Archive Job] Successfully archived video {$this->videoId} to R2. URL: {$result['r2_url']}");
            }

            // Log statistics
            $sizeMB = round($result['file_size'] / (1024 * 1024), 2);
            Log::info("[R2 Archive Job] Archive stats - Video ID: {$this->videoId}, Size: {$sizeMB} MB");

            // Optional: Trigger webhook to notify frontend/worker
            $this->notifyArchiveComplete($result);

        } catch (Exception $e) {
            Log::error("[R2 Archive Job] Failed to archive video {$this->videoId}: " . $e->getMessage());

            // Update status to failed
            if ($postContent ?? null) {
                $postContent->update([
                    'r2_status' => 'failed'
                ]);
            }

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        Log::error("[R2 Archive Job] Job failed permanently for video {$this->videoId}: " . $exception->getMessage());

        // Update status to failed permanently
        if ($this->postContentId) {
            PostContent::where('id', $this->postContentId)->update([
                'r2_status' => 'failed'
            ]);
        } else {
            PostContent::where('cloudflare_video_id', $this->videoId)->update([
                'r2_status' => 'failed'
            ]);
        }
    }

    /**
     * Notify external services about successful archive
     */
    private function notifyArchiveComplete(array $result): void
    {
        try {
            // Optional: Call Worker webhook to update cache
            $workerUrl = env('R2_ARCHIVER_WORKER_URL');
            if ($workerUrl) {
                $client = new \GuzzleHttp\Client();
                $client->post($workerUrl . '/notify-archived', [
                    'json' => [
                        'video_id' => $this->videoId,
                        'r2_url' => $result['r2_url'],
                        'file_size' => $result['file_size'],
                        'timestamp' => now()->toIso8601String(),
                    ],
                    'timeout' => 5,
                ]);
            }
        } catch (Exception $e) {
            // Don't fail job if notification fails
            Log::warning("[R2 Archive Job] Failed to notify archive completion: " . $e->getMessage());
        }
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        // Retry for up to 1 hour
        return now()->addHour();
    }
}