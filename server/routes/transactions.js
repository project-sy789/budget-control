// Routes for transaction management

// Thai: ไฟล์กำหนดเส้นทาง (routes) สำหรับการจัดการธุรกรรม
// - GET /api/transactions: ดึงข้อมูลธุรกรรม (พร้อม filter และ pagination)
// - POST /api/transactions: สร้างธุรกรรมใหม่
// - GET /api/transactions/export: ส่งออกข้อมูลธุรกรรมเป็น Excel
// - GET /api/transactions/:id: ดึงข้อมูลธุรกรรมตาม ID
// - PUT /api/transactions/:id: อัปเดตข้อมูลธุรกรรม
// - DELETE /api/transactions/:id: ลบธุรกรรม

const express = require("express");
const {
  createTransaction,
  getTransactions,
  getTransactionById,
  updateTransaction,
  deleteTransaction,
  exportTransactions, // Import the export controller
} = require("../controllers/transactionController");
const protect = require("../middleware/authMiddleware"); // Middleware for authentication
// Note: Authorization can be added if specific roles are needed for certain actions
// const authorize = require("../middleware/authorize");

const router = express.Router();

// Apply authentication to all transaction routes
// Thai: ใช้ middleware `protect` เพื่อยืนยันตัวตนสำหรับทุกเส้นทางที่เกี่ยวกับธุรกรรม
router.use(protect);

// GET /api/transactions/export - Export transactions to Excel
// Thai: เส้นทางสำหรับส่งออกข้อมูลธุรกรรมเป็นไฟล์ Excel (ต้องอยู่ก่อน /:id)
router.get("/export", exportTransactions);

// GET /api/transactions - Get transactions with filtering and pagination
// Thai: เส้นทางสำหรับดึงข้อมูลธุรกรรม (รองรับ query params: projectId, page, limit)
router.get("/", getTransactions);

// POST /api/transactions - Create a new transaction
// Thai: เส้นทางสำหรับสร้างธุรกรรมใหม่
router.post("/", createTransaction);

// GET /api/transactions/:id - Get a single transaction by ID
// Thai: เส้นทางสำหรับดึงข้อมูลธุรกรรมตาม ID ที่ระบุ
router.get("/:id", getTransactionById);

// PUT /api/transactions/:id - Update a transaction
// Thai: เส้นทางสำหรับอัปเดตข้อมูลธุรกรรมตาม ID ที่ระบุ
router.put("/:id", updateTransaction);

// DELETE /api/transactions/:id - Delete a transaction
// Thai: เส้นทางสำหรับลบธุรกรรมตาม ID ที่ระบุ
router.delete("/:id", deleteTransaction);

module.exports = router;

