<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PostContent;
use App\Jobs\WarmupCloudflareVideo;
use Illuminate\Support\Facades\Log;

/**
 * Periodic CDN Warmup Command
 *
 * Purpose:
 * - Maintain CDN cache for popular and recent videos
 * - Prevent cache expiration (CDN TTL ~24-48h)
 * - Ensure consistent performance globally
 *
 * Strategies:
 * 1. RECENT: Warm 50 most recent videos (last 7 days)
 * 2. POPULAR: Warm 50 most viewed videos (all time)
 * 3. BOTH: Warm top 30 recent + top 30 popular (60 total)
 *
 * Schedule: Every 2 hours (configurable)
 *
 * Usage:
 *   php artisan videos:warmup-recent
 *   php artisan videos:warmup-recent --strategy=recent
 *   php artisan videos:warmup-recent --strategy=popular
 *   php artisan videos:warmup-recent --strategy=both
 *   php artisan videos:warmup-recent --limit=100
 */
class WarmupRecentVideos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:warmup-recent
                            {--strategy= : Warmup strategy: recent, popular, both (default: from config)}
                            {--limit= : Number of videos to warm (overrides config)}
                            {--dry-run : Show what would be warmed without dispatching jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periodically warm up CDN cache for recent and popular videos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startTime = microtime(true);

        $this->info('🔥 ========================================');
        $this->info('🔥 PERIODIC CDN WARMUP STARTING');
        $this->info('🔥 ========================================');

        // Get strategy from option or config
        $strategy = $this->option('strategy') ?? config('cloudflare.warmup_strategy', 'both');
        $isDryRun = $this->option('dry-run');

        $this->info("📋 Strategy: " . strtoupper($strategy));
        if ($isDryRun) {
            $this->warn("⚠️  DRY RUN MODE - No jobs will be dispatched");
        }

        Log::info('🔥 [PERIODIC_WARMUP] Starting periodic warmup', [
            'strategy' => $strategy,
            'dry_run' => $isDryRun,
        ]);

        // Execute strategy
        $videos = [];
        switch ($strategy) {
            case 'recent':
                $videos = $this->getRecentVideos();
                break;
            case 'popular':
                $videos = $this->getPopularVideos();
                break;
            case 'both':
                $videos = $this->getBothRecentAndPopular();
                break;
            default:
                $this->error("❌ Invalid strategy: {$strategy}");
                $this->error("   Valid options: recent, popular, both");
                return 1;
        }

        if ($videos->isEmpty()) {
            $this->warn('⚠️  No videos found to warm up');
            Log::info('🔥 [PERIODIC_WARMUP] No videos found');
            return 0;
        }

        $this->info("📊 Found {$videos->count()} videos to warm up");
        $this->newLine();

        // Display videos table
        $this->displayVideosTable($videos);

        // Dispatch warmup jobs
        if (!$isDryRun) {
            $this->info('🚀 Dispatching warmup jobs...');
            $this->newLine();

            $dispatched = 0;
            $failed = 0;

            $progressBar = $this->output->createProgressBar($videos->count());
            $progressBar->start();

            foreach ($videos as $video) {
                try {
                    WarmupCloudflareVideo::dispatch($video->cloudflare_video_id);
                    $dispatched++;

                    Log::info('🔥 [PERIODIC_WARMUP] Dispatched warmup job', [
                        'video_id' => $video->cloudflare_video_id,
                        'post_id' => $video->post_id,
                        'views' => $video->view_count,
                    ]);
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('🔥 [PERIODIC_WARMUP] Failed to dispatch job', [
                        'video_id' => $video->cloudflare_video_id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("✅ Dispatched: {$dispatched} jobs");
            if ($failed > 0) {
                $this->error("❌ Failed: {$failed} jobs");
            }
        } else {
            $this->info('🔍 DRY RUN - No jobs dispatched');
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->newLine();
        $this->info('🔥 ========================================');
        $this->info("🔥 WARMUP COMPLETED IN {$duration}ms");
        $this->info('🔥 ========================================');

        Log::info('🔥 [PERIODIC_WARMUP] Completed', [
            'strategy' => $strategy,
            'videos_found' => $videos->count(),
            'jobs_dispatched' => $dispatched ?? 0,
            'jobs_failed' => $failed ?? 0,
            'duration_ms' => $duration,
        ]);

        return 0;
    }

    /**
     * Get recent videos (last 7 days)
     */
    protected function getRecentVideos()
    {
        $limit = $this->option('limit') ?? config('cloudflare.warmup_limit_recent', 50);

        $this->info("📅 Getting {$limit} most recent videos (last 7 days)...");

        return PostContent::where('content_type', 1) // Videos only
            ->where('cloudflare_status', 'ready')
            ->whereNotNull('cloudflare_video_id')
            ->whereNotNull('cloudflare_hls_url')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get popular videos (most viewed)
     */
    protected function getPopularVideos()
    {
        $limit = $this->option('limit') ?? config('cloudflare.warmup_limit_popular', 50);

        $this->info("🔥 Getting {$limit} most viewed videos...");

        return PostContent::where('content_type', 1) // Videos only
            ->where('cloudflare_status', 'ready')
            ->whereNotNull('cloudflare_video_id')
            ->whereNotNull('cloudflare_hls_url')
            ->where('view_count', '>', 0) // Only videos with views
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get both recent and popular videos
     */
    protected function getBothRecentAndPopular()
    {
        $limitRecent = config('cloudflare.warmup_limit_both_recent', 30);
        $limitPopular = config('cloudflare.warmup_limit_both_popular', 30);

        if ($this->option('limit')) {
            $totalLimit = $this->option('limit');
            $limitRecent = floor($totalLimit / 2);
            $limitPopular = $totalLimit - $limitRecent;
        }

        $this->info("📅 Getting {$limitRecent} recent + 🔥 {$limitPopular} popular videos...");

        // Get recent videos
        $recentVideos = PostContent::where('content_type', 1)
            ->where('cloudflare_status', 'ready')
            ->whereNotNull('cloudflare_video_id')
            ->whereNotNull('cloudflare_hls_url')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit($limitRecent)
            ->get();

        // Get popular videos
        $popularVideos = PostContent::where('content_type', 1)
            ->where('cloudflare_status', 'ready')
            ->whereNotNull('cloudflare_video_id')
            ->whereNotNull('cloudflare_hls_url')
            ->where('view_count', '>', 0)
            ->orderBy('view_count', 'desc')
            ->limit($limitPopular)
            ->get();

        // Merge and remove duplicates
        $merged = $recentVideos->merge($popularVideos);
        $unique = $merged->unique('cloudflare_video_id');

        $this->info("   Merged: {$merged->count()} total, {$unique->count()} unique");

        return $unique;
    }

    /**
     * Display videos table
     */
    protected function displayVideosTable($videos)
    {
        $tableData = [];

        foreach ($videos->take(10) as $video) { // Show first 10
            $tableData[] = [
                $video->id,
                $video->post_id,
                substr($video->cloudflare_video_id, 0, 20) . '...',
                $video->view_count ?? 0,
                $video->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['Content ID', 'Post ID', 'Video ID', 'Views', 'Created'],
            $tableData
        );

        if ($videos->count() > 10) {
            $this->info("   ... and " . ($videos->count() - 10) . " more videos");
        }

        $this->newLine();
    }
}
