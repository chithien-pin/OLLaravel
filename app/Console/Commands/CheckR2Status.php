<?php

namespace App\Console\Commands;

use App\Models\PostContent;
use App\Services\CloudflareR2Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckR2Status extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:r2-status
                            {--detailed : Show detailed video list}
                            {--failed : Show only failed videos}
                            {--pending : Show only pending videos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '📊 Check R2 migration status and statistics';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║              📊 R2 MIGRATION STATUS REPORT 📊               ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Get overall statistics
        $this->showOverallStats();

        // Get R2 bucket statistics
        $this->showR2BucketStats();

        // Show migration progress
        $this->showMigrationProgress();

        // Show cost analysis
        $this->showCostAnalysis();

        // Show detailed lists if requested
        if ($this->option('detailed')) {
            $this->showDetailedList();
        } elseif ($this->option('failed')) {
            $this->showFailedVideos();
        } elseif ($this->option('pending')) {
            $this->showPendingVideos();
        }

        return Command::SUCCESS;
    }

    /**
     * Show overall statistics
     */
    private function showOverallStats(): void
    {
        $stats = DB::table('post_contents')
            ->where('content_type', 1)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(cloudflare_video_id) as with_cloudflare,
                COUNT(r2_mp4_url) as with_r2,
                SUM(CASE WHEN r2_status = "ready" THEN 1 ELSE 0 END) as r2_ready,
                SUM(CASE WHEN r2_status = "pending" THEN 1 ELSE 0 END) as r2_pending,
                SUM(CASE WHEN r2_status = "processing" THEN 1 ELSE 0 END) as r2_processing,
                SUM(CASE WHEN r2_status = "failed" THEN 1 ELSE 0 END) as r2_failed,
                SUM(CASE WHEN use_r2 = 1 THEN 1 ELSE 0 END) as using_r2,
                SUM(r2_file_size) as total_r2_size
            ')
            ->first();

        $this->info('📈 OVERALL STATISTICS');
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Videos', $stats->total, '100%'],
                ['With Cloudflare Stream', $stats->with_cloudflare, $this->percentage($stats->with_cloudflare, $stats->total)],
                ['With R2 Storage', $stats->with_r2, $this->percentage($stats->with_r2, $stats->total)],
                ['', '', ''],
                ['R2 Ready', $stats->r2_ready ?? 0, $this->percentage($stats->r2_ready ?? 0, $stats->with_cloudflare)],
                ['R2 Pending', $stats->r2_pending ?? 0, $this->percentage($stats->r2_pending ?? 0, $stats->with_cloudflare)],
                ['R2 Processing', $stats->r2_processing ?? 0, $this->percentage($stats->r2_processing ?? 0, $stats->with_cloudflare)],
                ['R2 Failed', $stats->r2_failed ?? 0, $this->percentage($stats->r2_failed ?? 0, $stats->with_cloudflare)],
                ['', '', ''],
                ['Currently Using R2', $stats->using_r2 ?? 0, $this->percentage($stats->using_r2 ?? 0, $stats->with_r2)],
                ['Total R2 Storage', $this->formatBytes($stats->total_r2_size ?? 0), ''],
            ]
        );
        $this->newLine();
    }

    /**
     * Show R2 bucket statistics
     */
    private function showR2BucketStats(): void
    {
        try {
            $r2Service = app(CloudflareR2Service::class);
            $bucketStats = $r2Service->getBucketStats();

            $this->info('🪣 R2 BUCKET STATISTICS');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Objects', number_format($bucketStats['total_objects'])],
                    ['Total Size', $bucketStats['total_size_gb'] . ' GB'],
                    ['Storage Cost', '$' . number_format($bucketStats['estimated_cost_usd'], 2) . '/month'],
                    ['Bandwidth Cost', '$0.00 (FREE!)'],
                ]
            );
            $this->newLine();
        } catch (\Exception $e) {
            $this->warn('Unable to fetch R2 bucket statistics: ' . $e->getMessage());
            $this->newLine();
        }
    }

    /**
     * Show migration progress
     */
    private function showMigrationProgress(): void
    {
        $total = PostContent::where('content_type', 1)
            ->whereNotNull('cloudflare_video_id')
            ->where('cloudflare_status', 'ready')
            ->count();

        $migrated = PostContent::where('content_type', 1)
            ->whereNotNull('cloudflare_video_id')
            ->where('r2_status', 'ready')
            ->count();

        $percentage = $total > 0 ? round(($migrated / $total) * 100, 2) : 0;

        $this->info('📊 MIGRATION PROGRESS');
        $progressBar = str_repeat('█', (int)($percentage / 2)) . str_repeat('░', 50 - (int)($percentage / 2));
        $this->line("[$progressBar] {$percentage}%");
        $this->line("Migrated: {$migrated} / {$total} videos");
        $this->newLine();

        // Show recent activity
        $recentMigrations = PostContent::where('content_type', 1)
            ->whereNotNull('r2_uploaded_at')
            ->orderBy('r2_uploaded_at', 'desc')
            ->limit(5)
            ->get(['id', 'cloudflare_video_id', 'r2_uploaded_at', 'r2_file_size']);

        if ($recentMigrations->count() > 0) {
            $this->info('🕐 RECENT MIGRATIONS');
            $recentData = $recentMigrations->map(function ($video) {
                $uploadedAt = 'N/A';
                if ($video->r2_uploaded_at) {
                    if (is_string($video->r2_uploaded_at)) {
                        $uploadedAt = \Carbon\Carbon::parse($video->r2_uploaded_at)->diffForHumans();
                    } else {
                        $uploadedAt = $video->r2_uploaded_at->diffForHumans();
                    }
                }
                return [
                    $video->cloudflare_video_id,
                    $uploadedAt,
                    $this->formatBytes($video->r2_file_size ?? 0),
                ];
            });

            $this->table(['Video ID', 'Uploaded', 'Size'], $recentData);
            $this->newLine();
        }
    }

    /**
     * Show cost analysis
     */
    private function showCostAnalysis(): void
    {
        $r2Videos = PostContent::where('content_type', 1)
            ->where('r2_status', 'ready')
            ->where('use_r2', true)
            ->count();

        $totalR2Size = PostContent::where('content_type', 1)
            ->where('r2_status', 'ready')
            ->sum('r2_file_size');

        $totalR2SizeGB = $totalR2Size / (1024 * 1024 * 1024);

        // Estimate monthly views (1000 per video average)
        $estimatedViews = $r2Videos * 1000;
        $avgVideoDurationMinutes = 0.5; // 30 seconds average
        $totalMinutesDelivered = $estimatedViews * $avgVideoDurationMinutes;

        // Calculate costs
        $streamCost = ($totalMinutesDelivered / 1000) * 1; // $1 per 1000 minutes
        $r2StorageCost = $totalR2SizeGB * 0.015; // $0.015 per GB
        $r2BandwidthCost = 0; // FREE!
        $savings = $streamCost - ($r2StorageCost + $r2BandwidthCost);

        $this->info('💰 COST ANALYSIS (Monthly Estimate)');
        $this->table(
            ['Service', 'Storage', 'Bandwidth', 'Total'],
            [
                ['Without R2 (Stream only)', '-', '$' . number_format($streamCost, 2), '$' . number_format($streamCost, 2)],
                ['With R2', '$' . number_format($r2StorageCost, 2), '$0.00', '$' . number_format($r2StorageCost, 2)],
                ['', '', '', ''],
                ['SAVINGS', '', '', '💵 $' . number_format($savings, 2) . '/month'],
                ['', '', '', '📅 $' . number_format($savings * 12, 2) . '/year'],
            ]
        );
        $this->newLine();
    }

    /**
     * Show detailed list of videos
     */
    private function showDetailedList(): void
    {
        $videos = PostContent::where('content_type', 1)
            ->whereNotNull('cloudflare_video_id')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $this->info('📋 DETAILED VIDEO LIST (Latest 20)');
        $data = $videos->map(function ($video) {
            return [
                $video->id,
                substr($video->cloudflare_video_id ?? 'N/A', 0, 12) . '...',
                $video->r2_status ?? 'not_started',
                $video->use_r2 ? '✅' : '❌',
                $this->formatBytes($video->r2_file_size ?? 0),
                $video->created_at->format('Y-m-d'),
            ];
        });

        $this->table(['ID', 'Video ID', 'R2 Status', 'Using R2', 'Size', 'Created'], $data);
        $this->newLine();
    }

    /**
     * Show failed videos
     */
    private function showFailedVideos(): void
    {
        $failed = PostContent::where('content_type', 1)
            ->where('r2_status', 'failed')
            ->get(['id', 'cloudflare_video_id', 'updated_at']);

        if ($failed->count() == 0) {
            $this->info('✅ No failed migrations!');
            return;
        }

        $this->error('❌ FAILED MIGRATIONS');
        $data = $failed->map(function ($video) {
            return [
                $video->id,
                $video->cloudflare_video_id,
                $video->updated_at->diffForHumans(),
            ];
        });

        $this->table(['ID', 'Video ID', 'Failed'], $data);
        $this->newLine();

        $this->info('To retry failed migrations, run: php artisan videos:migrate-to-r2 --force');
    }

    /**
     * Show pending videos
     */
    private function showPendingVideos(): void
    {
        $pending = PostContent::where('content_type', 1)
            ->where('r2_status', 'pending')
            ->orWhere('r2_status', 'processing')
            ->get(['id', 'cloudflare_video_id', 'r2_status', 'updated_at']);

        if ($pending->count() == 0) {
            $this->info('✅ No pending migrations!');
            return;
        }

        $this->warn('⏳ PENDING MIGRATIONS');
        $data = $pending->map(function ($video) {
            return [
                $video->id,
                $video->cloudflare_video_id,
                $video->r2_status,
                $video->updated_at->diffForHumans(),
            ];
        });

        $this->table(['ID', 'Video ID', 'Status', 'Last Update'], $data);
        $this->newLine();

        $this->info('Make sure queue workers are running: php artisan queue:work');
    }

    /**
     * Helper to calculate percentage
     */
    private function percentage($value, $total): string
    {
        if ($total == 0) return '0%';
        return round(($value / $total) * 100, 2) . '%';
    }

    /**
     * Helper to format bytes
     */
    private function formatBytes($bytes): string
    {
        if ($bytes == 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log(1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}