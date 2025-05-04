# ระบบควบคุมงบประมาณโครงการ (Project Budget Control System)

ระบบควบคุมงบประมาณโครงการ เป็นแอปพลิเคชันสำหรับบริหารจัดการงบประมาณของโครงการต่างๆ ภายในองค์กร โดยมีฟังก์ชันการทำงานที่ครอบคลุมการบริหารจัดการงบประมาณอย่างครบถ้วน

## ฟังก์ชันการทำงานหลัก

### 1. การจัดการโครงการ (Project Management)
- สร้างโครงการใหม่
- กำหนดรายละเอียดโครงการ:
  - ชื่อโครงการ
  - ผู้รับผิดชอบ
  - งบประมาณรวม
  - หมวดงบประมาณ (เงินอุดหนุนรายหัว, เงินพัฒนาผู้เรียน, ฯลฯ)
  - กลุ่มงาน (วิชาการ, งบประมาณ, บุคลากร, บริหารทั่วไป, อื่นๆ)
  - วันที่เริ่มต้นและสิ้นสุดโครงการ
  - สถานะโครงการ (กำลังดำเนินการ, เสร็จสิ้น)
  - รายละเอียดโครงการ
- แก้ไขข้อมูลโครงการ
- ดูรายละเอียดโครงการ
- นำเข้าโครงการจากไฟล์ Excel
- ส่งออกข้อมูลโครงการเป็นไฟล์ Excel

### 2. การบริหารงบประมาณ (Budget Management)
- บันทึกรายการใช้จ่ายงบประมาณ
- แสดงยอดงบประมาณคงเหลือแยกตามหมวด
- คำนวณงบประมาณอัตโนมัติ:
  - งบประมาณทั้งหมด
  - ยอดใช้จ่าย
  - งบประมาณคงเหลือ
  - เปอร์เซ็นต์การใช้งบประมาณ
- โอนงบประมาณระหว่างโครงการ:
  - โอนระหว่างหมวดงบประมาณเดียวกัน
  - โอนระหว่างหมวดงบประมาณต่างกัน
  - บันทึกประวัติการโอนอัตโนมัติ
  - แสดงการแจ้งเตือนเมื่อโอนต่างหมวด

### 3. การแสดงผลข้อมูล (Data Display)
- แสดงภาพรวมงบประมาณทั้งหมด
- แสดงรายละเอียดการใช้จ่ายแยกตามโครงการ
- แสดงรายละเอียดแยกตามหมวดงบประมาณ
- แสดงประวัติการทำรายการ:
  - รายการใช้จ่าย
  - การโอนงบประมาณ
- แสดงสถานะโครงการ
- Progress bar แสดงความคืบหน้าการใช้งบประมาณ

### 4. การกรองและค้นหาข้อมูล (Filtering)
- กรองตามกลุ่มงาน
- กรองตามสถานะโครงการ
- กรองตามหมวดงบประมาณ
- ค้นหาโครงการ
- กรองตามช่วงเวลา

### 5. การจัดการผู้ใช้ (User Management)
- เข้าสู่ระบบด้วยบัญชี Google (@react-oauth/google)
- กำหนดสิทธิ์ผู้ใช้ (Admin, User)
- อนุมัติผู้ใช้ใหม่
- จัดการผู้ใช้ (ดูรายชื่อ, เปลี่ยนบทบาท, ลบ)
- กำหนด Admin เริ่มต้น (nutrawee@subyaischool.ac.th)

### 6. การตรวจสอบความถูกต้อง (Validation)
- ตรวจสอบยอดงบประมาณคงเหลือก่อนทำรายการ
- ตรวจสอบความถูกต้องของข้อมูลที่กรอก
- แจ้งเตือนเมื่อข้อมูลไม่ถูกต้อง
- ป้องกันการทำรายการที่เกินงบประมาณ

## การติดตั้งและใช้งาน (สำหรับ Development)

