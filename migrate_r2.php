<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PostContent;
use App\Jobs\ArchiveVideoToR2;

echo "Starting R2 migration for remaining videos...\n";

$videos = PostContent::where('content_type', 1)
    ->whereNull('r2_mp4_url')
    ->whereNotNull('cloudflare_video_id')
    ->where('cloudflare_status', 'ready')
    ->get();

echo "Found {$videos->count()} videos to migrate\n";

foreach ($videos as $video) {
    echo "Dispatching job for video: {$video->cloudflare_video_id}\n";
    ArchiveVideoToR2::dispatch($video->cloudflare_video_id, $video->id);
}

echo "Total jobs dispatched: {$videos->count()}\n";
echo "Done!\n";