# การปรับเปลี่ยนจาก Firebase ไปเป็น Express Server

โปรเจคนี้ได้ถูกปรับเปลี่ยนจากการใช้ Firebase Cloud Functions มาเป็น Express Server เพื่อให้สามารถ deploy บน render.com หรือ railway.app ได้ โดยยังคงการล็อกอินด้วย Gmail ไว้

## การเปลี่ยนแปลงที่สำคัญ

1. **ลบ Firebase Cloud Functions**
   - ลบโฟลเดอร์ `functions/` ที่มีไฟล์ setup.js และ index.js
   - แก้ไขไฟล์ firebase.json เพื่อลบการกำหนดค่าฟังก์ชัน Firebase

2. **เพิ่ม Express Server**
   - สร้างไฟล์ `server.js` สำหรับเป็น backend ใหม่
   - สร้างไฟล์ `server-package.json` สำหรับการติดตั้ง dependencies ของ server

3. **การล็อกอินด้วย Gmail**
   - ยังคงใช้การล็อกอินด้วย Gmail ผ่าน Google OAuth
   - ใช้ google-auth-library สำหรับการตรวจสอบ token

## วิธีการติดตั้งและใช้งาน

### การติดตั้ง

1. ติดตั้ง dependencies สำหรับ client
   ```
   npm install
   ```

2. ติดตั้ง dependencies สำหรับ server
   ```
   cp server-package.json package.json
   npm install
   ```

### การรัน local

1. รัน client
   ```
   npm start
   ```

2. รัน server
   ```
   node server.js
   ```

## การ Deploy

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

## การปรับแต่งเพิ่มเติม

1. **การเชื่อมต่อกับฐานข้อมูล**
   - คุณสามารถเชื่อมต่อกับฐานข้อมูลอื่นๆ เช่น MongoDB, PostgreSQL แทน Firestore ได้
   - ปรับแต่งไฟล์ `server.js` เพื่อเพิ่มการเชื่อมต่อกับฐานข้อมูลที่ต้องการ

2. **การเพิ่ม API Endpoints**
   - เพิ่ม routes ใหม่ในไฟล์ `server.js` ตามความต้องการ

3. **การปรับแต่ง Frontend**
   - ปรับแต่งไฟล์ในโฟลเดอร์ `src/` เพื่อให้เชื่อมต่อกับ API ใหม่แทน Firebase