CREATE DATABASE IF NOT EXISTS `catalogo3d` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `catalogo3d`;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255),
  `original_name` VARCHAR(255),
  `ext` VARCHAR(10),
  `path` VARCHAR(255),
  `thumb_path` VARCHAR(255),
  `size` BIGINT,
  `description` TEXT,
  `print_time` INT,
  `material_amount` DECIMAL(10,2),
  `multicolor` TINYINT(1),
  `category_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`category_id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
