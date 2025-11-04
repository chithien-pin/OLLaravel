-- Script để sửa lỗi URLs bị xuống dòng trong R2 fields
-- Chạy script này để loại bỏ các ký tự xuống dòng và khoảng trắng thừa

-- 1. Kiểm tra URLs bị lỗi trước khi sửa
SELECT '===== URLS BỊ LỖI (CÓ XUỐNG DÒNG) =====' as '';
SELECT
    id,
    cloudflare_video_id,
    LENGTH(r2_mp4_url) as old_length,
    r2_mp4_url
FROM post_contents
WHERE content_type = 1
    AND (r2_mp4_url LIKE '%\n%' OR r2_mp4_url LIKE '%\r%' OR r2_mp4_url LIKE '% %');

-- 2. Sửa r2_mp4_url - loại bỏ xuống dòng và khoảng trắng
UPDATE post_contents
SET r2_mp4_url = TRIM(REPLACE(REPLACE(REPLACE(r2_mp4_url, '\r\n', ''), '\n', ''), ' ', ''))
WHERE content_type = 1
    AND r2_mp4_url IS NOT NULL;

-- 3. Sửa r2_key - loại bỏ xuống dòng và khoảng trắng
UPDATE post_contents
SET r2_key = TRIM(REPLACE(REPLACE(REPLACE(r2_key, '\r\n', ''), '\n', ''), ' ', ''))
WHERE content_type = 1
    AND r2_key IS NOT NULL;

-- 4. Rebuild URLs đúng format cho tất cả videos
UPDATE post_contents
SET r2_mp4_url = CONCAT('https://orange-videos.272f6b5e0613deef5bd36a14e6c76188.r2.cloudflarestorage.com/videos/', cloudflare_video_id, '.mp4'),
    r2_key = CONCAT('videos/', cloudflare_video_id, '.mp4')
WHERE content_type = 1
    AND cloudflare_video_id IS NOT NULL
    AND r2_status = 'ready';

-- 5. Kiểm tra kết quả sau khi sửa
SELECT '===== KẾT QUẢ SAU KHI SỬA =====' as '';
SELECT
    id,
    cloudflare_video_id,
    LENGTH(r2_mp4_url) as new_length,
    r2_mp4_url
FROM post_contents
WHERE content_type = 1
    AND r2_mp4_url IS NOT NULL
ORDER BY id DESC
LIMIT 10;

-- 6. Verify không còn URLs bị lỗi
SELECT '===== KIỂM TRA FINAL =====' as '';
SELECT
    COUNT(*) as total_fixed_urls,
    MIN(LENGTH(r2_mp4_url)) as min_length,
    MAX(LENGTH(r2_mp4_url)) as max_length
FROM post_contents
WHERE content_type = 1
    AND r2_mp4_url IS NOT NULL;

-- 7. Kiểm tra xem có còn URLs với ký tự lạ không
SELECT
    CASE
        WHEN COUNT(*) = 0 THEN '✅ TẤT CẢ URLS ĐÃ ĐƯỢC SỬA THÀNH CÔNG!'
        ELSE CONCAT('⚠️ Còn ', COUNT(*), ' URLs cần kiểm tra')
    END as status
FROM post_contents
WHERE content_type = 1
    AND r2_mp4_url IS NOT NULL
    AND (r2_mp4_url LIKE '%\n%' OR r2_mp4_url LIKE '%\r%' OR r2_mp4_url LIKE '% %');

-- Script hoàn tất!
-- Tất cả URLs đã được sửa và format đúng chuẩn