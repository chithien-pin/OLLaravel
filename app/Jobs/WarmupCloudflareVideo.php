<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\CloudflareStreamService;

/**
 * Job to warm up Cloudflare CDN cache for newly uploaded videos
 *
 * Purpose:
 * - When a video becomes ready (webhook received), trigger CDN cache warming
 * - Send HEAD requests to video HLS manifest from multiple edge locations
 * - This causes Cloudflare to cache the video at global edge servers
 * - Result: Users worldwide get instant playback (no cold start delay)
 *
 * Strategy:
 * 1. Get video HLS manifest URL from Cloudflare
 * 2. Send HEAD requests from multiple regions (simulated via headers)
 * 3. Also warm up thumbnail and download URLs
 * 4. Log success/failure for monitoring
 */
class WarmupCloudflareVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The video ID to warm up
     */
    protected $videoId;

    /**
     * Cloudflare edge locations to warm (via IP spoofing headers)
     * These represent major regions where our users are located
     */
    protected $edgeRegions = [
        'vietnam'     => '14.241.111.1',   // Vietnam
        'singapore'   => '1.1.1.1',    // Asia Pacific
        'tokyo'       => '8.8.8.8',    // Japan
        'sydney'      => '9.9.9.9',    // Australia
        'mumbai'      => '182.19.96.1',    // India
        'dubai'       => '213.42.20.20',   // UAE (Dubai) - Etisalat
        'doha'        => '212.77.192.3',   // Qatar - Ooredoo (400km from Dubai)
        'bahrain'     => '195.229.147.1',  // Bahrain - Batelco (close to Dubai)
        'riyadh'      => '212.26.130.20',  // Saudi Arabia
        'london'      => '1.0.0.1',    // Europe (UK)
        'stockholm'   => '8.8.4.4',    // Europe (Sweden)
        'new_york'    => '4.2.2.2',    // US East
        'los_angeles' => '208.67.222.222', // US West
    ];

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [10, 30, 60]; // 10s, 30s, 60s

    /**
     * Create a new job instance.
     *
     * @param string $videoId Cloudflare video ID
     * @return void
     */
    public function __construct($videoId)
    {
        $this->videoId = $videoId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('🔥 [CDN_WARMUP_JOB] ========================================');
        Log::info('🔥 [CDN_WARMUP_JOB] Starting warmup for video: ' . $this->videoId);
        Log::info('🔥 [CDN_WARMUP_JOB] ========================================');

        $startTime = microtime(true);
        $cloudflareService = app(CloudflareStreamService::class);

        try {
            // Step 1: Get video details to ensure it's ready
            $videoDetails = $cloudflareService->getVideoDetails($this->videoId);

            if (!isset($videoDetails['ready']) || !$videoDetails['ready']) {
                Log::warning('🔥 [CDN_WARMUP_JOB] Video not ready yet, aborting warmup: ' . $this->videoId);
                Log::warning('🔥 [CDN_WARMUP_JOB] Status: ' . ($videoDetails['status'] ?? 'unknown'));
                return;
            }

            Log::info('🔥 [CDN_WARMUP_JOB] Video is ready, proceeding with warmup');

            // Step 2: Extract URLs to warm up
            $hlsUrl = $videoDetails['hls'] ?? null;
            $thumbnailUrl = $videoDetails['thumbnail'] ?? null;

            if (!$hlsUrl) {
                Log::error('🔥 [CDN_WARMUP_JOB] No HLS URL found for video: ' . $this->videoId);
                return;
            }

            Log::info('🔥 [CDN_WARMUP_JOB] HLS URL: ' . $hlsUrl);
            Log::info('🔥 [CDN_WARMUP_JOB] Thumbnail URL: ' . ($thumbnailUrl ?? 'none'));

            // Step 3: Warm up HLS manifest globally
            $hlsResults = $this->warmupUrlGlobally($hlsUrl, 'HLS Manifest');

            // Step 4: Warm up thumbnail globally
            $thumbnailResults = [];
            if ($thumbnailUrl) {
                $thumbnailResults = $this->warmupUrlGlobally($thumbnailUrl, 'Thumbnail');
            }

            // Step 5: Calculate success metrics
            $totalAttempts = count($hlsResults) + count($thumbnailResults);
            $successfulWarmups = collect(array_merge($hlsResults, $thumbnailResults))
                ->filter(fn($result) => $result['success'])
                ->count();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('🔥 [CDN_WARMUP_JOB] ========================================');
            Log::info('🔥 [CDN_WARMUP_JOB] Warmup completed in ' . $duration . 'ms');
            Log::info('🔥 [CDN_WARMUP_JOB] Success: ' . $successfulWarmups . '/' . $totalAttempts);
            Log::info('🔥 [CDN_WARMUP_JOB] Success Rate: ' . round(($successfulWarmups / $totalAttempts) * 100, 1) . '%');
            Log::info('🔥 [CDN_WARMUP_JOB] ========================================');

        } catch (\Exception $e) {
            Log::error('🔥 [CDN_WARMUP_JOB] Exception during warmup for video: ' . $this->videoId);
            Log::error('🔥 [CDN_WARMUP_JOB] Error: ' . $e->getMessage());
            Log::error('🔥 [CDN_WARMUP_JOB] Trace: ' . $e->getTraceAsString());

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Warm up a URL by sending HEAD requests from multiple regions
     *
     * @param string $url URL to warm up
     * @param string $type Type of URL (for logging)
     * @return array Array of results per region
     */
    protected function warmupUrlGlobally($url, $type)
    {
        Log::info('🔥 [CDN_WARMUP_JOB] Warming up ' . $type . ': ' . $url);

        $results = [];

        foreach ($this->edgeRegions as $region => $ip) {
            try {
                $startTime = microtime(true);

                // Send HEAD request (headers only, no body download)
                // Use X-Forwarded-For header to simulate request from different region
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'OrangeDating-CDNWarmup/1.0',
                        'X-Forwarded-For' => $ip, // Simulate request from this IP
                    ])
                    ->head($url);

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                // Check if request was successful
                $success = $response->successful();

                // Extract useful headers for debugging
                $cacheStatus = $response->header('cf-cache-status') ?? 'unknown';
                $cacheAge = $response->header('age') ?? 'unknown';

                $results[] = [
                    'region' => $region,
                    'success' => $success,
                    'status_code' => $response->status(),
                    'duration_ms' => $duration,
                    'cache_status' => $cacheStatus,
                    'cache_age' => $cacheAge,
                ];

                if ($success) {
                    Log::info("✅ [CDN_WARMUP_JOB] [{$type}] {$region}: {$response->status()} in {$duration}ms | Cache: {$cacheStatus} | Age: {$cacheAge}s");
                } else {
                    Log::warning("⚠️ [CDN_WARMUP_JOB] [{$type}] {$region}: {$response->status()} in {$duration}ms");
                }

            } catch (\Exception $e) {
                Log::error("❌ [CDN_WARMUP_JOB] [{$type}] {$region}: Exception - " . $e->getMessage());

                $results[] = [
                    'region' => $region,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('🔥 [CDN_WARMUP_JOB] ========================================');
        Log::error('🔥 [CDN_WARMUP_JOB] JOB FAILED PERMANENTLY');
        Log::error('🔥 [CDN_WARMUP_JOB] Video ID: ' . $this->videoId);
        Log::error('🔥 [CDN_WARMUP_JOB] Error: ' . $exception->getMessage());
        Log::error('🔥 [CDN_WARMUP_JOB] ========================================');

        // Could send alert to Slack/Discord here
        // Or log to external monitoring service
    }
}
