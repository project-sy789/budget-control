# แอปพลิเคชัน Budget Control (เวอร์ชันเขียนใหม่)

## ภาพรวม

โปรเจกต์นี้เป็นเวอร์ชันที่เขียนขึ้นใหม่ของแอปพลิเคชัน Budget Control ซึ่งเดิมสร้างด้วย React และ Express backend โดยใช้ฐานข้อมูลในหน่วยความจำ เวอร์ชันนี้ใช้ฐานข้อมูล PostgreSQL สำหรับการจัดเก็บข้อมูลถาวร, ใช้ Google OAuth สำหรับการยืนยันตัวตน และมีการปรับปรุงแก้ไขข้อผิดพลาดต่างๆ

**ฟีเจอร์หลัก:**

*   **การยืนยันตัวตนผู้ใช้:** ล็อกอินอย่างปลอดภัยผ่าน Google OAuth
*   **การจัดการผู้ใช้ (แอดมิน):** แอดมินสามารถอนุมัติผู้ใช้ใหม่, จัดการบทบาท (admin/user), และลบผู้ใช้ได้
*   **การจัดการโครงการ:** สร้าง, อ่าน, อัปเดต, ลบ (CRUD) โครงการ
*   **การจัดการธุรกรรม:** การดำเนินการ CRUD สำหรับธุรกรรมรายรับ/รายจ่ายที่เชื่อมโยงกับโครงการ พร้อมระบบแบ่งหน้า (pagination)
*   **สรุปงบประมาณ:** ดูสรุปงบประมาณโดยรวมและสรุปตามแต่ละโครงการ
*   **ส่งออกเป็น Excel:** ส่งออกธุรกรรมของโครงการที่เลือกไปยังไฟล์ Excel
*   **ฐานข้อมูล:** ใช้ PostgreSQL สำหรับการจัดเก็บข้อมูลถาวร
*   **การ Deployment:** กำหนดค่าสำหรับการ deploy บน Render.com (โดยใช้ `render.yaml`)
*   **คอมเมนต์ในโค้ด:** มีคอมเมนต์โดยละเอียดและคำอธิบายภาษาไทยสำหรับองค์ประกอบหลัก

## โครงสร้างโปรเจกต์

โปรเจกต์ถูกจัดระเบียบเป็นสองไดเรกทอรีหลัก:

*   `/client`: ประกอบด้วยแอปพลิเคชัน React frontend
*   `/server`: ประกอบด้วย Node.js/Express backend API

```
budget_control_rewrite/
├── client/             # React Frontend
│   ├── public/
│   ├── src/
│   │   ├── components/   # คอมโพเนนต์ UI (Login, Layout, ProjectMgmt, etc.)
│   │   ├── contexts/     # React Contexts (Auth, Budget)
│   │   ├── services/     # เซอร์วิสสำหรับติดต่อ API (AuthService)
│   │   ├── types/        # การกำหนดชนิดข้อมูล TypeScript
│   │   ├── App.tsx       # คอมโพเนนต์หลักและการกำหนดเส้นทาง (routing)
│   │   ├── index.tsx     # จุดเริ่มต้นของแอปพลิเคชัน
│   │   └── theme.ts      # การกำหนดค่า Theme ของ MUI
│   ├── .env.example    # ตัวอย่างตัวแปรสภาพแวดล้อมสำหรับ client
│   ├── package.json
│   └── ...             # ไฟล์กำหนดค่าอื่นๆ (tsconfig, etc.)
├── server/             # Node.js/Express Backend
│   ├── config/         # การกำหนดค่า (การเชื่อมต่อ db, jwt, port)
│   ├── controllers/    # ตัวจัดการเส้นทาง (ตรรกะสำหรับ requests)
│   ├── middleware/     # Express middleware (การยืนยันตัวตน, การจัดการข้อผิดพลาด)
│   ├── models/         # ตรรกะการโต้ตอบกับฐานข้อมูล (User, Project, Transaction)
│   ├── routes/         # การกำหนดเส้นทาง API
│   ├── services/       # เซอร์วิสตรรกะทางธุรกิจ (ถ้าจำเป็น)
│   ├── utils/          # ฟังก์ชันยูทิลิตี้
│   ├── .env.example    # ตัวอย่างตัวแปรสภาพแวดล้อมสำหรับ server
│   ├── package.json
│   ├── server.js       # จุดเริ่มต้นหลักของ backend
│   └── ...
├── render.yaml         # การกำหนดค่า Deployment สำหรับ Render.com
├── schema.sql          # สคริปต์สร้าง Schema ฐานข้อมูล PostgreSQL
└── README.md           # ไฟล์นี้ (เวอร์ชันภาษาอังกฤษ)
└── README_th.md        # ไฟล์นี้ (เวอร์ชันภาษาไทย)
```

