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

-- Budget categories for projects
CREATE TABLE budget_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category ENUM('SUBSIDY', 'DEVELOPMENT', 'INCOME', 'EQUIPMENT', 'UNIFORM', 'BOOKS', 'LUNCH') NOT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
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
    budget_category ENUM('SUBSIDY', 'DEVELOPMENT', 'INCOME', 'EQUIPMENT', 'UNIFORM', 'BOOKS', 'LUNCH') NOT NULL,
    note TEXT,
    is_transfer BOOLEAN DEFAULT FALSE,
    is_transfer_in BOOLEAN DEFAULT FALSE,
    transfer_to_project_id INT NULL,
    transfer_to_category ENUM('SUBSIDY', 'DEVELOPMENT', 'INCOME', 'EQUIPMENT', 'UNIFORM', 'BOOKS', 'LUNCH') NULL,
    transfer_from_project_id INT NULL,
    transfer_from_category ENUM('SUBSIDY', 'DEVELOPMENT', 'INCOME', 'EQUIPMENT', 'UNIFORM', 'BOOKS', 'LUNCH') NULL,
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

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, display_name, role, approved, department, position) 
VALUES (
    'admin', 
    'admin@budgetcontrol.local', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'ผู้ดูแลระบบ', 
    'admin', 
    TRUE, 
    'กลุ่มงานงบประมาณ', 
    'ผู้ดูแลระบบ'
);

-- Insert sample user (password: user123)
INSERT INTO users (username, email, password_hash, display_name, role, approved, department, position) 
VALUES (
    'user1', 
    'user1@budgetcontrol.local', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: user123
    'ผู้ใช้ทดสอบ', 
    'user', 
    TRUE, 
    'กลุ่มงานบริหารวิชาการ', 
    'เจ้าหน้าที่'
);