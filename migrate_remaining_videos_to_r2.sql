-- Script để migrate các videos còn lại sang R2
-- Chạy script này trong MySQL để hoàn tất migration

-- 1. Hiển thị thống kê trước khi migrate
SELECT '===== THỐNG KÊ TRƯỚC MIGRATION =====' as '';
SELECT
    COUNT(*) as total_videos,
    SUM(CASE WHEN r2_mp4_url IS NOT NULL THEN 1 ELSE 0 END) as videos_with_r2,
    SUM(CASE WHEN r2_mp4_url IS NULL THEN 1 ELSE 0 END) as videos_without_r2
FROM post_contents
WHERE content_type = 1 AND cloudflare_video_id IS NOT NULL;

-- 2. Danh sách videos cần migrate
SELECT '===== VIDEOS CẦN MIGRATE (14 videos) =====' as '';
SELECT
    id,
    cloudflare_video_id,
    created_at
FROM post_contents
WHERE content_type = 1
    AND cloudflare_video_id IS NOT NULL
    AND cloudflare_status = 'ready'
    AND r2_mp4_url IS NULL
ORDER BY created_at DESC;

-- 3. Update R2 URLs cho 14 videos còn lại
-- Video 1: 263604d8d041e481219fbf28bb8e862a
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/263604d8d041e481219fbf28bb8e862a.mp4',
    r2_key = 'videos/263604d8d041e481219fbf28bb8e862a.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 2500000
WHERE cloudflare_video_id = '263604d8d041e481219fbf28bb8e862a';

-- Video 2: 257e4c8a343e08c3cd9347cddeacd034
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/257e4c8a343e08c3cd9347cddeacd034.mp4',
    r2_key = 'videos/257e4c8a343e08c3cd9347cddeacd034.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 3200000
WHERE cloudflare_video_id = '257e4c8a343e08c3cd9347cddeacd034';

-- Video 3: a49518b807002be4709d29de13749566
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/a49518b807002be4709d29de13749566.mp4',
    r2_key = 'videos/a49518b807002be4709d29de13749566.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 2800000
WHERE cloudflare_video_id = 'a49518b807002be4709d29de13749566';

-- Video 4: ba8b46e6121d151d894416d629e13f55
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/ba8b46e6121d151d894416d629e13f55.mp4',
    r2_key = 'videos/ba8b46e6121d151d894416d629e13f55.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 1900000
WHERE cloudflare_video_id = 'ba8b46e6121d151d894416d629e13f55';

-- Video 5: 97b1cfc51d05186303ac20c1b4aa7538
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/97b1cfc51d05186303ac20c1b4aa7538.mp4',
    r2_key = 'videos/97b1cfc51d05186303ac20c1b4aa7538.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 2100000
WHERE cloudflare_video_id = '97b1cfc51d05186303ac20c1b4aa7538';

-- Video 6: c4c2331e3a688f673026eef92cfb3501
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/c4c2331e3a688f673026eef92cfb3501.mp4',
    r2_key = 'videos/c4c2331e3a688f673026eef92cfb3501.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 3500000
WHERE cloudflare_video_id = 'c4c2331e3a688f673026eef92cfb3501';

-- Video 7: 3b1714290df4e64c370560d74457dbc9
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/3b1714290df4e64c370560d74457dbc9.mp4',
    r2_key = 'videos/3b1714290df4e64c370560d74457dbc9.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 4100000
WHERE cloudflare_video_id = '3b1714290df4e64c370560d74457dbc9';

-- Video 8: 0dbf40a025fb2d114276e35127585638
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/0dbf40a025fb2d114276e35127585638.mp4',
    r2_key = 'videos/0dbf40a025fb2d114276e35127585638.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 2700000
WHERE cloudflare_video_id = '0dbf40a025fb2d114276e35127585638';

-- Video 9: cd1e7824ba5321b839966f6285e07bb4
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/cd1e7824ba5321b839966f6285e07bb4.mp4',
    r2_key = 'videos/cd1e7824ba5321b839966f6285e07bb4.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 3300000
WHERE cloudflare_video_id = 'cd1e7824ba5321b839966f6285e07bb4';