## เทคโนโลยีที่ใช้

*   **Frontend:** React, TypeScript, Material UI (MUI), React Router, Axios (หรือ Fetch API), Google OAuth Library (`@react-oauth/google`), Date-fns, JWT Decode
*   **Backend:** Node.js, Express, PostgreSQL (ไลบรารี `pg`), JWT (`jsonwebtoken`), Google Auth Library (`google-auth-library`), CORS, Dotenv, XLSX (สำหรับส่งออก Excel)
*   **Database:** PostgreSQL
*   **Deployment:** Render.com (Static Site + Web Service + PostgreSQL)

## การติดตั้งและตั้งค่า

**สิ่งที่ต้องมี:**

*   Node.js (แนะนำเวอร์ชัน 18 หรือใหม่กว่า)
*   npm หรือ yarn
*   อินสแตนซ์ฐานข้อมูล PostgreSQL (บนเครื่องหรือบนคลาวด์)
*   โปรเจกต์ Google Cloud Platform ที่มีการกำหนดค่า OAuth 2.0 Client ID

**ขั้นตอน:**

1.  **Clone Repository:**
    ```bash
    # ไม่สามารถใช้ได้ในบริบทนี้ เนื่องจากโค้ดถูกส่งให้โดยตรง
    ```

2.  **ตั้งค่า Backend:**
    *   ไปที่ไดเรกทอรี `server`: `cd server`
    *   ติดตั้ง dependencies: `npm install`
    *   สร้างไฟล์ `.env` โดยคัดลอกจาก `.env.example`:
        ```bash
        cp .env.example .env
        ```
    *   **กำหนดค่าตัวแปรใน `.env` (ดูส่วน Environment Variables ด้านล่าง)** ที่สำคัญคือ `DATABASE_URL`, `GOOGLE_CLIENT_ID`, และ `JWT_SECRET`
    *   ใช้ schema ฐานข้อมูล: เชื่อมต่อกับฐานข้อมูล PostgreSQL ของคุณและรันคำสั่งใน `schema.sql`
        ```sql
        -- ตัวอย่างการใช้ psql
        -- psql -U your_db_user -d your_db_name -a -f ../schema.sql
        ```

3.  **ตั้งค่า Frontend:**
    *   ไปที่ไดเรกทอรี `client`: `cd ../client`
    *   ติดตั้ง dependencies: `npm install`
    *   สร้างไฟล์ `.env` โดยคัดลอกจาก `.env.example`:
        ```bash
        cp .env.example .env
        ```
    *   **กำหนดค่าตัวแปรใน `.env` (ดูส่วน Environment Variables ด้านล่าง)** ตั้งค่า `REACT_APP_GOOGLE_CLIENT_ID` และ `REACT_APP_API_URL` (สำหรับการพัฒนาบนเครื่อง)

4.  **การรันบนเครื่อง (Locally):**
    *   **เริ่ม Backend:** ในไดเรกทอรี `server` รัน: `npm run dev` (ใช้ nodemon สำหรับการรีสตาร์ทอัตโนมัติ) หรือ `npm start`
    *   **เริ่ม Frontend:** ในไดเรกทอรี `client` รัน: `npm start`
    *   เปิดเบราว์เซอร์ไปที่ `http://localhost:3000` (หรือพอร์ตที่ React ระบุ)

## Environment Variables (ตัวแปรสภาพแวดล้อม)

**Server (`/server/.env`):**

*   `NODE_ENV`: ตั้งค่าเป็น `development` หรือ `production`
*   `PORT`: พอร์ตสำหรับ backend server (เช่น `5000`)
*   `DATABASE_URL`: Connection string สำหรับฐานข้อมูล PostgreSQL ของคุณ
    *   รูปแบบ: `postgresql://DB_USER:DB_PASSWORD@DB_HOST:DB_PORT/DB_NAME`
*   `GOOGLE_CLIENT_ID`: Google OAuth Client ID ของคุณ (ได้จาก Google Cloud Console)
*   `JWT_SECRET`: สตริงลับที่แข็งแกร่งสำหรับลงนาม JWT tokens (สร้างขึ้นแบบสุ่ม)
*   `JWT_EXPIRES_IN`: เวลาหมดอายุของ JWT token (เช่น `7d`, `24h`)

**Client (`/client/.env`):**

