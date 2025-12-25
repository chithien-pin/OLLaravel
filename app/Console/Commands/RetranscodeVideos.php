<?php

namespace App\Console\Commands;

use App\Models\PostMedia;
use App\Services\R2StorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RetranscodeVideos extends Command
{
    protected $signature = 'videos:retranscode
                            {--limit=0 : Limit number of videos to process (0 = all)}
                            {--dry-run : Show what would be done without actually doing it}';

    protected $description = 'Re-transcode existing videos to add 720p quality';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('🎬 Re-transcode Videos to add 720p');
        $this->info('================================');

        // Query videos that are ready and have raw path
        $query = PostMedia::where('media_type', 1)
            ->where('r2_status', 'ready')
            ->whereNotNull('r2_raw_path')
            ->whereNotNull('r2_id');

        $totalCount = $query->count();
        $this->info("Found {$totalCount} videos ready for re-transcoding");

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

        $r2Service = new R2StorageService();
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
                // Queue new transcoding job
                $success = $r2Service->queueTranscodeJob(
                    $video->r2_id,
                    $video->r2_raw_path,
                    $jobId
                );

                if ($success) {
                    // Update status to processing
                    $video->update([
                        'r2_status' => 'processing',
                        'transcode_job_id' => $jobId,
                        'transcode_progress' => 0,
                    ]);
                    $successCount++;
                } else {
                    $errorCount++;
                    $this->newLine();
                    $this->error("Failed to queue video ID: {$video->r2_id}");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Error processing video {$video->r2_id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Queued: {$successCount} videos");
        if ($errorCount > 0) {
            $this->error("❌ Errors: {$errorCount} videos");
        }

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to actually queue jobs.');
        } else {
            $this->info('Jobs have been queued. Monitor transcoding server logs for progress.');
        }

        return 0;
    }
}
