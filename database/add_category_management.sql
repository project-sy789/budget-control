-- Add category_types table for dynamic budget categories
CREATE TABLE IF NOT EXISTS category_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_key VARCHAR(50) UNIQUE NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_category_active ON category_types(is_active);
CREATE INDEX IF NOT EXISTS idx_category_key ON category_types(category_key);

-- Insert default categories
INSERT INTO category_types (category_key, category_name, description, is_active) VALUES
('BOOKS', 'ค่าหนังสือและเอกสาร', 'ค่าใช้จ่ายสำหรับหนังสือ เอกสาร และสื่อการเรียนการสอน', 1),
('EQUIPMENT', 'ค่าครุภัณฑ์และอุปกรณ์', 'ค่าใช้จ่ายสำหรับครุภัณฑ์ อุปกรณ์ และเครื่องมือต่างๆ', 1),
('DEVELOPMENT', 'ค่าพัฒนาและก่อสร้าง', 'ค่าใช้จ่ายสำหรับการพัฒนาและก่อสร้างโครงการ', 1),
('LUNCH', 'ค่าอาหารและเครื่องดื่ม', 'ค่าใช้จ่ายสำหรับอาหาร เครื่องดื่ม และการเลี้ยงรับรอง', 1),
('SUBSIDY', 'ค่าใช้จ่ายอื่นๆ', 'ค่าใช้จ่ายเบ็ดเตล็ดและค่าใช้จ่ายอื่นๆ', 1),
('UNIFORM', 'ค่าเครื่องแบบและเครื่องแต่งกาย', 'ค่าใช้จ่ายสำหรับเครื่องแบบ เครื่องแต่งกาย และอุปกรณ์แต่งตัว', 1),
('TRAINING', 'ค่าฝึกอบรมและสัมมนา', 'ค่าใช้จ่ายสำหรับการฝึกอบรม สัมมนา และพัฒนาบุคลากร', 1),
('MATERIAL', 'ค่าวัสดุสำนักงาน', 'ค่าใช้จ่ายสำหรับวัสดุสำนักงานและอุปกรณ์การทำงาน', 1),
('TRANSPORT', 'ค่าเดินทางและขนส่ง', 'ค่าใช้จ่ายสำหรับการเดินทาง ขนส่ง และค่าน้ำมันเชื้อเพลิง', 1),
('UTILITY', 'ค่าสาธารณูปโภค', 'ค่าใช้จ่ายสำหรับไฟฟ้า น้ำประปา โทรศัพท์ และอินเทอร์เน็ต', 1);