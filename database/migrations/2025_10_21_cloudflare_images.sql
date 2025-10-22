-- ============================================================================
-- Migration: Add Cloudflare Images fields to post_contents table
-- Created: 2025-10-21
-- Description: Adds support for Cloudflare Images (direct upload)
-- ============================================================================

USE `orange_db`;

-- Add Cloudflare Images fields to post_contents table
ALTER TABLE `post_contents`
ADD COLUMN `cloudflare_image_id` VARCHAR(255) NULL COMMENT 'Cloudflare Images ID' AFTER `cloudflare_upload_id`,
ADD COLUMN `cloudflare_image_url` TEXT NULL COMMENT 'Cloudflare Images public URL' AFTER `cloudflare_image_id`,
ADD COLUMN `cloudflare_image_variants` JSON NULL COMMENT 'Cloudflare Images variant URLs (thumbnail, medium, large)' AFTER `cloudflare_image_url`;

-- Add index for performance
ALTER TABLE `post_contents`
ADD INDEX `idx_cloudflare_image_id` (`cloudflare_image_id`);

-- Verify columns were added
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'post_contents'
  AND COLUMN_NAME IN ('cloudflare_image_id', 'cloudflare_image_url', 'cloudflare_image_variants')
ORDER BY ORDINAL_POSITION;

-- Show indexes
SHOW INDEX FROM `post_contents` WHERE Key_name = 'idx_cloudflare_image_id';

-- Success message
SELECT 'Migration completed successfully!' AS status;