### ข้อกำหนดเบื้องต้น
- ติดตั้ง Node.js (เวอร์ชัน 16.x ขึ้นไปแนะนำ)
- Git

### ขั้นตอนการติดตั้ง
1.  **Clone Repository:**
    ```bash
    git clone https://github.com/project-sy789/budget-control.git
    cd budget-control
    ```
2.  **ติดตั้ง Dependencies:**
    ```bash
    npm install
    ```
3.  **ตั้งค่า Environment Variables:**
    - สร้างไฟล์ `.env` ใน root directory ของโปรเจกต์ โดยคัดลอกจาก `.env.example`
    - แก้ไขไฟล์ `.env` และใส่ `GOOGLE_CLIENT_ID` ของคุณ:
      ```
      REACT_APP_GOOGLE_CLIENT_ID=YOUR_GOOGLE_CLIENT_ID_HERE
      ```
    *หมายเหตุ: `server.js` จะใช้ค่านี้ผ่าน `process.env.GOOGLE_CLIENT_ID` หากไม่ได้ตั้งค่าใน environment ของ server โดยตรง*

### การรันแอปพลิเคชัน (Development Mode)
1.  **รัน Backend Server (Express):**
    เปิด Terminal แรก และรัน:
    ```bash
    node server.js
    ```
    เซิร์ฟเวอร์จะทำงานที่ `http://localhost:3001` (หรือ port ที่กำหนดโดย `PORT` environment variable)

2.  **รัน Frontend (React App):**
    เปิด Terminal ที่สอง และรัน:
    ```bash
    npm start
    ```
    แอปพลิเคชันจะเปิดในเบราว์เซอร์ที่ `http://localhost:3000`

## เทคโนโลยีที่ใช้

- **Frontend:**
  - React
  - TypeScript
  - Material-UI (MUI)
  - React Router
  - React Context API
  - @react-oauth/google (สำหรับ Google Login)
  - Date-fns
  - xlsx (สำหรับ Import/Export Excel)
- **Backend:**
  - Node.js
  - Express
  - cors
  - google-auth-library (สำหรับ Verify Google Token)
  - uuid (สำหรับสร้าง ID)
- **Development & Build:**
  - npm
  - Create React App (react-scripts)

## โครงสร้างโปรเจค
```
budget-control/
├── README.md
├── package.json
├── package-lock.json
├── tsconfig.json
├── .gitignore
├── .env.example        # ตัวอย่าง Environment Variables
├── server.js           # Backend Express Server
├── public/             # Static files (HTML, favicon, etc.)
├── src/                # Frontend React code
│   ├── components/     # React components
│   ├── contexts/       # React Context providers (Auth, Budget)
│   ├── services/       # Service functions (AuthService.js)
│   ├── types/          # TypeScript Interfaces
│   ├── utils/          # Utility Functions
│   ├── App.tsx         # Main application component
│   └── index.tsx       # Application entry point (เดิมคือ main.tsx)
├── build/              # Frontend build output (หลังรัน npm run build)
├── templates/          # Excel templates
│   └── project_export_template.xlsx
├── scripts/            # Utility scripts
│   └── create_excel_template.py
├── render.yaml         # Deployment config for Render.com (ตัวอย่าง)
└── Procfile            # Deployment config for Railway/Heroku (ตัวอย่าง)
```

## การ Deploy

แอปพลิเคชันนี้ถูกออกแบบมาเพื่อ deploy เป็น Node.js application บนแพลตฟอร์ม เช่น Render.com หรือ Railway.app

### ขั้นตอนทั่วไป:
1.  **Push โค้ดไปยัง Git Repository** (เช่น GitHub, GitLab)
2.  **สร้าง Service/App ใหม่บนแพลตฟอร์ม:**
    - เชื่อมต่อกับ Git repository ของคุณ
