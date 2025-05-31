-- Budget Control System v2 Database Schema
-- Created for MySQL 5.7+

CREATE DATABASE IF NOT EXISTS subyaisite_budget CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE subyaisite_budget;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'pending') DEFAULT 'pending',
    approved BOOLEAN DEFAULT FALSE,
    department VARCHAR(100),
    position VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Projects table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    work_group ENUM('academic', 'budget', 'hr', 'general', 'other') NOT NULL,
    responsible_person VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_work_group (work_group),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
);

-- Category types for dynamic budget categories
CREATE TABLE category_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(50) UNIQUE NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Budget categories for projects
CREATE TABLE budget_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category_type_id INT NOT NULL,
    budget_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (category_type_id) REFERENCES category_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_category (project_id, category_type_id),
    INDEX idx_project_id (project_id),
    INDEX idx_category_type_id (category_type_id)
);

-- Transactions table for budget tracking
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category_type_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_type ENUM('income', 'expense', 'transfer_in', 'transfer_out') NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    reference_number VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (category_type_id) REFERENCES category_types(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_project_id (project_id),
    INDEX idx_category_type_id (category_type_id),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_transaction_type (transaction_type)
);

-- Budget transfers between categories
CREATE TABLE budget_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    from_category_id INT NOT NULL,
    to_category_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    transfer_date DATE NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (from_category_id) REFERENCES category_types(id) ON DELETE CASCADE,
    FOREIGN KEY (to_category_id) REFERENCES category_types(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_project_id (project_id),
    INDEX idx_transfer_date (transfer_date)
);

-- Insert default category types
INSERT INTO category_types (category_key, category_name, description) VALUES
('ACTIVITY_FUNDS', 'เงินกิจกรรมพัฒนาผู้เรียน', 'เงินสำหรับกิจกรรมพัฒนาผู้เรียน'),
('SUPPLIES_FUNDS', 'เงินค่าอุปกรณ์การเรียน', 'เงินสำหรับจัดซื้ออุปกรณ์การเรียนการสอน'),
('UNIFORMS_FUNDS', 'เงินค่าเครื่องแบบนักเรียน', 'เงินสำหรับจัดซื้อเครื่องแบบนักเรียน'),
('INCOME', 'เงินรายได้สถานศึกษา', 'เงินรายได้ของสถานศึกษา'),
('LUNCH', 'เงินอาหารกลางวัน', 'เงินสำหรับอาหารกลางวันนักเรียน'),
('SUBSIDY', 'เงินอุดหนุนรายหัว', 'เงินอุดหนุนรายหัวจากรัฐบาล');

-- No default admin user - will be created during installation