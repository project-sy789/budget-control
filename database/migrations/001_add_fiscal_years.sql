-- Add fiscal_years table
CREATE TABLE IF NOT EXISTS fiscal_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Fiscal Year Name e.g. 2567',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add fiscal_year_id to projects table
ALTER TABLE projects ADD COLUMN fiscal_year_id INT AFTER id;
ALTER TABLE projects ADD CONSTRAINT fk_projects_fiscal_year FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id) ON DELETE SET NULL;
ALTER TABLE projects ADD INDEX idx_fiscal_year (fiscal_year_id);

-- Insert default fiscal year (Current Thai Year)
-- Insert default fiscal year (2568)
INSERT INTO fiscal_years (name, start_date, end_date, is_active) 
VALUES ('2568', '2024-10-01', '2025-09-30', TRUE);

-- Update existing projects to use the new fiscal year
UPDATE projects SET fiscal_year_id = (SELECT id FROM fiscal_years WHERE name = '2568');
