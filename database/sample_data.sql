-- Sample Data for Budget Control System
-- This file contains realistic sample data for testing and demonstration

USE budget_control;

-- Insert additional users
INSERT INTO users (username, email, password_hash, display_name, role, approved, department, position) VALUES
('teacher1', 'teacher1@school.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'อาจารย์สมชาย ใจดี', 'user', TRUE, 'กลุ่มงานบริหารวิชาการ', 'ครูผู้สอน'),
('budget1', 'budget1@school.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางสาวสุดา จัดการ', 'user', TRUE, 'กลุ่มงานงบประมาณ', 'เจ้าหน้าที่งบประมาณ'),
('hr1', 'hr1@school.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นายประชา บริหาร', 'user', TRUE, 'กลุ่มงานบุคลากร', 'หัวหน้ากลุ่มงานบุคลากร'),
('general1', 'general1@school.ac.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'นางวิไล ทั่วไป', 'user', TRUE, 'กลุ่มงานทั่วไป', 'เจ้าหน้าที่ธุรการ');

-- Insert sample projects
INSERT INTO projects (name, budget, work_group, responsible_person, description, start_date, end_date, status, created_by) VALUES
('โครงการพัฒนาหลักสูตรวิทยาศาสตร์', 500000.00, 'academic', 'อาจารย์สมชาย ใจดี', 'โครงการพัฒนาหลักสูตรวิทยาศาสตร์ให้ทันสมัยและสอดคล้องกับความต้องการของตลาดแรงงาน', '2024-01-01', '2024-12-31', 'active', 2),
('โครงการจัดซื้อครุภัณฑ์คอมพิวเตอร์', 800000.00, 'budget', 'นางสาวสุดา จัดการ', 'โครงการจัดซื้อครุภัณฑ์คอมพิวเตอร์สำหรับห้องเรียนและห้องปฏิบัติการ', '2024-02-01', '2024-11-30', 'active', 3),
('โครงการอบรมพัฒนาบุคลากร', 300000.00, 'hr', 'นายประชา บริหาร', 'โครงการอบรมเพื่อพัฒนาศักยภาพของบุคลากรในสถานศึกษา', '2024-03-01', '2024-10-31', 'active', 4),
('โครงการปรับปรุงภูมิทัศน์โรงเรียน', 1200000.00, 'general', 'นางวิไล ทั่วไป', 'โครงการปรับปรุงและพัฒนาภูมิทัศน์ภายในโรงเรียนให้สวยงามและเป็นระเบียบ', '2024-01-15', '2024-08-31', 'active', 5),
('โครงการค่ายวิทยาศาสตร์เยาวชน', 150000.00, 'academic', 'อาจารย์สมชาย ใจดี', 'โครงการจัดค่ายวิทยาศาสตร์เพื่อส่งเสริมความสนใจในวิทยาศาสตร์ของนักเรียน', '2024-06-01', '2024-07-31', 'completed', 2),
('โครงการจัดหาเครื่องแบบนักเรียน', 400000.00, 'general', 'นางวิไล ทั่วไป', 'โครงการจัดหาเครื่องแบบนักเรียนสำหรับนักเรียนยากจน', '2024-04-01', '2024-05-31', 'completed', 5);

-- Insert budget categories for projects
INSERT INTO budget_categories (project_id, category, amount, description) VALUES
-- Project 1: โครงการพัฒนาหลักสูตรวิทยาศาสตร์
(1, 'DEVELOPMENT', 300000.00, 'ค่าพัฒนาหลักสูตรและสื่อการเรียนการสอน'),
(1, 'BOOKS', 150000.00, 'ค่าจัดซื้อหนังสือและเอกสารประกอบการเรียน'),
(1, 'EQUIPMENT', 50000.00, 'ค่าอุปกรณ์การเรียนการสอน'),

-- Project 2: โครงการจัดซื้อครุภัณฑ์คอมพิวเตอร์
(2, 'EQUIPMENT', 700000.00, 'ค่าจัดซื้อเครื่องคอมพิวเตอร์และอุปกรณ์'),
(2, 'DEVELOPMENT', 100000.00, 'ค่าติดตั้งและพัฒนาระบบ'),

-- Project 3: โครงการอบรมพัฒนาบุคลากร
(3, 'DEVELOPMENT', 200000.00, 'ค่าวิทยากรและค่าอบรม'),
(3, 'LUNCH', 80000.00, 'ค่าอาหารและเครื่องดื่มในการอบรม'),
(3, 'BOOKS', 20000.00, 'ค่าเอกสารประกอบการอบรม'),

-- Project 4: โครงการปรับปรุงภูมิทัศน์โรงเรียน
(4, 'DEVELOPMENT', 800000.00, 'ค่าก่อสร้างและปรับปรุง'),
(4, 'EQUIPMENT', 300000.00, 'ค่าอุปกรณ์และเครื่องมือ'),
(4, 'SUBSIDY', 100000.00, 'ค่าใช้จ่ายอื่นๆ'),

-- Project 5: โครงการค่ายวิทยาศาสตร์เยาวชน
(5, 'DEVELOPMENT', 80000.00, 'ค่าจัดกิจกรรมและวัสดุการเรียน'),
(5, 'LUNCH', 50000.00, 'ค่าอาหารและเครื่องดื่ม'),
(5, 'UNIFORM', 20000.00, 'ค่าเสื้อค่าย'),

-- Project 6: โครงการจัดหาเครื่องแบบนักเรียน
(6, 'UNIFORM', 350000.00, 'ค่าเครื่องแบบนักเรียน'),
(6, 'SUBSIDY', 50000.00, 'ค่าใช้จ่ายในการจัดหา');

-- Insert sample transactions
INSERT INTO transactions (project_id, date, description, amount, budget_category, note, created_by) VALUES
-- Project 1 transactions
(1, '2024-01-15', 'จ้างที่ปรึกษาพัฒนาหลักสูตร', -80000.00, 'DEVELOPMENT', 'จ้างผู้เชี่ยวชาญด้านหลักสูตรวิทยาศาสตร์', 2),
(1, '2024-02-01', 'ซื้อหนังสือเรียนวิทยาศาสตร์', -45000.00, 'BOOKS', 'หนังสือเรียนและเอกสารอ้างอิง 50 เล่ม', 2),
(1, '2024-02-15', 'ซื้ออุปกรณ์การทดลอง', -25000.00, 'EQUIPMENT', 'อุปกรณ์การทดลองเคมีและฟิสิกส์', 2),
(1, '2024-03-01', 'ค่าพัฒนาสื่อการเรียนการสอน', -120000.00, 'DEVELOPMENT', 'พัฒนาสื่อดิจิทัลและวิดีโอการเรียน', 2),

-- Project 2 transactions
(2, '2024-02-10', 'ซื้อเครื่องคอมพิวเตอร์ 20 เครื่อง', -400000.00, 'EQUIPMENT', 'คอมพิวเตอร์สำหรับห้องเรียน', 3),
(2, '2024-03-01', 'ซื้อโปรเจคเตอร์ 5 เครื่อง', -150000.00, 'EQUIPMENT', 'โปรเจคเตอร์สำหรับห้องเรียน', 3),
(2, '2024-03-15', 'ค่าติดตั้งระบบเครือข่าย', -60000.00, 'DEVELOPMENT', 'ติดตั้งและตั้งค่าระบบเครือข่าย', 3),

-- Project 3 transactions
(3, '2024-03-10', 'ค่าวิทยากรการอบรม', -80000.00, 'DEVELOPMENT', 'วิทยากรภายนอก 2 ท่าน', 4),
(3, '2024-03-15', 'ค่าอาหารกลางวันการอบรม', -25000.00, 'LUNCH', 'อาหารกลางวัน 3 วัน', 4),
(3, '2024-04-01', 'ค่าเอกสารประกอบการอบรม', -15000.00, 'BOOKS', 'เอกสารและหนังสือประกอบการอบรม', 4),

-- Project 4 transactions
(4, '2024-02-01', 'ค่าจ้างผู้รับเหมาปรับปรุงสวน', -300000.00, 'DEVELOPMENT', 'ปรับปรุงสวนหน้าโรงเรียน', 5),
(4, '2024-02-20', 'ซื้อต้นไม้และดอกไม้', -80000.00, 'EQUIPMENT', 'ต้นไม้ประดับและดอกไม้นานาชนิด', 5),
(4, '2024-03-10', 'ค่าระบบน้ำพุ', -150000.00, 'DEVELOPMENT', 'ติดตั้งระบบน้ำพุกลางสวน', 5),

-- Project 5 transactions (completed)
(5, '2024-06-05', 'ค่าวัสดุการทดลอง', -30000.00, 'DEVELOPMENT', 'วัสดุสำหรับกิจกรรมการทดลอง', 2),
(5, '2024-06-10', 'ค่าอาหารค่าย', -40000.00, 'LUNCH', 'อาหาร 3 มื้อ 3 วัน', 2),
(5, '2024-06-01', 'ค่าเสื้อค่าย', -18000.00, 'UNIFORM', 'เสื้อค่ายสำหรับผู้เข้าร่วม 60 คน', 2),

-- Project 6 transactions (completed)
(6, '2024-04-10', 'ซื้อเครื่องแบบนักเรียนชาย', -180000.00, 'UNIFORM', 'เครื่องแบบนักเรียนชาย 100 ชุด', 5),
(6, '2024-04-15', 'ซื้อเครื่องแบบนักเรียนหญิง', -150000.00, 'UNIFORM', 'เครื่องแบบนักเรียนหญิง 80 ชุด', 5),
(6, '2024-04-20', 'ค่าขนส่งและจัดส่ง', -20000.00, 'SUBSIDY', 'ค่าขนส่งเครื่องแบบไปยังนักเรียน', 5);

-- Insert some budget transfer transactions
INSERT INTO transactions (project_id, date, description, amount, budget_category, note, is_transfer, transfer_to_project_id, transfer_to_category, created_by) VALUES
(1, '2024-04-01', 'โอนงบประมาณไปโครงการอบรมบุคลากร', -50000.00, 'DEVELOPMENT', 'โอนงบเพื่อสนับสนุนการอบรม', TRUE, 3, 'DEVELOPMENT', 1);

INSERT INTO transactions (project_id, date, description, amount, budget_category, note, is_transfer, is_transfer_in, transfer_from_project_id, transfer_from_category, created_by) VALUES
(3, '2024-04-01', 'รับโอนงบประมาณจากโครงการพัฒนาหลักสูตร', 50000.00, 'DEVELOPMENT', 'รับโอนงบเพื่อการอบรม', TRUE, TRUE, 1, 'DEVELOPMENT', 1);

-- Insert some income transactions
INSERT INTO transactions (project_id, date, description, amount, budget_category, note, created_by) VALUES
(2, '2024-01-20', 'รับงบประมาณเพิ่มเติมจากกรมส่งเสริม', 200000.00, 'INCOME', 'งบประมาณสนับสนุนจากหน่วยงานภายนอก', 3),
(4, '2024-02-10', 'เงินบริจาคจากศิษย์เก่า', 100000.00, 'INCOME', 'เงินบริจาคเพื่อปรับปรุงโรงเรียน', 5);

-- Update last_login for users
UPDATE users SET last_login = NOW() - INTERVAL FLOOR(RAND() * 30) DAY WHERE id > 1;

COMMIT;