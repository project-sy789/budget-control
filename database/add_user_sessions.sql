-- Add user_sessions table to fix login issues
USE subyaisite_budget;

-- Check if user_sessions table exists and create if not
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_expires (user_id, expires_at)
);

-- Show tables to verify
SHOW TABLES;

-- Show user_sessions table structure
DESCRIBE user_sessions;

-- Check existing users
SELECT id, username, email, role, approved FROM users;