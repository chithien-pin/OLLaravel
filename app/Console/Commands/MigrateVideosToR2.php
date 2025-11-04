<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveVideoToR2;
use App\Models\PostContent;
use App\Services\CloudflareR2Service;
use App\Services\CloudflareStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateVideosToR2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:migrate-to-r2
                            {--limit=100 : Number of videos to process per run}
                            {--force : Force re-upload even if already in R2}
                            {--age=7 : Only migrate videos older than X days}
                            {--batch : Use batch processing with jobs}
                            {--dry-run : Show what would be migrated without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '🚀 Migrate existing Cloudflare Stream videos to R2 for free bandwidth';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║     🚀 R2 VIDEO MIGRATION - FREE BANDWIDTH INITIATIVE 🚀     ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $limit = $this->option('limit');
        $force = $this->option('force');
        $ageThreshold = $this->option('age');
        $useBatch = $this->option('batch');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
            $this->newLine();
        }

        // Get statistics first
        $this->showStatistics();

        // Build query for videos to migrate
        $query = PostContent::where('content_type', 1) // Videos only
            ->where('cloudflare_status', 'ready')
            ->whereNotNull('cloudflare_video_id');

        if (!$force) {
            // Only get videos not yet in R2
            $query->whereNull('r2_mp4_url');
        }

        if ($ageThreshold > 0) {
            // Only migrate videos older than threshold
            $query->where('created_at', '<', now()->subDays($ageThreshold));
        }

        $totalVideos = $query->count();

        if ($totalVideos == 0) {
            $this->info('✅ No videos need migration!');
            return Command::SUCCESS;
        }

        $this->info("📊 Found {$totalVideos} videos to migrate");
        $this->newLine();

        if (!$this->confirm("Do you want to migrate {$totalVideos} videos to R2?")) {
            $this->warn('Migration cancelled by user');
            return Command::SUCCESS;
        }

        // Process videos
        $videos = $query->limit($limit)->get();
        $bar = $this->output->createProgressBar($videos->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($videos as $video) {
            $bar->setMessage("Processing video ID: {$video->cloudflare_video_id}");

            if ($dryRun) {
                $this->newLine();
                $this->line("Would migrate: Video ID {$video->cloudflare_video_id}, Post ID {$video->post_id}");
                $successCount++;
            } else if ($useBatch) {
                // Dispatch job for background processing
                try {
                    ArchiveVideoToR2::dispatch($video->cloudflare_video_id, $video->id)
                        ->delay(now()->addSeconds($successCount * 2)); // Stagger jobs

                    $video->update(['r2_status' => 'pending']);
                    $successCount++;

                    Log::info("[R2 Migration] Dispatched job for video {$video->cloudflare_video_id}");
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("[R2 Migration] Failed to dispatch job: " . $e->getMessage());
                }
            } else {
                // Direct migration (blocking)
                try {
                    $this->migrateVideoDirectly($video);
                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->error("Failed: " . $e->getMessage());
                    Log::error("[R2 Migration] Failed to migrate video {$video->cloudflare_video_id}: " . $e->getMessage());
                }
            }

            $bar->advance();

            // Rate limiting to avoid overwhelming services
            if (!$dryRun && !$useBatch) {
                usleep(500000); // 0.5 second delay
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Show results
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║         MIGRATION RESULTS            ║');
        $this->info('╠══════════════════════════════════════╣');
        $this->info(sprintf('║ ✅ Success:  %-24d║', $successCount));
        $this->info(sprintf('║ ❌ Failed:   %-24d║', $failedCount));
        $this->info(sprintf('║ ⏭️  Skipped:  %-24d║', $skippedCount));
        $this->info('╚══════════════════════════════════════╝');

        if ($useBatch && !$dryRun) {
            $this->newLine();
            $this->info('📋 Jobs have been queued. Monitor with: php artisan queue:work');
            $this->info('📊 Check status with: php artisan videos:r2-status');
        }

        // Calculate cost savings
        $this->calculateSavings($successCount);

        return Command::SUCCESS;
    }

    /**
     * Migrate a video directly (not via job queue)
     */
    private function migrateVideoDirectly(PostContent $video): void
    {
        $r2Service = app(CloudflareR2Service::class);
        $streamService = app(CloudflareStreamService::class);

        // Check if already in R2
        if (!$this->option('force') && $video->r2_mp4_url) {
            throw new \Exception("Video already in R2");
        }

        // Update status
        $video->update(['r2_status' => 'processing']);

        // Get video info from Stream
        $videoInfo = $streamService->getVideoDetails($video->cloudflare_video_id);

        if (!$videoInfo || !isset($videoInfo['ready']) || !$videoInfo['ready']) {
            throw new \Exception("Video not ready in Cloudflare Stream");
        }

        // Download and upload to R2
        $mp4Url = "https://customer-72k8duo0n5kea9kp.cloudflarestream.com/{$video->cloudflare_video_id}/downloads/default.mp4";
        $result = $r2Service->uploadFromUrl($video->cloudflare_video_id, $mp4Url);

        if (!$result || !$result['success']) {
            throw new \Exception("Failed to upload to R2");
        }

        // Update database
        $video->update([
            'r2_mp4_url' => $result['r2_url'],
            'r2_key' => $result['r2_key'],
            'r2_file_size' => $result['file_size'],
            'r2_uploaded_at' => now(),
            'r2_status' => 'ready',
            'use_r2' => true,
        ]);

        Log::info("[R2 Migration] Successfully migrated video {$video->cloudflare_video_id}");
    }

    /**
     * Show current statistics
     */
    private function showStatistics(): void
    {
        $totalVideos = PostContent::where('content_type', 1)->count();
        $cloudflareVideos = PostContent::where('content_type', 1)
            ->whereNotNull('cloudflare_video_id')
            ->where('cloudflare_status', 'ready')
            ->count();
        $r2Videos = PostContent::where('content_type', 1)
            ->whereNotNull('r2_mp4_url')
            ->where('r2_status', 'ready')
            ->count();
        $pendingR2 = PostContent::where('content_type', 1)
            ->where('r2_status', 'pending')
            ->count();

        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Videos', $totalVideos, '100%'],
                ['Cloudflare Stream', $cloudflareVideos, $totalVideos > 0 ? round(($cloudflareVideos / $totalVideos) * 100, 2) . '%' : '0%'],
                ['Already in R2', $r2Videos, $totalVideos > 0 ? round(($r2Videos / $totalVideos) * 100, 2) . '%' : '0%'],
                ['Pending R2 Migration', $pendingR2, $totalVideos > 0 ? round(($pendingR2 / $totalVideos) * 100, 2) . '%' : '0%'],
                ['Need Migration', $cloudflareVideos - $r2Videos, $cloudflareVideos > 0 ? round((($cloudflareVideos - $r2Videos) / $cloudflareVideos) * 100, 2) . '%' : '0%'],
            ]
        );
        $this->newLine();
    }

    /**
     * Calculate and display cost savings
     */
    private function calculateSavings(int $videoCount): void
    {
        // Assume average video: 30 seconds, 20MB, 1000 views each
        $avgVideoSizeMB = 20;
        $avgViewsPerVideo = 1000;
        $avgVideoDurationMinutes = 0.5;

        $totalStorageGB = ($videoCount * $avgVideoSizeMB) / 1024;
        $totalMinutesDelivered = $videoCount * $avgViewsPerVideo * $avgVideoDurationMinutes;

        // Cloudflare Stream costs
        $streamStorageCost = $totalStorageGB * 5; // $5 per 1000 minutes stored (rough estimate)
        $streamDeliveryCost = ($totalMinutesDelivered / 1000) * 1; // $1 per 1000 minutes delivered

        // R2 costs
        $r2StorageCost = $totalStorageGB * 0.015; // $0.015 per GB per month
        $r2DeliveryCost = 0; // FREE bandwidth!

        $totalSavingsPerMonth = ($streamStorageCost + $streamDeliveryCost) - ($r2StorageCost + $r2DeliveryCost);

        $this->newLine();
        $this->info('💰 COST SAVINGS ESTIMATE (per month)');
        $this->info('═══════════════════════════════════════');
        $this->table(
            ['Service', 'Storage', 'Bandwidth', 'Total'],
            [
                ['Cloudflare Stream', '$' . number_format($streamStorageCost, 2), '$' . number_format($streamDeliveryCost, 2), '$' . number_format($streamStorageCost + $streamDeliveryCost, 2)],
                ['R2 Storage', '$' . number_format($r2StorageCost, 2), '$0.00 (FREE!)', '$' . number_format($r2StorageCost, 2)],
                ['', '', '', ''],
                ['SAVINGS', '', '', '🎉 $' . number_format($totalSavingsPerMonth, 2) . '/month'],
            ]
        );

        $this->newLine();
        $this->info('📈 Annual Savings: $' . number_format($totalSavingsPerMonth * 12, 2));
    }
}