-- =====================================================
-- Cloudflare Stream Migration Script
-- Date: 2025-10-19
-- Description: Add Cloudflare Stream fields to post_contents table
-- =====================================================

-- Add Cloudflare Stream columns to post_contents table
ALTER TABLE `post_contents`
    ADD COLUMN `cloudflare_video_id` VARCHAR(255) NULL COMMENT 'Cloudflare Stream video UID' AFTER `content_type`,
    ADD COLUMN `cloudflare_stream_url` VARCHAR(255) NULL COMMENT 'Cloudflare Stream playback URL' AFTER `cloudflare_video_id`,
    ADD COLUMN `cloudflare_thumbnail_url` VARCHAR(255) NULL COMMENT 'Cloudflare Stream thumbnail URL' AFTER `cloudflare_stream_url`,
    ADD COLUMN `cloudflare_hls_url` VARCHAR(255) NULL COMMENT 'Cloudflare HLS manifest URL' AFTER `cloudflare_thumbnail_url`,
    ADD COLUMN `cloudflare_dash_url` VARCHAR(255) NULL COMMENT 'Cloudflare DASH manifest URL' AFTER `cloudflare_hls_url`,
    ADD COLUMN `cloudflare_status` ENUM('pending', 'uploading', 'processing', 'ready', 'error') NULL COMMENT 'Video processing status' AFTER `cloudflare_dash_url`,
    ADD COLUMN `cloudflare_error` TEXT NULL COMMENT 'Error message if any' AFTER `cloudflare_status`,
    ADD COLUMN `cloudflare_duration` INT NULL COMMENT 'Video duration in seconds' AFTER `cloudflare_error`,
    ADD COLUMN `cloudflare_upload_id` VARCHAR(255) NULL COMMENT 'TUS upload ID for tracking' AFTER `cloudflare_duration`;

-- Add indexes for performance
ALTER TABLE `post_contents`
    ADD INDEX `idx_cloudflare_video_id` (`cloudflare_video_id`),
    ADD INDEX `idx_cloudflare_status` (`cloudflare_status`);

-- =====================================================
-- Rollback Script (if needed)
-- =====================================================
-- To rollback these changes, uncomment and run the following:
/*
ALTER TABLE `post_contents`
    DROP INDEX `idx_cloudflare_video_id`,
    DROP INDEX `idx_cloudflare_status`;

ALTER TABLE `post_contents`
    DROP COLUMN `cloudflare_video_id`,
    DROP COLUMN `cloudflare_stream_url`,
    DROP COLUMN `cloudflare_thumbnail_url`,
    DROP COLUMN `cloudflare_hls_url`,
    DROP COLUMN `cloudflare_dash_url`,
    DROP COLUMN `cloudflare_status`,
    DROP COLUMN `cloudflare_error`,
    DROP COLUMN `cloudflare_duration`,
    DROP COLUMN `cloudflare_upload_id`;
*/

-- =====================================================
-- Verification Query
-- =====================================================
-- Run this to verify the migration was successful:
/*
DESCRIBE post_contents;

-- Check if new columns exist
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM
    INFORMATION_SCHEMA.COLUMNS
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'post_contents'
    AND COLUMN_NAME LIKE 'cloudflare_%'
ORDER BY
    ORDINAL_POSITION;

-- Check indexes
SHOW INDEX FROM post_contents WHERE Key_name LIKE 'idx_cloudflare_%';
*/

-- =====================================================
-- Sample Data Migration (Optional)
-- =====================================================
-- If you want to migrate existing video posts to use placeholder Cloudflare data:
/*
-- Mark all existing video posts as pending Cloudflare migration
UPDATE post_contents
SET cloudflare_status = 'pending'
WHERE content_type = 1  -- Video type
    AND cloudflare_video_id IS NULL;

-- Count videos that need migration
SELECT COUNT(*) as videos_to_migrate
FROM post_contents
WHERE content_type = 1
    AND cloudflare_video_id IS NULL;
*/