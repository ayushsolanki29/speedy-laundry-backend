-- Run this ONLY if you have an existing database created from an older schema
-- Adds likes_count and comments_count to blogs table.
-- New installs: run schema.sql only (it includes these columns).
-- If columns already exist, you may see "Duplicate column" errors - safe to ignore.
ALTER TABLE `blogs` ADD COLUMN `likes_count` INT DEFAULT 0;
ALTER TABLE `blogs` ADD COLUMN `comments_count` INT DEFAULT 0;

ALTER TABLE email_queue ADD COLUMN reply_to_name VARCHAR(100) DEFAULT NULL AFTER reply_to
ALTER TABLE email_queue ADD COLUMN reply_to VARCHAR(255) DEFAULT NULL AFTER to_name