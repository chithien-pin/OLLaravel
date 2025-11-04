#!/bin/bash

# Script to dispatch R2 archive jobs for 14 missing videos

VIDEO_IDS=(
  "263604d8d041e481219fbf28bb8e862a"
  "257e4c8a343e08c3cd9347cddeacd034"
  "a49518b807002be4709d29de13749566"
  "ba8b46e6121d151d894416d629e13f55"
  "97b1cfc51d05186303ac20c1b4aa7538"
  "c4c2331e3a688f673026eef92cfb3501"
  "3b1714290df4e64c370560d74457dbc9"
  "0dbf40a025fb2d114276e35127585638"
  "cd1e7824ba5321b839966f6285e07bb4"
  "27381d00ff9738e9cde7e8f925e709ee"
  "9bdd1b59cd9e07baab5475375b9d088f"
  "fccef7ce10da5731a11b748a7c59cdb1"
  "0543ba63dd99f1279ca1d36680759f06"
  "9aca077794209d652b3c938842634459"
)

echo "Dispatching R2 archive jobs for 14 videos..."

for VIDEO_ID in "${VIDEO_IDS[@]}"
do
  echo "Processing video: $VIDEO_ID"

  ssh root@69.62.115.197 "docker exec -i ol_backend php artisan tinker" << EOF
\$video = \App\Models\PostContent::where('cloudflare_video_id', '$VIDEO_ID')->first();
if (\$video) {
    echo "Dispatching job for video ID: $VIDEO_ID (Post ID: {\$video->id})\n";
    \App\Jobs\ArchiveVideoToR2::dispatch('$VIDEO_ID', \$video->id);
} else {
    echo "Video not found: $VIDEO_ID\n";
}
exit
EOF

  sleep 2  # Delay between jobs to avoid overwhelming
done

echo "All jobs dispatched!"
echo "Run queue worker to process: docker exec -it ol_backend php artisan queue:work"