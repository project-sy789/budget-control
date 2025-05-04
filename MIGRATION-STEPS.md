# ขั้นตอนการย้ายจาก Firebase ไปยัง Express Server

เอกสารนี้อธิบายขั้นตอนที่จำเป็นในการย้ายแอปพลิเคชันจาก Firebase Cloud Functions ไปยัง Express Server เพื่อให้สามารถ deploy บน render.com หรือ railway.app ได้ โดยยังคงการล็อกอินด้วย Gmail ไว้

## ขั้นตอนที่ 1: ลบไฟล์และโฟลเดอร์ที่เกี่ยวข้องกับ Firebase

1. ลบโฟลเดอร์และไฟล์ที่เกี่ยวข้องกับ Firebase ทั้งหมด
   ```
   rm -rf .firebase/ .firebaserc firebase.json functions/
   ```

2. ตรวจสอบว่าไม่มีไฟล์หรือโฟลเดอร์ที่เกี่ยวข้องกับ Firebase เหลืออยู่ และปรับปรุงไฟล์ .gitignore เพื่อป้องกันการอัปโหลดไฟล์ Firebase ที่อาจหลงเหลืออยู่

## ขั้นตอนที่ 2: ติดตั้ง Express Server

1. ติดตั้ง dependencies สำหรับ Express Server
   ```
   cp server-package.json package.json
   npm install
   ```

2. ตั้งค่าไฟล์สภาพแวดล้อม
   ```
   cp .env.example .env
   ```

3. แก้ไขไฟล์ .env เพื่อตั้งค่า GOOGLE_CLIENT_ID สำหรับ Google OAuth

## ขั้นตอนที่ 3: ปรับแต่ง Frontend

1. ปรับแต่งไฟล์ในโฟลเดอร์ `src/` เพื่อให้เชื่อมต่อกับ API ใหม่แทน Firebase
   - ใช้ไฟล์ `src/services/AuthService.js` ที่สร้างขึ้นใหม่แทนการใช้ Firebase Authentication
   - ปรับแต่งไฟล์อื่นๆ ที่เกี่ยวข้องกับการเชื่อมต่อกับ Firebase

2. ตั้งค่า API URL ในไฟล์ .env ของ React
   ```
   REACT_APP_API_URL=http://localhost:3001
   ```

## ขั้นตอนที่ 4: ทดสอบการทำงาน

1. รัน Express Server
   ```
   node server.js
   ```

2. รัน React App
   ```
   npm start
   ```

3. ทดสอบการล็อกอินด้วย Gmail และฟังก์ชันอื่นๆ

## ขั้นตอนที่ 5: Deploy บน Render.com หรือ Railway.app

### การ Deploy บน Render.com

1. สร้าง Web Service ใหม่บน Render.com
2. เชื่อมต่อกับ GitHub repository ของคุณ
3. ตั้งค่าดังนี้:
   - **Environment**: Node
   - **Build Command**: `npm install && npm run build`
   - **Start Command**: `node server.js`
   - **Environment Variables**: ตั้งค่า `GOOGLE_CLIENT_ID` สำหรับ Google OAuth

### การ Deploy บน Railway.app

1. สร้าง Project ใหม่บน Railway.app
2. เชื่อมต่อกับ GitHub repository ของคุณ
3. ตั้งค่า Environment Variables: `GOOGLE_CLIENT_ID`
4. Railway จะ deploy โปรเจคโดยอัตโนมัติ

## หมายเหตุสำคัญ

- ไฟล์ `server.js` ที่สร้างขึ้นใหม่จะทำหน้าที่แทน Firebase Cloud Functions
- ไฟล์ `src/services/AuthService.js` จะจัดการการล็อกอินด้วย Gmail แทนการใช้ Firebase Authentication
- ไฟล์ `render.yaml` จะช่วยในการ deploy บน render.com
- ไฟล์ `Procfile` จะช่วยในการ deploy บน railway.app

โปรดดูรายละเอียดเพิ่มเติมในไฟล์ README-migration.md