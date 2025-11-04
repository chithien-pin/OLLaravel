-- Update R2 URLs from direct R2.dev domain to Cloudflare Worker proxy
-- Old: https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/{id}.mp4
-- New: https://orange-r2-archiver.lbthuan917.workers.dev/video/{id}

-- This fixes the 400 Authorization error when accessing R2 videos directly

UPDATE post_contents
SET r2_mp4_url = CONCAT(
    'https://orange-r2-archiver.lbthuan917.workers.dev/video/',
    REPLACE(REPLACE(r2_key, 'videos/', ''), '.mp4', '')
)
WHERE r2_mp4_url LIKE 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/%'
  AND r2_key IS NOT NULL
  AND r2_key != '';

-- Show updated records count
SELECT COUNT(*) as updated_count
FROM post_contents
WHERE r2_mp4_url LIKE 'https://orange-r2-archiver.lbthuan917.workers.dev/%';

-- Show sample of updated URLs
SELECT id, r2_key, r2_mp4_url, is_r2_available, r2_status
FROM post_contents
WHERE r2_mp4_url LIKE 'https://orange-r2-archiver.lbthuan917.workers.dev/%'
LIMIT 5;
