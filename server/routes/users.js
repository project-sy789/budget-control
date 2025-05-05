// Routes for user management (Admin only)

// Thai: ไฟล์กำหนดเส้นทาง (routes) สำหรับการจัดการผู้ใช้ (สำหรับแอดมินเท่านั้น)
// - GET /api/users: ดึงข้อมูลผู้ใช้ทั้งหมด
// - PUT /api/users/:uid/role: อัปเดตบทบาทผู้ใช้
// - PUT /api/users/:uid/approve: อนุมัติผู้ใช้
// - DELETE /api/users/:uid: ลบผู้ใช้

const express = require("express");
const {
  getAllUsers,
  updateUserRole,
  approveUser,
  deleteUser,
} = require("../controllers/userController");
const protect = require("../middleware/authMiddleware"); // Middleware for authentication
const authorize = require("../middleware/authorize"); // Middleware for role-based authorization

const router = express.Router();

// Apply authentication and admin authorization to all routes in this file
// Thai: ใช้ middleware `protect` เพื่อยืนยันตัวตน และ `authorize("admin")` เพื่อจำกัดสิทธิ์เฉพาะแอดมิน สำหรับทุกเส้นทางในไฟล์นี้
router.use(protect);
router.use(authorize("admin"));

// GET /api/users - Get all users
// Thai: เส้นทางสำหรับดึงข้อมูลผู้ใช้ทั้งหมด
router.get("/", getAllUsers);

// PUT /api/users/:uid/role - Update user role
// Thai: เส้นทางสำหรับอัปเดตบทบาทของผู้ใช้ตาม uid ที่ระบุ
router.put("/:uid/role", updateUserRole);

// PUT /api/users/:uid/approve - Approve user
// Thai: เส้นทางสำหรับอัปเดตสถานะการอนุมัติของผู้ใช้ตาม uid ที่ระบุ
router.put("/:uid/approve", approveUser);

// DELETE /api/users/:uid - Delete user
// Thai: เส้นทางสำหรับลบผู้ใช้ตาม uid ที่ระบุ
router.delete("/:uid", deleteUser);

module.exports = router;

