// Model for interacting with the transactions table in PostgreSQL

// Thai: โมเดลสำหรับจัดการข้อมูลธุรกรรมในฐานข้อมูล PostgreSQL
// ประกอบด้วยฟังก์ชันสำหรับการสร้าง, ค้นหา (พร้อม pagination), อัปเดต, และลบข้อมูลธุรกรรม

const db = require("../config/db");

const Transaction = {
  // Create a new transaction
  // Thai: สร้างธุรกรรมใหม่
  create: async (transactionData) => {
    const { projectId, description, amount, type, date } = transactionData;
    // Ensure amount is positive, type determines income/expense
    const positiveAmount = Math.abs(amount);
    const query = `
      INSERT INTO transactions (project_id, description, amount, type, date)
      VALUES ($1, $2, $3, $4, $5)
      RETURNING *
    `;
    try {
      // Validate project exists before inserting (optional, could be done in controller)
      const projectExists = await db.query("SELECT 1 FROM projects WHERE id = $1", [projectId]);
      if (projectExists.rowCount === 0) {
        throw new Error(`ไม่พบโครงการสำหรับ projectId: ${projectId}`);
      }

      const { rows } = await db.query(query, [projectId, description, positiveAmount, type, date]);
      console.log(`Transaction created with ID ${rows[0].id} for project ${projectId}.`);
      return rows[0];
    } catch (error) {
      console.error("Error creating transaction:", error);
      // Handle specific errors like foreign key violation (if project check is removed)
      if (error.code === "23503") { // foreign_key_violation
        throw new Error(`ไม่พบโครงการสำหรับ projectId: ${projectId}`);
      }
      // Handle check constraint violation for type
      if (error.code === "23514" && error.constraint === "transactions_type_check") {
          throw new Error(`ประเภทธุรกรรมไม่ถูกต้อง (ต้องเป็น 'income' หรือ 'expense')`);
      }
      throw error; // Re-throw for generic handling
    }
  },

  // Find transactions with filtering and pagination
  // Thai: ค้นหาธุรกรรม สามารถกรองตาม projectId และแบ่งหน้า (pagination)
  findAll: async ({ projectId, page = 1, limit = 10 }) => {
    const offset = (page - 1) * limit;
    let query = "SELECT * FROM transactions";
    let countQuery = "SELECT COUNT(*) FROM transactions";
    const queryParams = [];
    const countParams = [];

    if (projectId) {
      query += " WHERE project_id = $1";
      countQuery += " WHERE project_id = $1";
      queryParams.push(projectId);
      countParams.push(projectId);
    }

    query += " ORDER BY date DESC LIMIT $" + (queryParams.length + 1) + " OFFSET $" + (queryParams.length + 2);
    queryParams.push(limit, offset);

    try {
      const { rows } = await db.query(query, queryParams);
      const { rows: countRows } = await db.query(countQuery, countParams);
      const totalTransactions = parseInt(countRows[0].count, 10);
      const totalPages = Math.ceil(totalTransactions / limit);

      return {
        transactions: rows,
        currentPage: page,
        totalPages: totalPages,
        totalTransactions: totalTransactions,
      };
    } catch (error) {
      console.error("Error finding transactions:", error);
      throw error;
    }
  },

  // Find a transaction by its ID
  // Thai: ค้นหาธุรกรรมด้วย ID
  findById: async (id) => {
    const query = "SELECT * FROM transactions WHERE id = $1";
    try {
      const { rows } = await db.query(query, [id]);
      return rows[0]; // Returns transaction object or undefined
    } catch (error) {
      console.error("Error finding transaction by ID:", error);
      throw error;
    }
  },

  // Update an existing transaction
  // Thai: อัปเดตข้อมูลธุรกรรมที่มีอยู่
  update: async (id, transactionData) => {
    const { projectId, description, amount, type, date } = transactionData;
    // Ensure amount is positive
    const positiveAmount = Math.abs(amount);
    const query = `
      UPDATE transactions
      SET project_id = $1, description = $2, amount = $3, type = $4, date = $5, updated_at = NOW()
      WHERE id = $6
      RETURNING *
    `;
    try {
      // Optional: Validate project exists
      if (projectId) {
          const projectExists = await db.query("SELECT 1 FROM projects WHERE id = $1", [projectId]);
          if (projectExists.rowCount === 0) {
            throw new Error(`ไม่พบโครงการสำหรับ projectId ที่ต้องการอัปเดต: ${projectId}`);
          }
      }

      const { rows } = await db.query(query, [projectId, description, positiveAmount, type, date, id]);
      if (rows.length === 0) {
        throw new Error("ไม่พบธุรกรรมที่ต้องการอัปเดต");
      }
      console.log(`Transaction ${id} updated.`);
      return rows[0];
    } catch (error) {
      console.error("Error updating transaction:", error);
      // Handle specific errors
      if (error.code === "23503") { // foreign_key_violation
        throw new Error(`ไม่พบโครงการสำหรับ projectId: ${projectId}`);
      }
      if (error.code === "23514" && error.constraint === "transactions_type_check") {
          throw new Error(`ประเภทธุรกรรมไม่ถูกต้อง (ต้องเป็น 'income' หรือ 'expense')`);
      }
      throw error;
    }
  },

  // Delete a transaction by its ID
  // Thai: ลบธุรกรรมด้วย ID
  delete: async (id) => {
    const query = "DELETE FROM transactions WHERE id = $1 RETURNING id";
    try {
      const { rowCount } = await db.query(query, [id]);
      if (rowCount === 0) {
        throw new Error("ไม่พบธุรกรรมที่ต้องการลบ");
      }
      console.log(`Transaction ${id} deleted.`);
      return true; // Indicate successful deletion
    } catch (error) {
      console.error("Error deleting transaction:", error);
      throw error;
    }
  },

  // Get total income and expense for a specific project
  // Thai: คำนวณยอดรวมรายรับและรายจ่ายสำหรับโครงการที่ระบุ
  getProjectBudgetSummary: async (projectId) => {
    const query = `
      SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expense
      FROM transactions
      WHERE project_id = $1;
    `;
    try {
      const { rows } = await db.query(query, [projectId]);
      return rows[0]; // Returns { total_income, total_expense }
    } catch (error) {
      console.error(`Error getting budget summary for project ${projectId}:`, error);
      throw error;
    }
  }
};

module.exports = Transaction;

