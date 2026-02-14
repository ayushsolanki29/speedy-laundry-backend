<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/functions.php';

/**
 * Migrates existing databases to the latest schema.
 * Run: php apply_updates.php
 * New installs: use init.php or run sql/schema.sql directly.
 */

try {
    $db = Database::getInstance()->getConnection();
    echo "Connected to database successfully.\n";

    // 1. Create blog_likes and blog_comments if not exist
    $db->exec("CREATE TABLE IF NOT EXISTS `blog_likes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `blog_id` INT NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `user_agent` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`blog_id`) REFERENCES `blogs`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_like` (`blog_id`, `ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "blog_likes table ready.\n";

    $db->exec("CREATE TABLE IF NOT EXISTS `blog_comments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `blog_id` INT NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `content` TEXT NOT NULL,
        `is_admin_reply` BOOLEAN DEFAULT FALSE,
        `parent_id` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`blog_id`) REFERENCES `blogs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`parent_id`) REFERENCES `blog_comments`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "blog_comments table ready.\n";

    // 2. Add likes_count, comments_count to blogs
    foreach (['likes_count', 'comments_count'] as $col) {
        try {
            $db->exec("ALTER TABLE `blogs` ADD COLUMN `$col` INT DEFAULT 0");
            echo "Column $col added to blogs.\n";
        } catch (PDOException $e) {
            if ($e->getCode() == '42S21') {
                echo "Column $col already exists.\n";
            } else {
                throw $e;
            }
        }
    }

    // 3. Create email_queue table
    $db->exec("CREATE TABLE IF NOT EXISTS `email_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `to_email` VARCHAR(255) NOT NULL,
        `to_name` VARCHAR(100),
        `subject` VARCHAR(255) NOT NULL,
        `body` TEXT NOT NULL,
        `type` ENUM('enquiry_admin', 'enquiry_user', 'business_admin', 'business_user') NOT NULL,
        `is_html` TINYINT UNSIGNED DEFAULT 0,
        `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        `attempts` TINYINT UNSIGNED DEFAULT 0,
        `max_attempts` TINYINT UNSIGNED DEFAULT 3,
        `error_message` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `sent_at` DATETIME,
        INDEX `idx_status` (`status`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "email_queue table ready.\n";

    // 4. Create admin_sessions table
    $db->exec("CREATE TABLE IF NOT EXISTS `admin_sessions` (
        `token` VARCHAR(64) PRIMARY KEY,
        `admin_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
        INDEX `idx_admin_id` (`admin_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "admin_sessions table ready.\n";

    // 4b. Create reviews table
    $db->exec("CREATE TABLE IF NOT EXISTS `reviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `content` TEXT NOT NULL,
        `rating` TINYINT UNSIGNED DEFAULT 5,
        `photo_url` VARCHAR(500),
        `display_order` INT DEFAULT 0,
        `is_pinned` TINYINT UNSIGNED DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "reviews table ready.\n";

    $count = (int) $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    if ($count === 0) {
        $seedFile = __DIR__ . '/../sql/seed-reviews.sql';
        if (file_exists($seedFile)) {
            $sql = file_get_contents($seedFile);
            $db->exec($sql);
            echo "Seed reviews inserted.\n";
        }
    }

    // 5. Add is_html to email_queue (for very old installs that created table without it)
    try {
        $db->exec("ALTER TABLE `email_queue` ADD COLUMN `is_html` TINYINT UNSIGNED DEFAULT 0");
        echo "email_queue is_html column added.\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "email_queue is_html column already exists.\n";
        } else {
            throw $e;
        }
    }

    echo "All updates applied successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