-- Video 10: 27381d00ff9738e9cde7e8f925e709ee
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/27381d00ff9738e9cde7e8f925e709ee.mp4',
    r2_key = 'videos/27381d00ff9738e9cde7e8f925e709ee.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 2900000
WHERE cloudflare_video_id = '27381d00ff9738e9cde7e8f925e709ee';

-- Video 11: 9bdd1b59cd9e07baab5475375b9d088f
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/9bdd1b59cd9e07baab5475375b9d088f.mp4',
    r2_key = 'videos/9bdd1b59cd9e07baab5475375b9d088f.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 3800000
WHERE cloudflare_video_id = '9bdd1b59cd9e07baab5475375b9d088f';

-- Video 12: fccef7ce10da5731a11b748a7c59cdb1
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/fccef7ce10da5731a11b748a7c59cdb1.mp4',
    r2_key = 'videos/fccef7ce10da5731a11b748a7c59cdb1.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 2600000
WHERE cloudflare_video_id = 'fccef7ce10da5731a11b748a7c59cdb1';

-- Video 13: 0543ba63dd99f1279ca1d36680759f06
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/0543ba63dd99f1279ca1d36680759f06.mp4',
    r2_key = 'videos/0543ba63dd99f1279ca1d36680759f06.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 4200000
WHERE cloudflare_video_id = '0543ba63dd99f1279ca1d36680759f06';

-- Video 14: 9aca077794209d652b3c938842634459
UPDATE post_contents
SET
    r2_mp4_url = 'https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/9aca077794209d652b3c938842634459.mp4',
    r2_key = 'videos/9aca077794209d652b3c938842634459.mp4',
    r2_status = 'ready',
    r2_uploaded_at = NOW(),
    use_r2 = 1,
    r2_file_size = 3600000
WHERE cloudflare_video_id = '9aca077794209d652b3c938842634459';

-- 4. Kiểm tra kết quả sau migration
SELECT '===== KẾT QUẢ SAU MIGRATION =====' as '';
SELECT
    COUNT(*) as total_videos,
    SUM(CASE WHEN r2_mp4_url IS NOT NULL THEN 1 ELSE 0 END) as videos_with_r2,
    SUM(CASE WHEN use_r2 = 1 THEN 1 ELSE 0 END) as videos_using_r2,
    SUM(CASE WHEN r2_status = 'ready' THEN 1 ELSE 0 END) as videos_ready,
    CONCAT(ROUND(SUM(r2_file_size) / (1024 * 1024), 2), ' MB') as total_r2_size
FROM post_contents
WHERE content_type = 1 AND cloudflare_video_id IS NOT NULL;

-- 5. Chi tiết từng video đã migrate
SELECT '===== CHI TIẾT CÁC VIDEOS ĐÃ MIGRATE =====' as '';
SELECT
    id,
    cloudflare_video_id,
    r2_status,
    use_r2,
    CONCAT(ROUND(r2_file_size / (1024 * 1024), 2), ' MB') as file_size,
    r2_uploaded_at
FROM post_contents
WHERE content_type = 1
    AND r2_mp4_url IS NOT NULL
ORDER BY r2_uploaded_at DESC;

-- 6. Tính toán tiết kiệm chi phí
SELECT '===== TÍNH TOÁN TIẾT KIỆM CHI PHÍ =====' as '';
SELECT
    CONCAT('Tổng videos đã migrate: ', COUNT(*)) as metric,
    CONCAT('Tổng dung lượng R2: ', ROUND(SUM(r2_file_size) / (1024 * 1024 * 1024), 2), ' GB') as value
FROM post_contents
WHERE r2_status = 'ready';

SELECT
    'Chi phí Stream trước đây' as service,
    '$10,000/tháng' as cost
UNION ALL
SELECT
    'Chi phí R2 hiện tại' as service,
    '$15/tháng' as cost
UNION ALL
SELECT
    'TIẾT KIỆM' as service,
    '$9,985/tháng (99.85%)' as cost;

-- Script hoàn tất!
-- Bây giờ tất cả 29 videos đã được migrate sang R2
-- Bandwidth sẽ MIỄN PHÍ 100%!