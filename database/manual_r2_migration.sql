-- ============================================
-- R2 MIGRATION SCRIPT FOR ORANGE DATABASE
-- ============================================
-- Purpose: Add R2 storage fields to support hybrid Cloudflare Stream + R2 architecture
-- Date: 2024-11-02
-- Author: Orange Dev Team
--
-- INSTRUCTIONS:
-- 1. SSH to server: ssh root@69.62.115.197
-- 2. Connect to MySQL: docker exec -it ol_mysql mysql -u orange_user -p orange_db
-- 3. Password: orange_pass
-- 4. Run this script: source /path/to/manual_r2_migration.sql
-- OR directly paste the SQL commands below
-- ============================================

USE orange_db;

-- Check if columns already exist before adding
-- This prevents errors if running script multiple times
DELIMITER $$

DROP PROCEDURE IF EXISTS add_r2_columns$$
CREATE PROCEDURE add_r2_columns()
BEGIN
    -- Check and add r2_mp4_url column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND COLUMN_NAME = 'r2_mp4_url'
    ) THEN
        ALTER TABLE `post_contents`
        ADD COLUMN `r2_mp4_url` VARCHAR(500) NULL DEFAULT NULL
        COMMENT 'Direct MP4 URL from R2 bucket for free bandwidth'
        AFTER `cloudflare_hls_url`;
    END IF;

    -- Check and add r2_key column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND COLUMN_NAME = 'r2_key'
    ) THEN
        ALTER TABLE `post_contents`
        ADD COLUMN `r2_key` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'R2 object key (path in bucket)'
        AFTER `r2_mp4_url`;
    END IF;

    -- Check and add r2_file_size column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND COLUMN_NAME = 'r2_file_size'
    ) THEN
        ALTER TABLE `post_contents`
        ADD COLUMN `r2_file_size` BIGINT NULL DEFAULT NULL
        COMMENT 'File size in bytes on R2'
        AFTER `r2_key`;
    END IF;

    -- Check and add r2_uploaded_at column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND COLUMN_NAME = 'r2_uploaded_at'
    ) THEN
        ALTER TABLE `post_contents`
        ADD COLUMN `r2_uploaded_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'When video was uploaded to R2'
        AFTER `r2_file_size`;
    END IF;

    -- Check and add use_r2 column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND COLUMN_NAME = 'use_r2'
    ) THEN
        ALTER TABLE `post_contents`
        ADD COLUMN `use_r2` BOOLEAN DEFAULT FALSE
        COMMENT 'Whether to prefer R2 over Stream for this video'
        AFTER `r2_uploaded_at`;
    END IF;

    -- Check and add r2_status column
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND COLUMN_NAME = 'r2_status'
    ) THEN
        ALTER TABLE `post_contents`
        ADD COLUMN `r2_status` VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'R2 archive status: pending, processing, ready, failed'
        AFTER `use_r2`;
    END IF;
END$$

DELIMITER ;

-- Execute the procedure
CALL add_r2_columns();

-- Drop the temporary procedure
DROP PROCEDURE IF EXISTS add_r2_columns;

-- Add indexes for better query performance
-- Check if indexes exist before creating
DELIMITER $$

DROP PROCEDURE IF EXISTS add_r2_indexes$$
CREATE PROCEDURE add_r2_indexes()
BEGIN
    -- Index on r2_status
    IF NOT EXISTS (
        SELECT * FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND INDEX_NAME = 'idx_r2_status'
    ) THEN
        CREATE INDEX `idx_r2_status` ON `post_contents` (`r2_status`);
    END IF;

    -- Index on use_r2
    IF NOT EXISTS (
        SELECT * FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND INDEX_NAME = 'idx_use_r2'
    ) THEN
        CREATE INDEX `idx_use_r2` ON `post_contents` (`use_r2`);
    END IF;

    -- Index on r2_uploaded_at
    IF NOT EXISTS (
        SELECT * FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND INDEX_NAME = 'idx_r2_uploaded_at'
    ) THEN
        CREATE INDEX `idx_r2_uploaded_at` ON `post_contents` (`r2_uploaded_at`);
    END IF;

    -- Composite index for finding videos to migrate
    IF NOT EXISTS (
        SELECT * FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_contents'
        AND INDEX_NAME = 'idx_r2_migration'
    ) THEN
        CREATE INDEX `idx_r2_migration` ON `post_contents` (`content_type`, `cloudflare_status`, `r2_status`);
    END IF;
END$$

DELIMITER ;

-- Execute the procedure
CALL add_r2_indexes();

-- Drop the temporary procedure
DROP PROCEDURE IF EXISTS add_r2_indexes;

-- Update table comment
ALTER TABLE `post_contents`
COMMENT = 'Post content with Cloudflare Stream HLS and R2 MP4 hybrid storage';

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check if all columns were added successfully
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'post_contents'
    AND COLUMN_NAME IN ('r2_mp4_url', 'r2_key', 'r2_file_size', 'r2_uploaded_at', 'use_r2', 'r2_status')
ORDER BY
    ORDINAL_POSITION;

-- Check indexes
SELECT
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM
    information_schema.STATISTICS
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'post_contents'
    AND INDEX_NAME LIKE '%r2%'
ORDER BY
    INDEX_NAME, SEQ_IN_INDEX;

-- ============================================
-- STATISTICS QUERIES (Run after migration)
-- ============================================

-- Count videos by status
SELECT
    'Total Videos' as metric,
    COUNT(*) as count
FROM post_contents
WHERE content_type = 1
UNION ALL
SELECT
    'Videos with Cloudflare Stream',
    COUNT(*)
FROM post_contents
WHERE content_type = 1 AND cloudflare_video_id IS NOT NULL
UNION ALL
SELECT
    'Videos Ready for R2 Migration',
    COUNT(*)
FROM post_contents
WHERE content_type = 1
    AND cloudflare_status = 'ready'
    AND r2_mp4_url IS NULL
UNION ALL
SELECT
    'Videos Already in R2',
    COUNT(*)
FROM post_contents
WHERE content_type = 1 AND r2_mp4_url IS NOT NULL;

-- ============================================
-- SAMPLE QUERIES FOR TESTING
-- ============================================

-- Find first 10 videos ready for R2 migration
SELECT
    id,
    post_id,
    cloudflare_video_id,
    cloudflare_status,
    r2_status,
    created_at
FROM
    post_contents
WHERE
    content_type = 1
    AND cloudflare_status = 'ready'
    AND r2_mp4_url IS NULL
ORDER BY
    created_at DESC
LIMIT 10;

-- ============================================
-- ROLLBACK SCRIPT (If needed)
-- ============================================
-- CAUTION: This will remove all R2 related columns and data!
--
-- ALTER TABLE `post_contents`
-- DROP COLUMN `r2_mp4_url`,
-- DROP COLUMN `r2_key`,
-- DROP COLUMN `r2_file_size`,
-- DROP COLUMN `r2_uploaded_at`,
-- DROP COLUMN `use_r2`,
-- DROP COLUMN `r2_status`;
--
-- DROP INDEX `idx_r2_status` ON `post_contents`;
-- DROP INDEX `idx_use_r2` ON `post_contents`;
-- DROP INDEX `idx_r2_uploaded_at` ON `post_contents`;
-- DROP INDEX `idx_r2_migration` ON `post_contents`;

-- ============================================
-- END OF MIGRATION SCRIPT
-- ============================================

-- Success message
SELECT '✅ R2 Migration Script Completed Successfully!' as Status;