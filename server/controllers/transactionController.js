// Controller for handling transaction management logic

// Thai: คอนโทรลเลอร์สำหรับจัดการตรรกะเกี่ยวกับธุรกรรม (Transactions)
// - สร้างธุรกรรมใหม่
// - ดึงข้อมูลธุรกรรม (พร้อม pagination และ filter)
// - ดึงข้อมูลธุรกรรมตาม ID
// - อัปเดตข้อมูลธุรกรรม
// - ลบธุรกรรม
// - (อาจรวมถึง) ส่งออกข้อมูลธุรกรรมเป็น Excel

const Transaction = require("../models/Transaction");
const Project = require("../models/Project"); // Needed to check project existence
const xlsx = require("xlsx"); // For Excel export

// Create a new transaction
// Thai: สร้างธุรกรรมใหม่
exports.createTransaction = async (req, res, next) => {
  const { projectId, description, amount, type, date } = req.body;

  // Thai: ตรวจสอบข้อมูลที่จำเป็นเบื้องต้น
  if (!projectId || !description || amount === undefined || !type || !date) {
    const error = new Error("ข้อมูลธุรกรรมไม่ครบถ้วน (ต้องการ: projectId, description, amount, type, date)");
    error.statusCode = 400; // Bad Request
    return next(error);
  }

  // Thai: ตรวจสอบประเภทธุรกรรมที่ถูกต้อง
  if (!["income", "expense"].includes(type)) {
      const error = new Error("ประเภทธุรกรรมไม่ถูกต้อง (ต้องเป็น 'income' หรือ 'expense')");
      error.statusCode = 400;
      return next(error);
  }

  try {
    // Thai: (ตรวจสอบเพิ่มเติม) ว่า projectId ที่ระบุมีอยู่จริงหรือไม่
    const project = await Project.findById(projectId);
    if (!project) {
        const error = new Error(`ไม่พบโครงการสำหรับ projectId: ${projectId}`);
        error.statusCode = 404; // Not Found
        return next(error);
    }

    // Thai: เรียกใช้โมเดล Transaction เพื่อสร้างธุรกรรมใหม่ในฐานข้อมูล
    const newTransaction = await Transaction.create({ projectId, description, amount, type, date });

    // Thai: ส่งข้อมูลธุรกรรมที่สร้างใหม่กลับไปใน response (Status 201 Created)
    res.status(201).json({
      success: true,
      message: "สร้างธุรกรรมสำเร็จ",
      data: newTransaction,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error("Error creating transaction:", error);
    next(error);
  }
};

// Get transactions with filtering and pagination
// Thai: ดึงข้อมูลธุรกรรม พร้อมการแบ่งหน้า (pagination) และกรองตาม projectId
exports.getTransactions = async (req, res, next) => {
  // Thai: ดึงค่า query parameters สำหรับการกรองและแบ่งหน้า
  const projectId = req.query.projectId; // Filter by project ID
  const page = parseInt(req.query.page, 10) || 1; // Current page number, default 1
  const limit = parseInt(req.query.limit, 10) || 10; // Items per page, default 10

  // Thai: ตรวจสอบค่า page และ limit ว่าเป็นบวก
  if (page <= 0 || limit <= 0) {
      const error = new Error("ค่า page และ limit ต้องเป็นจำนวนเต็มบวก");
      error.statusCode = 400;
      return next(error);
  }

  try {
    // Thai: เรียกใช้โมเดล Transaction เพื่อดึงข้อมูลธุรกรรมตามเงื่อนไข
    const result = await Transaction.findAll({ projectId, page, limit });

    // Thai: ส่งข้อมูลธุรกรรมและข้อมูล pagination กลับไปใน response
    res.status(200).json({
      success: true,
      count: result.transactions.length,
      pagination: {
        currentPage: result.currentPage,
        totalPages: result.totalPages,
        totalItems: result.totalTransactions,
        limit: limit
      },
      data: result.transactions,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error("Error getting transactions:", error);
    next(error);
  }
};

// Get a single transaction by ID
// Thai: ดึงข้อมูลธุรกรรมตาม ID ที่ระบุ
exports.getTransactionById = async (req, res, next) => {
  const { id } = req.params; // Transaction ID from URL parameter

  try {
    // Thai: เรียกใช้โมเดล Transaction เพื่อค้นหาธุรกรรมด้วย ID
    const transaction = await Transaction.findById(id);

    // Thai: ตรวจสอบว่าพบธุรกรรมหรือไม่
    if (!transaction) {
      const error = new Error("ไม่พบธุรกรรมที่ระบุ");
      error.statusCode = 404; // Not Found
      return next(error);
    }

    // Thai: ส่งข้อมูลธุรกรรมกลับไปใน response
    res.status(200).json({
      success: true,
      data: transaction,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error getting transaction ${id}:`, error);
    next(error);
  }
};

// Update a transaction
// Thai: อัปเดตข้อมูลธุรกรรม
exports.updateTransaction = async (req, res, next) => {
  const { id } = req.params; // Transaction ID from URL parameter
  const { projectId, description, amount, type, date } = req.body;

  // Thai: ตรวจสอบข้อมูลที่จำเป็นเบื้องต้น
  if (!projectId || !description || amount === undefined || !type || !date) {
    const error = new Error("ข้อมูลธุรกรรมที่ต้องการอัปเดตไม่ครบถ้วน");
    error.statusCode = 400;
    return next(error);
  }
  // Thai: ตรวจสอบประเภทธุรกรรมที่ถูกต้อง
  if (!["income", "expense"].includes(type)) {
      const error = new Error("ประเภทธุรกรรมไม่ถูกต้อง (ต้องเป็น 'income' หรือ 'expense')");
      error.statusCode = 400;
      return next(error);
  }

  try {
    // Thai: (ตรวจสอบเพิ่มเติม) ว่า projectId ที่ระบุมีอยู่จริงหรือไม่
    const project = await Project.findById(projectId);
    if (!project) {
        const error = new Error(`ไม่พบโครงการสำหรับ projectId: ${projectId}`);
        error.statusCode = 404; // Not Found
        return next(error);
    }

    // Thai: เรียกใช้โมเดล Transaction เพื่ออัปเดตข้อมูลธุรกรรมในฐานข้อมูล
    const updatedTransaction = await Transaction.update(id, { projectId, description, amount, type, date });

    // Thai: ส่งข้อมูลธุรกรรมที่อัปเดตแล้วกลับไปใน response
    res.status(200).json({
      success: true,
      message: "อัปเดตธุรกรรมสำเร็จ",
      data: updatedTransaction,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error updating transaction ${id}:`, error);
    next(error);
  }
};

// Delete a transaction
// Thai: ลบธุรกรรม
exports.deleteTransaction = async (req, res, next) => {
  const { id } = req.params; // Transaction ID from URL parameter

  try {
    // Thai: เรียกใช้โมเดล Transaction เพื่อลบธุรกรรมออกจากฐานข้อมูล
    await Transaction.delete(id);

    // Thai: ส่ง response ยืนยันการลบ (Status 204 No Content)
    res.status(204).send();

  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error deleting transaction ${id}:`, error);
    next(error);
  }
};

// Export transactions to Excel
// Thai: ส่งออกข้อมูลธุรกรรมเป็นไฟล์ Excel
exports.exportTransactions = async (req, res, next) => {
    const projectId = req.query.projectId; // Optional filter by project ID

    try {
        // Fetch all transactions (without pagination) based on the filter
        // Note: For very large datasets, consider streaming or background jobs
        let query = "SELECT t.*, p.name as project_name FROM transactions t JOIN projects p ON t.project_id = p.id";
        const params = [];
        if (projectId) {
            query += " WHERE t.project_id = $1";
            params.push(projectId);
        }
        query += " ORDER BY t.date DESC";

        const { rows: transactions } = await require("../config/db").query(query, params);

        if (transactions.length === 0) {
            const error = new Error("ไม่พบข้อมูลธุรกรรมสำหรับส่งออก");
            error.statusCode = 404;
            return next(error);
        }

        // Thai: เตรียมข้อมูลสำหรับใส่ใน Excel
        const dataForExcel = transactions.map(t => ({
            'วันที่': new Date(t.date).toLocaleDateString('th-TH'), // Format date for Thai locale
            'โครงการ': t.project_name,
            'รายการ': t.description,
            'ประเภท': t.type === 'income' ? 'รายรับ' : 'รายจ่าย',
            'จำนวนเงิน': parseFloat(t.amount),
            'ID ธุรกรรม': t.id,
            'ID โครงการ': t.project_id
        }));

        // Thai: สร้าง Workbook และ Worksheet
        const ws = xlsx.utils.json_to_sheet(dataForExcel);
        const wb = xlsx.utils.book_new();
        xlsx.utils.book_append_sheet(wb, ws, "Transactions");

        // Thai: กำหนดชื่อไฟล์
        const filename = `transactions_export_${projectId ? projectId + '_' : ''}${Date.now()}.xlsx`;

        // Thai: ตั้งค่า Headers สำหรับการดาวน์โหลดไฟล์
        res.setHeader('Content-Disposition', `attachment; filename="${filename}"`);
        res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Thai: ส่งไฟล์ Excel กลับไปใน response
        const wbout = xlsx.write(wb, { bookType: 'xlsx', type: 'buffer' });
        res.send(wbout);

    } catch (error) {
        console.error("Error exporting transactions:", error);
        next(error);
    }
};