3.  **ตั้งค่า Build & Start Commands:**
    - **Build Command:** `npm install && npm run build`
    - **Start Command:** `npm run start:prod` (ซึ่งจะรัน `node server.js`)
4.  **ตั้งค่า Environment Variables:**
    - `GOOGLE_CLIENT_ID`: ใส่ Google Client ID ของคุณ
    - `NODE_ENV`: ตั้งค่าเป็น `production` (แพลตฟอร์มส่วนใหญ่มักตั้งค่าให้อัตโนมัติ)
    - (Optional) `PORT`: แพลตฟอร์มส่วนใหญ่จะกำหนด port ให้อัตโนมัติ
5.  **(สำคัญ) ตั้งค่าฐานข้อมูล:**
    - `server.js` ปัจจุบันใช้ **in-memory storage** ซึ่งข้อมูลจะหายไปเมื่อเซิร์ฟเวอร์รีสตาร์ท
    - สำหรับ Production **ต้อง** เปลี่ยนไปใช้ฐานข้อมูลแบบถาวร เช่น PostgreSQL, MongoDB ที่ให้บริการโดยแพลตฟอร์ม หรือเชื่อมต่อกับฐานข้อมูลภายนอก
    - แก้ไข `server.js` ในส่วน API routes (Projects, Transactions, Users) ให้บันทึกและอ่านข้อมูลจากฐานข้อมูลที่คุณเลือก

### ตัวอย่างไฟล์ Config:
- `render.yaml`: สำหรับ Render.com (ต้องปรับแก้ตามต้องการ)
- `Procfile`: สำหรับ Railway.app / Heroku (ระบุ `web: npm run start:prod`)

## การนำเข้าข้อมูลจาก Excel

(ส่วนนี้ยังคงเหมือนเดิม - ตรวจสอบความถูกต้องอีกครั้งหากมีการเปลี่ยนแปลง API)

### 1. การเตรียมไฟล์ Excel
...

### 2. การกรอกข้อมูลโครงการ
...

### 3. การกรอกข้อมูลรายการธุรกรรม
...

### 4. การตรวจจับรายการซ้ำ
...

### 5. ขั้นตอนการนำเข้าข้อมูล
...

## การแก้ไขปัญหาเบื้องต้น

- **Frontend ไม่สามารถเชื่อมต่อ Backend:**
  - ตรวจสอบว่า Backend Server (`server.js`) ทำงานอยู่
  - ตรวจสอบ URL ของ API ที่ Frontend เรียกใช้ (ควรเป็น relative path หรือ URL ที่ถูกต้องของ deployed backend)
  - ตรวจสอบ CORS configuration ใน `server.js`
- **Google Login ไม่ทำงาน:**
  - ตรวจสอบว่า `GOOGLE_CLIENT_ID` ถูกตั้งค่าถูกต้องทั้งใน `.env` (สำหรับ local dev) และ Environment Variables บนแพลตฟอร์มที่ deploy
  - ตรวจสอบการตั้งค่า OAuth Consent Screen และ Credentials ใน Google Cloud Console ว่าถูกต้อง (เช่น Authorized JavaScript origins, Authorized redirect URIs)
- **ข้อมูลหายหลัง Deploy:**
  - เกิดขึ้นหากยังใช้ in-memory storage ใน `server.js` ต้องเปลี่ยนไปใช้ฐานข้อมูลแบบถาวร

## การอัพเดทในอนาคต (ข้อเสนอแนะ)
- [ ] **Implement Persistent Database:** เปลี่ยนจาก in-memory storage เป็นฐานข้อมูลจริง (เช่น PostgreSQL, MongoDB) สำหรับ Production
- [ ] เพิ่มระบบ Export รายงาน
- [ ] เพิ่มการแสดงกราฟวิเคราะห์งบประมาณ
- [ ] ปรับปรุง UI/UX เพิ่มเติม
- [ ] เพิ่ม Unit/Integration Tests

