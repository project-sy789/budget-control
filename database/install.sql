-- Budget Control System v2 Installation Script
-- This script sets up the complete database schema and default data

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create Database (Optional, typically specific to host)
-- CREATE DATABASE IF NOT EXISTS budget_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE budget_db;

-- --------------------------------------------------------

-- Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager','user','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `approved` tinyint(1) DEFAULT 0,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `fiscal_years`
CREATE TABLE IF NOT EXISTS `fiscal_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Fiscal Year Name e.g. 2568',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `projects`
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `work_group` enum('academic','budget','hr','general','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `responsible_person` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','completed','suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `remaining_budget` decimal(15,2) GENERATED ALWAYS AS (`budget`) VIRTUAL,
  `used_budget` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fiscal_year_id` (`fiscal_year_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_work_group` (`work_group`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `category_types`
CREATE TABLE IF NOT EXISTS `category_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_key` (`category_key`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `category_types_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `budget_categories`
CREATE TABLE IF NOT EXISTS `budget_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `category_type_id` int(11) NOT NULL,
  `budget_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_category` (`project_id`,`category_type_id`),
  KEY `category_type_id` (`category_type_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_category_type_id` (`category_type_id`),
  CONSTRAINT `budget_categories_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_categories_ibfk_2` FOREIGN KEY (`category_type_id`) REFERENCES `category_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `transactions`
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `category_type_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_type` enum('income','expense','transfer_in','transfer_out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `reference_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_from_project_id` int(11) DEFAULT NULL,
  `transfer_to_project_id` int(11) DEFAULT NULL,
  `is_transfer` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `category_type_id` (`category_type_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_category_type_id` (`category_type_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_transaction_type` (`transaction_type`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`category_type_id`) REFERENCES `category_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `budget_transfers`
CREATE TABLE IF NOT EXISTS `budget_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `from_category_id` int(11) NOT NULL,
  `to_category_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `from_category_id` (`from_category_id`),
  KEY `to_category_id` (`to_category_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_transfer_date` (`transfer_date`),
  CONSTRAINT `budget_transfers_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_transfers_ibfk_2` FOREIGN KEY (`from_category_id`) REFERENCES `category_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_transfers_ibfk_3` FOREIGN KEY (`to_category_id`) REFERENCES `category_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_transfers_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `user_sessions`
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_user_expires` (`user_id`,`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `system_settings`
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setting_type` enum('text','number','boolean','file') COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Dumping data for table `users`
-- Default Admin User (Password: admin123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `display_name`, `role`, `approved`, `department`, `position`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 1, 'IT', 'System Admin');

-- Dumping data for table `fiscal_years`
INSERT INTO `fiscal_years` (`name`, `start_date`, `end_date`, `is_active`) VALUES
('2568', '2024-10-01', '2025-09-30', 1);

-- Dumping data for table `category_types`
INSERT INTO `category_types` (`category_key`, `category_name`, `description`, `is_active`) VALUES
('ACTIVITY_FUNDS', 'เงินกิจกรรมพัฒนาผู้เรียน', 'เงินสำหรับกิจกรรมพัฒนาผู้เรียน', 1),
('SUPPLIES_FUNDS', 'เงินค่าอุปกรณ์การเรียน', 'เงินสำหรับจัดซื้ออุปกรณ์การเรียนการสอน', 1),
('UNIFORMS_FUNDS', 'เงินค่าเครื่องแบบนักเรียน', 'เงินสำหรับจัดซื้อเครื่องแบบนักเรียน', 1),
('INCOME', 'เงินรายได้สถานศึกษา', 'เงินรายได้ของสถานศึกษา', 1),
('LUNCH', 'เงินอาหารกลางวัน', 'เงินสำหรับอาหารกลางวันนักเรียน', 1),
('SUBSIDY', 'เงินอุดหนุนรายหัว', 'เงินอุดหนุนรายหัวจากรัฐบาล', 1);

-- Dumping data for table `system_settings`
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('site_name', 'ระบบควบคุมงบประมาณ', 'text', 'ชื่อเว็บไซต์'),
('organization_name', 'โรงเรียนซับใหญ่วิทยาคม', 'text', 'ชื่อหน่วยงาน'),
('site_title', 'ระบบควบคุมงบประมาณ - โรงเรียนซับใหญ่วิทยาคม', 'text', 'ชื่อเรื่องของเว็บไซต์'),
('site_icon', '', 'file', 'ไอคอนของเว็บไซต์'),
('enable_pwa', '1', 'boolean', 'เปิดใช้งาน Progressive Web App'),
('year_label_type', 'fiscal_year', 'text', 'รูปแบบชื่อปีที่ใช้เรียก (fiscal_year, academic_year, budget_year)');

SET FOREIGN_KEY_CHECKS = 1;

