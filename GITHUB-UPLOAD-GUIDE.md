# คู่มือการอัปโหลดโปรเจคไปยัง GitHub

## ไฟล์ที่ควรอัปโหลดไปยัง GitHub

สำหรับโปรเจค "ระบบควบคุมงบประมาณโครงการ" (เวอร์ชัน Express Backend) นี้ คุณควรอัปโหลดไฟล์ต่อไปนี้ไปยัง GitHub:

### ไฟล์หลักของโปรเจค

- `README.md` - เอกสารอธิบายโปรเจค (อัปเดตแล้ว)
- `GITHUB-UPLOAD-GUIDE.md` - คู่มือนี้
- `package.json` - รายการ dependencies และสคริปต์ (สำหรับ Frontend และ Backend รวมกัน)
- `package-lock.json` - เวอร์ชันที่แน่นอนของ dependencies
- `tsconfig.json` - การตั้งค่า TypeScript สำหรับ Frontend
- `server.js` - ไฟล์ Backend Server หลัก (Express)

### ไฟล์การตั้งค่าสำหรับการ Deploy

- `.gitignore` - กำหนดไฟล์ที่ไม่ต้องการอัปโหลดไปยัง GitHub (ควรตรวจสอบว่าครอบคลุม `node_modules`, `build`, `.env`)
- `.env.example` - ตัวอย่างไฟล์ตั้งค่า Environment Variables (ไม่ใช่ไฟล์ .env จริง)
- `render.yaml` - (ตัวอย่าง) การตั้งค่าสำหรับ Render.com
- `Procfile` - (ตัวอย่าง) การตั้งค่าสำหรับ Heroku หรือ Railway

### โฟลเดอร์โค้ดหลัก

- `src/` - โค้ดหลักของแอปพลิเคชัน React (Frontend)
  - `components/`
  - `contexts/`
  - `services/`
  - `utils/`
  - `types/`
  - ไฟล์ .tsx, .ts, .css อื่นๆ

- `public/` - ไฟล์สถิตที่เข้าถึงได้โดยตรง (index.html, favicon, etc.)

- `scripts/` - สคริปต์ยูทิลิตี้ (ถ้ามี)

- `templates/` - เทมเพลต Excel สำหรับ Import/Export

## ไฟล์ที่ไม่ควรอัปโหลดไปยัง GitHub

ไฟล์ต่อไปนี้ **ไม่ควร** อัปโหลดไปยัง GitHub:

- `/node_modules/` - โฟลเดอร์ dependencies (ขนาดใหญ่มากและควรติดตั้งใหม่เมื่อ deploy)
- `/build/` - โฟลเดอร์ที่สร้างขึ้นจากการ build Frontend (`npm run build`)
- `.env` - ไฟล์ตั้งค่า Environment Variables จริงที่มีข้อมูลละเอียดอ่อน (เช่น Google Client ID)
- `.DS_Store` - ไฟล์ระบบของ macOS
- ไฟล์ log ต่างๆ - `npm-debug.log*`, `yarn-debug.log*`, `yarn-error.log*`
- ไฟล์ Firebase ที่อาจหลงเหลือ (ถ้ามี) - `.firebase/`, `.firebaserc`, `firebase.json`, `functions/` (ควรถูกลบไปแล้ว)
- ไฟล์ Config ของ Cloud Platform อื่นๆ ที่ไม่ได้ใช้ - เช่น `app.yaml`, `.gcloudignore` (ควรถูกลบไปแล้ว)

## ขั้นตอนการอัปโหลดไปยัง GitHub (สำหรับโปรเจคที่มี Git อยู่แล้ว)

1.  **ตรวจสอบสถานะไฟล์:**
    ```bash
    git status
    ```
    (ดูว่าไฟล์ที่แก้ไข/ลบ/เพิ่มใหม่ ถูกต้องหรือไม่)

2.  **เพิ่มการเปลี่ยนแปลงทั้งหมด:**
    ```bash
    git add .
    ```
    (ไฟล์ที่ระบุใน `.gitignore` จะถูกข้าม)

3.  **Commit การเปลี่ยนแปลง:**
    ```bash
    git commit -m "Update project: Remove Firebase, add Express backend, fix deployment error, update docs"
    ```
    (ใส่ข้อความ commit ที่สื่อความหมาย)

4.  **อัปโหลดไปยัง GitHub:**
    ```bash
    git push origin main
    ```
    (หรือ branch อื่นที่คุณใช้งาน เช่น `master`)

## ขั้นตอนการอัปโหลดไปยัง GitHub (สำหรับโปรเจคที่ยังไม่มี Git หรือต้องการสร้าง Repo ใหม่)

1.  **สร้าง Repository ใหม่บน GitHub** (ตามขั้นตอนในเวอร์ชันก่อนหน้า)
2.  **เตรียม Git ในโปรเจคของคุณ:**
    - เปิด Terminal ในโฟลเดอร์โปรเจค
    - เริ่มต้น Git repository (ถ้ายังไม่มี):
      ```bash
      git init
      ```
3.  **เพิ่มไฟล์ทั้งหมดที่ต้องการอัปโหลด:**
    ```bash
    git add .
    ```
4.  **Commit การเปลี่ยนแปลง:**
    ```bash
    git commit -m "Initial commit of Express version"
    ```
5.  **เชื่อมต่อกับ GitHub Repository:**
    ```bash
    git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPOSITORY_NAME.git
    ```
6.  **อัปโหลดไปยัง GitHub:**
    ```bash
    git push -u origin main
    ```

## หมายเหตุสำคัญ

- ตรวจสอบให้แน่ใจว่าไฟล์ `.gitignore` มีการตั้งค่าที่ถูกต้อง **ก่อน** ที่จะ commit และ push
- **ห้าม** อัปโหลดไฟล์ `.env` ที่มีข้อมูลละเอียดอ่อนเด็ดขาด ให้ใช้ Environment Variables บนแพลตฟอร์มที่ deploy แทน
- สำหรับการ deploy บน Render.com หรือ Railway.app คุณสามารถเชื่อมต่อกับ GitHub repository ของคุณโดยตรงเพื่อให้ระบบ deploy อัตโนมัติเมื่อมีการ push ไปยัง branch หลัก

