-- ============================================================================
-- ROLLBACK: Restore Legacy HLS Processing Fields to post_contents
-- Date: 2025-10-20
-- Description:
--   This script will RESTORE the legacy fields that were removed by the
--   2025_10_20_remove_legacy_hls_fields.sql migration.
--
-- Fields being restored:
--   - is_hls: Boolean flag for HLS-processed videos
--   - hls_path: Path to local HLS playlist file
--   - processing_status: Status of local FFmpeg processing
--   - processing_error: Error messages from FFmpeg
--
-- WARNING:
--   - This only restores the STRUCTURE, not the DATA
--   - Original data in these fields is permanently lost
--   - Fields will be recreated with default values
--   - Only use this if you need to rollback the application code
-- ============================================================================

-- ============================================================================
-- SAFETY CHECKS
-- ============================================================================

-- Check if we're connected to the correct database
SELECT 'Connected to database:', DATABASE();

-- Display current table structure before rollback
SELECT 'Current post_contents structure:' AS info;
DESCRIBE post_contents;

-- Check if legacy columns already exist (prevent duplicate column error)
SELECT
    'Checking for existing legacy columns:' AS info;
SELECT
    COLUMN_NAME,
    'EXISTS' AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'post_contents'
    AND COLUMN_NAME IN ('is_hls', 'hls_path', 'processing_status', 'processing_error');

-- ============================================================================
-- ROLLBACK: RESTORE LEGACY COLUMNS
-- ============================================================================

-- Start transaction for safety
START TRANSACTION;

-- Add back the legacy columns after cloudflare_upload_id
-- Note: Column order matches the original schema

-- Add is_hls column
ALTER TABLE `post_contents`
    ADD COLUMN `is_hls` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `cloudflare_upload_id`
    COMMENT 'Legacy: Boolean flag for HLS-processed videos';

-- Add hls_path column
ALTER TABLE `post_contents`
    ADD COLUMN `hls_path` VARCHAR(255) NULL DEFAULT NULL
    AFTER `is_hls`
    COMMENT 'Legacy: Path to local HLS playlist file';

-- Add processing_status column
ALTER TABLE `post_contents`
    ADD COLUMN `processing_status` ENUM('pending', 'processing', 'completed', 'failed')
    NOT NULL DEFAULT 'pending'
    AFTER `hls_path`
    COMMENT 'Legacy: Status of local FFmpeg processing';

-- Add processing_error column
ALTER TABLE `post_contents`
    ADD COLUMN `processing_error` TEXT NULL DEFAULT NULL
    AFTER `processing_status`
    COMMENT 'Legacy: Error messages from FFmpeg';

-- Verify the rollback was successful
SELECT 'Rollback completed. New table structure:' AS info;
DESCRIBE post_contents;

-- Verify all legacy columns are restored
SELECT
    'Verification - Restored columns:' AS info;
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'post_contents'
    AND COLUMN_NAME IN ('is_hls', 'hls_path', 'processing_status', 'processing_error')
ORDER BY ORDINAL_POSITION;

-- Display rollback summary
SELECT
    'Rollback Summary:' AS info,
    COUNT(*) AS total_records,
    COUNT(CASE WHEN content_type = 1 THEN 1 END) AS video_records,
    COUNT(CASE WHEN is_hls = 1 THEN 1 END) AS hls_videos_count,
    COUNT(CASE WHEN cloudflare_video_id IS NOT NULL THEN 1 END) AS cloudflare_videos
FROM post_contents;

-- Show sample data with restored columns (all will have default values)
SELECT
    'Sample records with restored columns (showing default values):' AS info;
SELECT
    id,
    post_id,
    content_type,
    is_hls,
    hls_path,
    processing_status,
    processing_error,
    cloudflare_video_id,
    cloudflare_status
FROM post_contents
WHERE content_type = 1
LIMIT 5;

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
-- If rollback was successful:
--   Run: COMMIT;
--
-- If there was an error or you want to undo:
--   Run: ROLLBACK;
--
-- After committing, remember to also revert your application code
-- to the previous version that uses these legacy fields.
--
-- ============================================================================
