# Budget Control System v2

ระบบควบคุมการเบิกจ่ายโครงการ เวอร์ชัน 2 ที่พัฒนาด้วย PHP, MySQL และ Bootstrap 5

## คุณสมบัติหลัก

- 🔐 ระบบล็อกอินด้วย Username/Password พร้อม Hash Security
- 📊 จัดการโครงการและงบประมาณ
- 💰 บันทึกรายรับ-รายจ่าย
- 🔄 โอนงบประมาณระหว่างโครงการ
- 📈 รายงานและสถิติ
- 👥 จัดการผู้ใช้ (สำหรับ Admin)
- 📱 Responsive Design ด้วย Bootstrap 5
- 📤 ส่งออกรายงานเป็น Excel/CSV

## ความต้องการของระบบ

- PHP 7.4 หรือสูงกว่า
- MySQL 5.7 หรือสูงกว่า
- Web Server (Apache/Nginx)
- Composer (สำหรับจัดการ dependencies)

## การติดตั้ง

### 1. Clone หรือ Download โปรเจค

```bash
git clone <repository-url>
cd Budget-control-v2
```

### 2. ติดตั้ง Dependencies

```bash
composer install
```

### 3. ตั้งค่าฐานข้อมูล

#### สร้างฐานข้อมูล
```sql
CREATE DATABASE budget_control_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### Import Schema
```bash
mysql -u root -p budget_control_v2 < database/schema_mysql.sql
```

### 4. ตั้งค่า Environment

```bash
cp .env.example .env
```

แก้ไขไฟล์ `.env` ให้ตรงกับการตั้งค่าของคุณ:

```env
DB_HOST=localhost
DB_NAME=budget_control_v2
DB_USER=your_username
DB_PASS=your_password
```

### 5. ตั้งค่า Web Server

#### Apache
สร้างไฟล์ `.htaccess` ในโฟลเดอร์ `public`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/Budget-control-v2/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. ตั้งค่าสิทธิ์ไฟล์

```bash
chmod -R 755 public/
chmod -R 777 uploads/ (ถ้ามี)
```

## การใช้งาน

### ผู้ใช้เริ่มต้น

ระบบจะสร้างผู้ใช้เริ่มต้นให้อัตโนมัติ:

**Admin:**
- Username: `admin`
- Password: `admin123`
- Role: Administrator

**User:**
- Username: `user`
- Password: `user123`
- Role: User

⚠️ **สำคัญ:** เปลี่ยนรหัสผ่านเริ่มต้นทันทีหลังจากติดตั้ง

### การเข้าใช้งาน

1. เปิดเว็บเบราว์เซอร์และไปที่ URL ของโปรเจค
2. ล็อกอินด้วย Username และ Password
3. เริ่มใช้งานระบบ

## โครงสร้างโปรเจค

```
Budget-control-v2/
├── config/
│   └── database.php          # การตั้งค่าฐานข้อมูล
├── database/
│   └── schema_mysql.sql     # โครงสร้างฐานข้อมูล MySQL
├── public/
│   ├── index.php           # หน้าหลัก
│   ├── login.php           # หน้าล็อกอิน
│   ├── export.php          # ส่งออกรายงาน
│   └── pages/              # หน้าต่างๆ ของระบบ
│       ├── dashboard.php
│       ├── projects.php
│       ├── budget-control.php
│       ├── budget-summary.php
│       ├── budget-transfer.php
│       └── user-management.php
├── src/
│   ├── Auth/               # ระบบยืนยันตัวตน
│   │   ├── AuthService.php
│   │   └── SessionManager.php
│   └── Services/           # บริการต่างๆ
│       ├── ProjectService.php
│       └── TransactionService.php
├── composer.json
├── .env.example
└── README.md
```

## คุณสมบัติหลัก

### 1. จัดการโครงการ
- สร้าง แก้ไข ลบโครงการ
- กำหนดงบประมาณและหมวดหมู่
- ติดตามสถานะโครงการ

### 2. บันทึกรายรับ-รายจ่าย
- บันทึกธุรกรรมทางการเงิน
- จัดหมวดหมู่รายการ
- แนบไฟล์เอกสาร

### 3. โอนงบประมาณ
- โอนงบประมาณระหว่างโครงการ
- ตรวจสอบยอดคงเหลือ
- บันทึกประวัติการโอน

### 4. รายงานและสถิติ
- สรุปงบประมาณตามโครงการ
- รายงานตามกลุ่มงาน
- กราฟและแผนภูมิ
- ส่งออกเป็น Excel/CSV

### 5. จัดการผู้ใช้ (Admin)
- เพิ่ม แก้ไข ผู้ใช้
- กำหนดสิทธิ์การใช้งาน
- รีเซ็ตรหัสผ่าน

## การพัฒนาต่อ

### เพิ่มฟีเจอร์ใหม่

1. สร้างไฟล์ Service ใหม่ใน `src/Services/`
2. สร้างหน้า UI ใน `public/pages/`
3. เพิ่ม Route ใน `public/index.php`
4. อัปเดตฐานข้อมูลถ้าจำเป็น

### การปรับแต่ง UI

- แก้ไข CSS ใน `public/index.php`
- ใช้ Bootstrap 5 Classes
- เพิ่ม JavaScript สำหรับ Interactive Features

## การแก้ไขปัญหา

### ปัญหาการเชื่อมต่อฐานข้อมูล

1. ตรวจสอบการตั้งค่าใน `.env`
2. ตรวจสอบว่าฐานข้อมูลทำงานอยู่
3. ตรวจสอบสิทธิ์การเข้าถึง

### ปัญหา Session

1. ตรวจสอบการตั้งค่า PHP Session
2. ตรวจสอบสิทธิ์การเขียนไฟล์
3. ล้าง Browser Cache

### ปัญหาการแสดงผล

1. ตรวจสอบ Console ใน Browser
2. ตรวจสอบ PHP Error Log
3. ตรวจสอบการโหลด CSS/JS

## การสำรองข้อมูล

### สำรองฐานข้อมูล

```bash
mysqldump -u username -p budget_control_v2 > backup_$(date +%Y%m%d).sql
```

### กู้คืนฐานข้อมูล

```bash
mysql -u username -p budget_control_v2 < backup_file.sql
```

## การอัปเดต

1. สำรองข้อมูลก่อนอัปเดต
2. ดาวน์โหลดเวอร์ชันใหม่
3. อัปเดต Dependencies
4. รันการ Migration (ถ้ามี)
5. ทดสอบระบบ

## การรักษาความปลอดภัย

- เปลี่ยนรหัสผ่านเริ่มต้น
- ใช้ HTTPS ในการใช้งานจริง
- อัปเดต PHP และ MySQL เป็นประจำ
- สำรองข้อมูลเป็นประจำ
- ตรวจสอบ Log เป็นประจำ

## การสนับสนุน

หากพบปัญหาหรือต้องการความช่วยเหลือ:

1. ตรวจสอบ Documentation นี้
2. ตรวจสอบ Issues ใน Repository
3. สร้าง Issue ใหม่พร้อมรายละเอียดปัญหา

## License

โปรเจคนี้เป็น Open Source ภายใต้ MIT License

---

**หมายเหตุ:** ระบบนี้พัฒนาขึ้นเพื่อใช้ในการจัดการงบประมาณโครงการ กรุณาทดสอบอย่างละเอียดก่อนนำไปใช้งานจริง