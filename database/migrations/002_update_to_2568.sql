-- 002_update_to_2568.sql
-- Run this script if you have already added the 'fiscal_years' table and 'fiscal_year_id' column
-- This script only updates the data to set 2568 as the default

-- 1. Insert Fiscal Year 2568 (if it doesn't exist)
-- Note: checks name to prevent duplicates
INSERT IGNORE INTO fiscal_years (name, start_date, end_date, is_active) 
VALUES ('2568', '2024-10-01', '2025-09-30', TRUE);

-- 2. Ensure only 2568 is active (optional safety)
UPDATE fiscal_years SET is_active = FALSE WHERE name != '2568';

-- 3. Update ALL existing projects to use Fiscal Year 2568
UPDATE projects 
SET fiscal_year_id = (SELECT id FROM fiscal_years WHERE name = '2568');
