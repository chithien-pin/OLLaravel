<?php

namespace App\Console\Commands;

use App\Models\PostMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RegenerateThumbnails extends Command
{
    protected $signature = 'thumbnails:regenerate
                            {--limit=0 : Limit number of videos to process (0 = all)}
                            {--dry-run : Show what would be done without actually doing it}';

    protected $description = 'Regenerate thumbnails only (without re-transcoding videos)';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('🖼️  Regenerate Thumbnails to 720p');
        $this->info('================================');

        // Query videos that are ready and have raw path
        $query = PostMedia::where('media_type', 1)
            ->where('r2_status', 'ready')
            ->whereNotNull('r2_raw_path')
            ->whereNotNull('r2_id');

        $totalCount = $query->count();
        $this->info("Found {$totalCount} videos with thumbnails to regenerate");

        if ($limit > 0) {
            $query->limit($limit);
            $this->info("Processing only {$limit} videos (--limit)");
        }

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }

        $videos = $query->get();

        if ($videos->isEmpty()) {
            $this->info('No videos to process.');
            return 0;
        }

        $this->newLine();
        $bar = $this->output->createProgressBar($videos->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($videos as $video) {
            $jobId = Str::uuid()->toString();

            if ($dryRun) {
                $bar->advance();
                $successCount++;
                continue;
            }

            try {
                // Queue thumbnail-only job
                $job = [
                    'job_id' => $jobId,
                    'video_id' => $video->r2_id,
                    'r2_raw_path' => $video->r2_raw_path,
                    'type' => 'thumbnail_only',  // Key difference!
                    'callback_url' => config('r2.transcode.callback_url'),
                    'callback_secret' => config('r2.transcode.callback_secret'),
                    'created_at' => now()->toIso8601String(),
                ];

                Redis::rpush(config('r2.transcode.redis_queue'), json_encode($job));
                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Error processing video {$video->r2_id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Queued: {$successCount} thumbnail jobs");
        if ($errorCount > 0) {
            $this->error("❌ Errors: {$errorCount} videos");
        }

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to actually queue jobs.');
        } else {
            $this->info('Jobs have been queued. Monitor transcoding server logs for progress.');
            $this->info('Estimated time: ~5-10 seconds per video');
        }

        return 0;
    }
}
