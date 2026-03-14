-- Speedy Laundry - Full Database Schema
-- Run this file to create or sync all tables

-- =============================================
-- Core
-- =============================================

CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `role` ENUM('super_admin', 'admin', 'staff') DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `enquiries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `address` VARCHAR(255) NULL,
    `postcode` VARCHAR(20) NOT NULL,
    `service` VARCHAR(50),
    `business_name` VARCHAR(150) NULL,
    `industry` VARCHAR(100) NULL,
    `message` TEXT,
    `status` ENUM('new', 'in_progress', 'completed', 'cancelled') DEFAULT 'new',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `visits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `visit_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_visit` (`ip_address`, `visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email Queue
-- Successfully sent emails are automatically removed from this table
-- =============================================

CREATE TABLE IF NOT EXISTS `email_queue` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Blog
-- =============================================

CREATE TABLE IF NOT EXISTS `blogs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `excerpt` TEXT,
    `content` LONGTEXT NOT NULL,
    `image_url` VARCHAR(255),
    `category` VARCHAR(100),
    `author_id` INT,
    `status` ENUM('draft', 'published') DEFAULT 'draft',
    `published_at` DATETIME,
    `likes_count` INT DEFAULT 0,
    `comments_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`author_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blog_likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `blog_id` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`blog_id`) REFERENCES `blogs`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_like` (`blog_id`, `ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blog_comments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Reviews (customer testimonials)
-- =============================================

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `content` TEXT NOT NULL,
    `rating` TINYINT UNSIGNED DEFAULT 5,
    `photo_url` VARCHAR(500),
    `display_order` INT DEFAULT 0,
    `is_pinned` TINYINT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed existing reviews (only when table is empty)
INSERT INTO `reviews` (`name`, `content`, `rating`, `display_order`)
SELECT * FROM (
    SELECT 'Jane Whitfield' AS name, 'Speedy Iron/Laundry is the most wonderful Company. Have used them for twenty years and not once have they let me down. Ironing of all kinds perfectly done, dry cleaning too. Latterly I have been using the laundry service for my bedding & am as impressed as I expected to be!' AS content, 5 AS rating, 0 AS display_order
    UNION ALL SELECT 'Anna Fountain', 'Amazing! My long delicate dress was very dirty after dragging on the floor at a wedding, I was so surprised they managed to get the dirt out of the bottom of the dress. Highly recommend !!', 5, 1
    UNION ALL SELECT 'Ann', 'We have used Speedy Iron for about 15 years or possibly more. One day I had a lot of ironing to do for a holiday and needed it quickly. It was a 24hour approx turn around service. They came to pick up my bag of ironing and weighed it.', 5, 2
    UNION ALL SELECT 'Jonathan Martin', 'Excellent service!! For both cleanliness and liaison''s!! Thank you for going beyond expectations!!', 5, 3
    UNION ALL SELECT 'Les Poole', 'Best customer service I''ve experienced in a long time, both from the office staff & the pick up/delivery service. Nothing too much trouble & any queries responded to & resolved immediately. Provides a first class service.', 5, 4
    UNION ALL SELECT 'Andrew Daw', 'We have used Speedy Laundry for several years and they have always been reliable, as well as speedy no matter how much we ask them to do. We would recommend them to anyone who needs an ironing or dry cleaning service.', 5, 5
    UNION ALL SELECT 'Nick Winfield', 'Excellent service, we have been using Speedy Laundry for years for our ironing and they have NEVER let us down. Trustworthy and reliable, I would not hesitate to recommend them.', 5, 6
    UNION ALL SELECT 'Malcolm Cleaver', 'Fantastic collect and drop off ironing service. Been using them for over 20 years and they''ve never missed a beat. Top quality personal service.', 5, 7
) t
WHERE NOT EXISTS (SELECT 1 FROM reviews LIMIT 1);

-- =============================================
-- Admin Sessions (for token validation)
-- =============================================

CREATE TABLE IF NOT EXISTS `admin_sessions` (
    `token` VARCHAR(64) PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
    INDEX `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- Seed Data
-- =============================================

INSERT IGNORE INTO `admins` (`username`, `password`, `email`, `role`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'super_admin');
-- Password: 'password'