*   `REACT_APP_GOOGLE_CLIENT_ID`: Google OAuth Client ID ของคุณ (เหมือนกับของ server)
*   `REACT_APP_API_URL`: URL พื้นฐานของ backend API ของคุณ
    *   สำหรับการพัฒนาบนเครื่อง: `http://localhost:5000/api` (ใช้พอร์ตที่ backend ของคุณทำงานอยู่)
    *   สำหรับ production (Render): ค่านี้จะถูกตั้งค่าโดยอัตโนมัติโดย Render ตาม `render.yaml`

## ฐานข้อมูล

*   Schema ฐานข้อมูลถูกกำหนดไว้ใน `schema.sql` รันสคริปต์นี้กับฐานข้อมูล PostgreSQL ของคุณเพื่อสร้างตารางที่จำเป็น (`users`, `projects`, `transactions`)
*   Backend เชื่อมต่อกับฐานข้อมูลโดยใช้ตัวแปรสภาพแวดล้อม `DATABASE_URL`

## การ Deployment (Render.com)

โปรเจกต์นี้มีไฟล์ `render.yaml` สำหรับการ deploy บน Render.com อย่างง่ายดาย

**ขั้นตอน:**

1.  **สร้างบัญชี Render:** สมัครใช้งานที่ [render.com](https://render.com/)
2.  **สร้าง Blueprint Instance ใหม่:**
    *   ไปที่ "Blueprints" และคลิก "New Blueprint Instance"
    *   เชื่อมต่อ Git repository ของคุณ (GitHub, GitLab, Bitbucket) ที่มีโปรเจกต์นี้อยู่
    *   Render จะตรวจจับ `render.yaml` โดยอัตโนมัติ
3.  **กำหนดค่า Services:**
    *   Render จะเสนอ services ตาม `render.yaml` (backend, frontend, database)
    *   **Database:** ตรวจสอบให้แน่ใจว่าฐานข้อมูล PostgreSQL (`budget-control-db`) ได้รับการกำหนดค่า (เลือก plan, region, etc.)
    *   **Backend (`budget-control-backend`):**
        *   ตรวจสอบคำสั่ง build และ start
        *   ไปที่แท็บ "Environment"
        *   `DATABASE_URL` ควรเชื่อมโยงจาก database service โดยอัตโนมัติ
        *   `JWT_SECRET` จะถูกสร้างโดย Render (หรือคุณสามารถตั้งค่าเองได้)
        *   **สำคัญ: เพิ่ม secret environment variable สำหรับ `GOOGLE_CLIENT_ID` ด้วย Google Client ID จริงของคุณ**
    *   **Frontend (`budget-control-frontend`):**
        *   ตรวจสอบคำสั่ง build และ publish directory
        *   ไปที่แท็บ "Environment"
        *   `REACT_APP_API_URL` ควรเชื่อมโยงจาก backend service โดยอัตโนมัติ
        *   **สำคัญ: เพิ่ม secret environment variable สำหรับ `REACT_APP_GOOGLE_CLIENT_ID` ด้วย Google Client ID จริงของคุณ**
4.  **Deploy:** คลิก "Create Blueprint Instance" หรือ "Deploy" Render จะ build และ deploy services ของคุณ
5.  **เข้าถึง:** เมื่อ deploy เสร็จแล้ว Render จะให้ URL สาธารณะสำหรับ frontend และ backend ของคุณ

**ข้อควรทราบสำคัญสำหรับการ Deployment:**

*   **Environment Variables:** ตรวจสอบให้แน่ใจว่าตัวแปรสภาพแวดล้อมที่จำเป็นทั้งหมด โดยเฉพาะ secrets เช่น `GOOGLE_CLIENT_ID` และ `JWT_SECRET` ได้รับการตั้งค่าอย่างถูกต้องในส่วน environment ของ Render dashboard สำหรับทั้ง backend และ frontend services
*   **การเชื่อมต่อฐานข้อมูล:** `DATABASE_URL` จะถูกจัดเตรียมโดยอัตโนมัติโดย Render เมื่อเชื่อมโยง database service
*   **การกำหนดค่า Google OAuth:** ตรวจสอบให้แน่ใจว่า Google Cloud OAuth Client ID ของคุณมี authorized JavaScript origins ที่ถูกต้อง (สำหรับ URL ของ frontend ที่ Render ให้มา) และ authorized redirect URIs (ถ้ามี แม้ว่าการตั้งค่านี้จะใช้ token-based flow)

## คำอธิบายโค้ด (ภาษาไทย)

คำอธิบายโค้ดโดยละเอียดเป็นภาษาไทยมีอยู่ในรูปแบบคอมเมนต์ภายในไฟล์ซอร์สโค้ดสำหรับองค์ประกอบหลักต่างๆ ทั้งในส่วนของ Backend และ Frontend

