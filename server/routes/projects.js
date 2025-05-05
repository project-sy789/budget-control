// Routes for project management

// Thai: ไฟล์กำหนดเส้นทาง (routes) สำหรับการจัดการโครงการ
// - GET /api/projects: ดึงข้อมูลโครงการทั้งหมด (พร้อมสรุปงบประมาณ)
// - POST /api/projects: สร้างโครงการใหม่
// - GET /api/projects/:id: ดึงข้อมูลโครงการตาม ID (พร้อมสรุปงบประมาณ)
// - PUT /api/projects/:id: อัปเดตข้อมูลโครงการ
// - DELETE /api/projects/:id: ลบโครงการ

const express = require("express");
const {
  createProject,
  getAllProjects,
  getProjectById,
  updateProject,
  deleteProject,
} = require("../controllers/projectController");
const protect = require("../middleware/authMiddleware"); // Middleware for authentication
// Note: Authorization (e.g., only admins can delete?) can be added here if needed
// const authorize = require("../middleware/authorize");

const router = express.Router();

// Apply authentication to all project routes
// Thai: ใช้ middleware `protect` เพื่อยืนยันตัวตนสำหรับทุกเส้นทางที่เกี่ยวกับโครงการ
router.use(protect);

// GET /api/projects - Get all projects
// Thai: เส้นทางสำหรับดึงข้อมูลโครงการทั้งหมด
router.get("/", getAllProjects);

// POST /api/projects - Create a new project
// Thai: เส้นทางสำหรับสร้างโครงการใหม่
router.post("/", createProject);

// GET /api/projects/:id - Get a single project by ID
// Thai: เส้นทางสำหรับดึงข้อมูลโครงการตาม ID ที่ระบุ
router.get("/:id", getProjectById);

// PUT /api/projects/:id - Update a project
// Thai: เส้นทางสำหรับอัปเดตข้อมูลโครงการตาม ID ที่ระบุ
router.put("/:id", updateProject);

// DELETE /api/projects/:id - Delete a project
// Thai: เส้นทางสำหรับลบโครงการตาม ID ที่ระบุ
// Optional: Add admin authorization: router.delete("/:id", authorize("admin"), deleteProject);
router.delete("/:id", deleteProject);

module.exports = router;

