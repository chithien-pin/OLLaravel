-- ============================================================================
-- Migration: Remove Legacy HLS Processing Fields from post_contents
-- Date: 2025-10-20
-- Description:
--   Remove legacy fields used for local video processing with FFmpeg.
--   All videos now use Cloudflare Stream exclusively.
--
-- Legacy fields being removed:
--   - is_hls: Boolean flag for HLS-processed videos
--   - hls_path: Path to local HLS playlist file
--   - processing_status: Status of local FFmpeg processing
--   - processing_error: Error messages from FFmpeg
--
-- WARNING: This is an IRREVERSIBLE migration. Data in these columns will be lost.
--          Make sure to backup your database before running this migration.
-- ============================================================================

-- ============================================================================
-- SAFETY CHECKS
-- ============================================================================

-- Check if we're connected to the correct database
SELECT 'Connected to database:', DATABASE();

-- Display current table structure before migration
SELECT 'Current post_contents structure:' AS info;
DESCRIBE post_contents;

-- Count total records that will be affected
SELECT
    'Total post_contents records:' AS info,
    COUNT(*) AS total_records,
    COUNT(CASE WHEN content_type = 1 THEN 1 END) AS video_records,
    COUNT(CASE WHEN is_hls = 1 THEN 1 END) AS legacy_hls_videos,
    COUNT(CASE WHEN cloudflare_video_id IS NOT NULL THEN 1 END) AS cloudflare_videos
FROM post_contents;

-- Show sample of legacy data that will be lost
SELECT
    'Sample of legacy data that will be LOST:' AS info;
SELECT
    id,
    post_id,
    content_type,
    is_hls,
    SUBSTRING(hls_path, 1, 50) AS hls_path_sample,
    processing_status,
    SUBSTRING(processing_error, 1, 50) AS processing_error_sample,
    created_at
FROM post_contents
WHERE is_hls = 1 OR hls_path IS NOT NULL OR processing_status != 'pending'
LIMIT 10;

-- ============================================================================
-- CONFIRMATION PROMPT
-- ============================================================================
--
-- STOP HERE AND REVIEW THE OUTPUT ABOVE!
--
-- Before proceeding, ensure:
-- 1. You have a complete database backup
-- 2. The database name shown above is correct
-- 3. You understand that legacy data will be permanently deleted
-- 4. All videos are using Cloudflare Stream (cloudflare_video_id is populated)
--
-- To proceed with the migration, run the commands below.
-- ============================================================================


-- ============================================================================
-- MIGRATION: DROP LEGACY COLUMNS
-- ============================================================================

-- Start transaction for safety
START TRANSACTION;

-- Drop the legacy columns from post_contents table
ALTER TABLE `post_contents`
    DROP COLUMN `is_hls`,
    DROP COLUMN `hls_path`,
    DROP COLUMN `processing_status`,
    DROP COLUMN `processing_error`;

-- Verify the migration was successful
SELECT 'Migration completed. New table structure:' AS info;
DESCRIBE post_contents;

-- Verify no legacy columns remain
SELECT
    'Verification - Columns after migration:' AS info;
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'post_contents'
ORDER BY ORDINAL_POSITION;

-- Display migration summary
SELECT
    'Migration Summary:' AS info,
    COUNT(*) AS total_records,
    COUNT(CASE WHEN content_type = 1 THEN 1 END) AS video_records,
    COUNT(CASE WHEN cloudflare_video_id IS NOT NULL THEN 1 END) AS cloudflare_videos,
    COUNT(CASE WHEN content_type = 0 THEN 1 END) AS image_records
FROM post_contents;

-- If everything looks good, commit the transaction
-- COMMIT;

-- If something went wrong, rollback the transaction
-- ROLLBACK;

-- ============================================================================
-- IMPORTANT: COMMIT OR ROLLBACK
-- ============================================================================
--
-- Review the output above carefully.
--
-- If migration was successful:
--   Run: COMMIT;
--
-- If there was an error or you want to undo:
--   Run: ROLLBACK;
--
-- ============================================================================
