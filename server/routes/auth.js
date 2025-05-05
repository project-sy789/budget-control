// Routes for authentication

// Thai: ไฟล์กำหนดเส้นทาง (routes) สำหรับการยืนยันตัวตน
// - เส้นทาง /verify-google สำหรับยืนยัน Google ID token และรับ JWT token กลับไป

const express = require("express");
const { verifyGoogleToken } = require("../controllers/authController");

const router = express.Router();

// POST /api/auth/verify-google
// Thai: เส้นทางสำหรับรับ Google ID token จาก client, ทำการยืนยัน,
//       สร้าง/ค้นหาผู้ใช้, และส่ง JWT token กลับไป
router.post("/verify-google", verifyGoogleToken);

module.exports = router;

