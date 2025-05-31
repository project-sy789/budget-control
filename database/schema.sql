-- Budget Control System v2 Database Schema
-- Created for MySQL 5.7+

CREATE DATABASE IF NOT EXISTS budget_control CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE budget_control;

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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category_active (is_active),
    INDEX idx_category_key (category_key)
);

-- Budget categories for projects
CREATE TABLE budget_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (category) REFERENCES category_types(category_key) ON DELETE RESTRICT,
    UNIQUE KEY unique_project_category (project_id, category),
    INDEX idx_category (category)
);

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    budget_category VARCHAR(100) NOT NULL,
    note TEXT,
    is_transfer BOOLEAN DEFAULT FALSE,
    is_transfer_in BOOLEAN DEFAULT FALSE,
    transfer_to_project_id INT NULL,
    transfer_to_category VARCHAR(100) NULL,
    transfer_from_project_id INT NULL,
    transfer_from_category VARCHAR(100) NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (transfer_to_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (transfer_from_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_project_date (project_id, date),
    INDEX idx_category (budget_category),
    INDEX idx_transfer (is_transfer, transfer_to_project_id)
);

-- Sessions table for user sessions
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_expires (user_id, expires_at)
);

-- Insert default category types
INSERT INTO category_types (category_key, category_name, description, is_active) VALUES
('BOOKS', 'ค่าหนังสือและเอกสาร', 'ค่าใช้จ่ายสำหรับหนังสือ เอกสาร และสื่อการเรียนการสอน', 1),
('EQUIPMENT', 'ค่าครุภัณฑ์และอุปกรณ์', 'ค่าใช้จ่ายสำหรับครุภัณฑ์ อุปกรณ์ และเครื่องมือต่างๆ', 1),
('DEVELOPMENT', 'ค่าพัฒนาและก่อสร้าง', 'ค่าใช้จ่ายสำหรับการพัฒนาและก่อสร้างโครงการ', 1),
('LUNCH', 'ค่าอาหารและเครื่องดื่ม', 'ค่าใช้จ่ายสำหรับอาหาร เครื่องดื่ม และการเลี้ยงรับรอง', 1),
('SUBSIDY', 'ค่าใช้จ่ายอื่นๆ', 'ค่าใช้จ่ายเบ็ดเตล็ดและค่าใช้จ่ายที่ไม่อยู่ในหมวดอื่น', 1),
('UNIFORM', 'ค่าเครื่องแบบและเครื่องแต่งกาย', 'ค่าใช้จ่ายสำหรับเครื่องแบบ เครื่องแต่งกาย และอุปกรณ์แต่งตัว', 1),
('TRAINING', 'ค่าฝึกอบรมและสัมมนา', 'ค่าใช้จ่ายสำหรับการฝึกอบรม สัมมนา และพัฒนาบุคลากร', 1),
('MATERIAL', 'ค่าวัสดุสำนักงาน', 'ค่าใช้จ่ายสำหรับวัสดุสำนักงานและอุปกรณ์การทำงาน', 1),
('TRANSPORT', 'ค่าเดินทางและขนส่ง', 'ค่าใช้จ่ายสำหรับการเดินทาง ขนส่ง และค่าพาหนะ', 1),
('UTILITY', 'ค่าสาธารณูปโภค', 'ค่าใช้จ่ายสำหรับไฟฟ้า น้ำประปา โทรศัพท์ และสาธารณูปโภคอื่นๆ', 1);

-- Default users will be created during installation process
-- No pre-inserted users in schema to avoid conflicts during installation