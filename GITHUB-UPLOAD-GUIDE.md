# คู่มือการอัปโหลดโปรเจคไปยัง GitHub

## ไฟล์ที่ควรอัปโหลดไปยัง GitHub

สำหรับโปรเจค "ระบบควบคุมงบประมาณโครงการ" นี้ คุณควรอัปโหลดไฟล์ต่อไปนี้ไปยัง GitHub:

### ไฟล์หลักของโปรเจค

- `README.md` - เอกสารอธิบายโปรเจค
- `INSTALLATION.md` - คู่มือการติดตั้ง
- `MIGRATION-STEPS.md` - ขั้นตอนการย้ายจาก Firebase
- `package.json` - รายการ dependencies และสคริปต์
- `package-lock.json` - เวอร์ชันที่แน่นอนของ dependencies
- `tsconfig.json` - การตั้งค่า TypeScript
- `server.js` - ไฟล์เซิร์ฟเวอร์หลัก
- `server-package.json` - package.json สำหรับเซิร์ฟเวอร์

### ไฟล์การตั้งค่าสำหรับการ Deploy

- `.gitignore` - กำหนดไฟล์ที่ไม่ต้องการอัปโหลดไปยัง GitHub
- `.env.example` - ตัวอย่างไฟล์ตั้งค่าสภาพแวดล้อม (ไม่ใช่ไฟล์ .env จริง)
- `app.yaml` - การตั้งค่าสำหรับ Google Cloud
- `render.yaml` - การตั้งค่าสำหรับ Render.com
- `Procfile` - การตั้งค่าสำหรับ Heroku หรือ Railway
- `.gcloudignore` - กำหนดไฟล์ที่ไม่ต้องการอัปโหลดไปยัง Google Cloud

### โฟลเดอร์โค้ดหลัก

- `src/` - โค้ดหลักของแอปพลิเคชัน React
  - `components/` - คอมโพเนนต์ React
  - `contexts/` - React Context providers
  - `services/` - บริการต่างๆ
  - `utils/` - ฟังก์ชันยูทิลิตี้
  - `types/` - TypeScript interfaces
  - ไฟล์ .tsx, .ts, .css อื่นๆ

- `public/` - ไฟล์สถิตที่เข้าถึงได้โดยตรง
  - `index.html` - ไฟล์ HTML หลัก
  - `favicon.ico` - ไอคอนเว็บไซต์
  - `manifest.json` - ไฟล์ manifest สำหรับ PWA

- `scripts/` - สคริปต์ยูทิลิตี้
  - `create_excel_template.py`
  - `generate-favicon.js`
  - `generate-icons.js`
  - `simple-icons.js`

- `templates/` - เทมเพลตสำหรับการส่งออก
  - `project_export_template.xlsx`

## ไฟล์ที่ไม่ควรอัปโหลดไปยัง GitHub

ไฟล์ต่อไปนี้ไม่ควรอัปโหลดไปยัง GitHub เนื่องจากอาจมีข้อมูลที่ละเอียดอ่อนหรือเป็นไฟล์ที่สร้างขึ้นในระหว่างการพัฒนา:

- `/node_modules/` - โฟลเดอร์ dependencies (ขนาดใหญ่มาก)
- `/build/` - โฟลเดอร์ที่สร้างขึ้นจากการ build
- `.env` - ไฟล์ตั้งค่าสภาพแวดล้อมที่มีข้อมูลละเอียดอ่อน
- `.DS_Store` - ไฟล์ระบบของ macOS
- ไฟล์ log ต่างๆ - `npm-debug.log*`, `yarn-debug.log*`, `yarn-error.log*`
- ไฟล์ Firebase ที่เหลืออยู่ - `.firebase/`, `.firebaserc`, `firebase.json`, `functions/`

## ขั้นตอนการอัปโหลดไปยัง GitHub

1. **สร้าง Repository ใหม่บน GitHub**
   - ไปที่ [GitHub](https://github.com)
   - คลิกที่ปุ่ม "New" เพื่อสร้าง repository ใหม่
   - ตั้งชื่อ repository เช่น "budget-control-system"
   - เลือกเป็น Public หรือ Private ตามต้องการ
   - คลิก "Create repository"

2. **เตรียม Git ในโปรเจคของคุณ**
   - เปิด Terminal หรือ Command Prompt
   - นำทางไปยังโฟลเดอร์โปรเจคของคุณ
   ```bash
   cd /Users/jamies/Library/CloudStorage/OneDrive-ส่วนบุคคล/Project\ Manage/budget-control
   ```
   - เริ่มต้น Git repository
   ```bash
   git init
   ```

3. **เพิ่มไฟล์ทั้งหมดที่ต้องการอัปโหลด**
   ```bash
   git add .
   ```
   - ไฟล์ที่ระบุใน .gitignore จะถูกข้ามโดยอัตโนมัติ

4. **Commit การเปลี่ยนแปลง**
   ```bash
   git commit -m "Initial commit"
   ```

5. **เชื่อมต่อกับ GitHub Repository**
   ```bash
   git remote add origin https://github.com/YOUR_USERNAME/budget-control-system.git
   ```
   - แทนที่ `YOUR_USERNAME` ด้วยชื่อผู้ใช้ GitHub ของคุณ

6. **อัปโหลดไปยัง GitHub**
   ```bash
   git push -u origin main
   ```
   - หากคุณใช้ branch ชื่อ "master" แทนที่ "main" ให้ใช้คำสั่ง:
   ```bash
   git push -u origin master
   ```

## หมายเหตุสำคัญ

- ตรวจสอบให้แน่ใจว่าไฟล์ `.gitignore` มีการตั้งค่าที่ถูกต้องก่อนที่จะ commit และ push
- ไม่ควรอัปโหลดไฟล์ที่มีข้อมูลละเอียดอ่อน เช่น API keys, รหัสผ่าน, หรือข้อมูลส่วนตัวอื่นๆ
- หากคุณต้องการเปลี่ยนแปลงไฟล์หลังจากที่ได้อัปโหลดไปแล้ว คุณสามารถใช้คำสั่ง `git add`, `git commit` และ `git push` อีกครั้ง
- สำหรับการ deploy บน Render.com หรือ Railway.app คุณสามารถเชื่อมต่อกับ GitHub repository ของคุณโดยตรงเพื่อให้ระบบ deploy อัตโนมัติเมื่อมีการ push ไปยัง branch หลัก