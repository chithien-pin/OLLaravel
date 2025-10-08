<?php

namespace App\Jobs;

use App\Models\PostContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessVideoToHLS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postContent;
    protected $videoPath;

    /**
     * Create a new job instance.
     *
     * @param PostContent $postContent
     * @param string $videoPath
     */
    public function __construct(PostContent $postContent, string $videoPath)
    {
        $this->postContent = $postContent;
        $this->videoPath = $videoPath;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('Starting HLS processing for post_content: ' . $this->postContent->id);

            // Update status to processing
            $this->postContent->processing_status = 'processing';
            $this->postContent->save();

            // Get full path to video
            $inputPath = storage_path('app/public/' . $this->videoPath);

            if (!file_exists($inputPath)) {
                throw new \Exception('Video file not found: ' . $inputPath);
            }

            // Create HLS output directory
            $hlsDir = 'hls/' . $this->postContent->post_id . '/' . $this->postContent->id;
            $hlsFullDir = storage_path('app/public/' . $hlsDir);

            // Create directory if it doesn't exist
            if (!file_exists($hlsFullDir)) {
                mkdir($hlsFullDir, 0755, true);
            }

            // HLS output paths
            $playlistPath = $hlsFullDir . '/playlist.m3u8';
            $segmentPattern = $hlsFullDir . '/segment_%03d.ts';

            // FFmpeg command for HLS conversion
            // -codec:v h264 - Use H.264 video codec for compatibility
            // -codec:a aac - Use AAC audio codec
            // -hls_time 2 - Each segment is 2 seconds
            // -hls_playlist_type vod - Video on Demand (not live stream)
            // -hls_segment_filename - Pattern for segment files
            // -preset fast - Faster encoding with good quality
            // -crf 22 - Quality setting (lower = better quality, 18-28 is good range)
            $ffmpegCommand = sprintf(
                'ffmpeg -i %s ' .
                '-codec:v h264 -profile:v main -level 3.1 ' .
                '-codec:a aac -ar 44100 -ac 2 -b:a 128k ' .
                '-hls_time 2 ' .
                '-hls_playlist_type vod ' .
                '-hls_segment_filename %s ' .
                '-hls_flags independent_segments ' .
                '-preset fast -crf 22 ' .
                '-maxrate 2M -bufsize 4M ' .
                '-pix_fmt yuv420p ' .
                '-movflags +faststart ' .
                '%s 2>&1',
                escapeshellarg($inputPath),
                escapeshellarg($segmentPattern),
                escapeshellarg($playlistPath)
            );

            Log::info('FFmpeg command: ' . $ffmpegCommand);

            // Execute FFmpeg
            $output = [];
            $returnVar = 0;
            exec($ffmpegCommand, $output, $returnVar);

            if ($returnVar !== 0) {
                $errorOutput = implode("\n", $output);
                Log::error('FFmpeg failed: ' . $errorOutput);
                throw new \Exception('FFmpeg conversion failed: ' . $errorOutput);
            }

            Log::info('HLS conversion successful');

            // Generate thumbnail if not exists
            if (empty($this->postContent->thumbnail)) {
                $this->generateThumbnail($inputPath, $hlsFullDir);
            }

            // Update database with HLS information
            $this->postContent->is_hls = true;
            $this->postContent->hls_path = $hlsDir . '/playlist.m3u8';
            $this->postContent->processing_status = 'completed';
            $this->postContent->save();

            Log::info('HLS processing completed for post_content: ' . $this->postContent->id);

            // Optional: Delete original video to save space
            // Uncomment if you want to delete the original after successful conversion
            // if (config('app.delete_original_after_hls', false)) {
            //     Storage::delete('public/' . $this->videoPath);
            //     Log::info('Original video deleted: ' . $this->videoPath);
            // }

        } catch (\Exception $e) {
            Log::error('HLS processing failed: ' . $e->getMessage());

            // Update status to failed
            $this->postContent->processing_status = 'failed';
            $this->postContent->processing_error = $e->getMessage();
            $this->postContent->save();

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Generate thumbnail from video
     *
     * @param string $videoPath
     * @param string $outputDir
     * @return void
     */
    protected function generateThumbnail($videoPath, $outputDir)
    {
        try {
            $thumbnailPath = $outputDir . '/thumbnail.jpg';

            // FFmpeg command to extract thumbnail at 1 second
            $command = sprintf(
                'ffmpeg -i %s -ss 00:00:01 -vframes 1 -vf "scale=480:-1" %s 2>&1',
                escapeshellarg($videoPath),
                escapeshellarg($thumbnailPath)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0 && file_exists($thumbnailPath)) {
                // Get relative path for storage
                $relativePath = str_replace(storage_path('app/public/'), '', $thumbnailPath);
                $this->postContent->thumbnail = $relativePath;
                Log::info('Thumbnail generated: ' . $relativePath);
            }
        } catch (\Exception $e) {
            Log::warning('Thumbnail generation failed: ' . $e->getMessage());
            // Non-critical error, continue processing
        }
    }

    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        Log::error('Job failed for post_content ' . $this->postContent->id . ': ' . $exception->getMessage());
    }
}