-- ============================================================
-- SQL Script: Add country, ip_network, language fields to users table
-- Date: 2025-11-25
-- Description: Track user registration location and language preference
-- ============================================================

-- Add country field (detected from IP via ip-api.com)
ALTER TABLE `users`
ADD COLUMN `country` VARCHAR(100) NULL DEFAULT NULL
COMMENT 'Country code detected from IP (e.g., VN, US, FR)'
AFTER `longitude`;

-- Add ip_network field (device IP at registration)
ALTER TABLE `users`
ADD COLUMN `ip_network` VARCHAR(45) NULL DEFAULT NULL
COMMENT 'IP address at registration time'
AFTER `country`;

-- Add language field (device language preference)
ALTER TABLE `users`
ADD COLUMN `language` VARCHAR(10) NULL DEFAULT NULL
COMMENT 'Device language code (e.g., vi, en, fr)'
AFTER `ip_network`;

-- Add index for country (useful for analytics/filtering)
ALTER TABLE `users`
ADD INDEX `idx_country` (`country`);

-- ============================================================
-- Verify changes
-- ============================================================
-- DESCRIBE users;
-- SHOW COLUMNS FROM users WHERE Field IN ('country', 'ip_network', 'language');
