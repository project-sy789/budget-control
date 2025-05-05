// Model for interacting with the projects table in PostgreSQL

// Thai: โมเดลสำหรับจัดการข้อมูลโครงการในฐานข้อมูล PostgreSQL
// ประกอบด้วยฟังก์ชันสำหรับการสร้าง, ค้นหา, อัปเดต, และลบข้อมูลโครงการ

const db = require("../config/db");

const Project = {
  // Create a new project
  // Thai: สร้างโครงการใหม่
  create: async (projectData) => {
    const { name, startDate, endDate, initialBudget } = projectData;
    const query = `
      INSERT INTO projects (name, start_date, end_date, initial_budget)
      VALUES ($1, $2, $3, $4)
      RETURNING *
    `;
    try {
      const { rows } = await db.query(query, [name, startDate, endDate, initialBudget]);
      console.log(`Project "${name}" created with ID ${rows[0].id}.`);
      return rows[0];
    } catch (error) {
      console.error("Error creating project:", error);
      // Handle specific errors like unique constraint violation
      if (error.code === "23505" && error.constraint === "projects_name_key") {
        throw new Error(`ชื่อโครงการ "${name}" มีอยู่ในระบบแล้ว`);
      }
      // Handle date constraint violation
      if (error.code === "23514" && error.constraint === "dates_check") {
          throw new Error("วันที่สิ้นสุดโครงการต้องไม่มาก่อนวันที่เริ่มต้น");
      }
      throw error; // Re-throw for generic handling
    }
  },

  // Find all projects, sorted by start date descending
  // Thai: ค้นหาโครงการทั้งหมด เรียงตามวันที่เริ่มต้นล่าสุด
  findAll: async () => {
    const query = "SELECT * FROM projects ORDER BY start_date DESC";
    try {
      const { rows } = await db.query(query);
      return rows;
    } catch (error) {
      console.error("Error finding all projects:", error);
      throw error;
    }
  },

  // Find a project by its ID
  // Thai: ค้นหาโครงการด้วย ID
  findById: async (id) => {
    const query = "SELECT * FROM projects WHERE id = $1";
    try {
      const { rows } = await db.query(query, [id]);
      return rows[0]; // Returns project object or undefined
    } catch (error) {
      console.error("Error finding project by ID:", error);
      throw error;
    }
  },

  // Update an existing project
  // Thai: อัปเดตข้อมูลโครงการที่มีอยู่
  update: async (id, projectData) => {
    const { name, startDate, endDate, initialBudget } = projectData;
    // Ensure all fields are provided or fetch existing data first if partial updates are allowed
    const query = `
      UPDATE projects
      SET name = $1, start_date = $2, end_date = $3, initial_budget = $4, updated_at = NOW()
      WHERE id = $5
      RETURNING *
    `;
    try {
      const { rows } = await db.query(query, [name, startDate, endDate, initialBudget, id]);
      if (rows.length === 0) {
        throw new Error("ไม่พบโครงการที่ต้องการอัปเดต");
      }
      console.log(`Project ${id} updated.`);
      return rows[0];
    } catch (error) {
      console.error("Error updating project:", error);
      // Handle specific errors like unique constraint violation
      if (error.code === "23505" && error.constraint === "projects_name_key") {
        throw new Error(`ชื่อโครงการ "${name}" มีอยู่ในระบบแล้ว`);
      }
      // Handle date constraint violation
      if (error.code === "23514" && error.constraint === "dates_check") {
          throw new Error("วันที่สิ้นสุดโครงการต้องไม่มาก่อนวันที่เริ่มต้น");
      }
      throw error;
    }
  },

  // Delete a project by its ID
  // Note: Transactions associated with this project will be deleted automatically due to ON DELETE CASCADE
  // Thai: ลบโครงการด้วย ID (ธุรกรรมที่เกี่ยวข้องจะถูกลบไปด้วยเนื่องจากตั้งค่า ON DELETE CASCADE)
  delete: async (id) => {
    const query = "DELETE FROM projects WHERE id = $1 RETURNING id";
    try {
      const { rowCount } = await db.query(query, [id]);
      if (rowCount === 0) {
        throw new Error("ไม่พบโครงการที่ต้องการลบ");
      }
      console.log(`Project ${id} deleted (associated transactions also deleted).`);
      return true; // Indicate successful deletion
    } catch (error) {
      console.error("Error deleting project:", error);
      throw error;
    }
  },
};

module.exports = Project;

