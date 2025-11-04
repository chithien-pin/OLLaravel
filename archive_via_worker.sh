#!/bin/bash

# Archive 14 missing videos via R2 Worker API

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

WORKER_URL="https://orange-r2-archiver.lbthuan917.workers.dev"

echo "🚀 Archiving 14 videos to R2 via Worker..."
echo "=========================================="

SUCCESS_COUNT=0
FAILED_COUNT=0

for VIDEO_ID in "${VIDEO_IDS[@]}"
do
  echo -n "📹 Archiving video $VIDEO_ID... "

  # Call Worker API to archive video
  RESPONSE=$(curl -s -X POST "$WORKER_URL/archive-video" \
    -H "Content-Type: application/json" \
    -d "{\"video_id\": \"$VIDEO_ID\"}")

  if echo "$RESPONSE" | grep -q "success"; then
    echo "✅ Success!"
    SUCCESS_COUNT=$((SUCCESS_COUNT + 1))

    # Update database
    ssh root@69.62.115.197 "docker exec -i ol_mysql mysql -u orange_user -porange_pass orange_db -e \"
      UPDATE post_contents
      SET r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/$VIDEO_ID.mp4',
          r2_key = 'videos/$VIDEO_ID.mp4',
          r2_status = 'ready',
          r2_uploaded_at = NOW(),
          use_r2 = 1
      WHERE cloudflare_video_id = '$VIDEO_ID';
    \"" 2>/dev/null
  else
    echo "❌ Failed!"
    echo "Response: $RESPONSE"
    FAILED_COUNT=$((FAILED_COUNT + 1))
  fi

  # Rate limit - avoid overwhelming the API
  sleep 3
done

echo "=========================================="
echo "📊 Archive Results:"
echo "   ✅ Success: $SUCCESS_COUNT"
echo "   ❌ Failed: $FAILED_COUNT"
echo "=========================================="

# Final check
echo ""
echo "📈 Checking R2 bucket status..."
ssh root@69.62.115.197 "docker exec -i ol_backend php artisan videos:r2-status | grep 'Total Objects'"