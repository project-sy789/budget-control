// Model for interacting with the users table in PostgreSQL

// Thai: โมเดลสำหรับจัดการข้อมูลผู้ใช้ในฐานข้อมูล PostgreSQL
// ประกอบด้วยฟังก์ชันสำหรับการสร้าง, ค้นหา, อัปเดต, และลบข้อมูลผู้ใช้

const db = require("../config/db");

const User = {
  // Find user by Google UID
  // Thai: ค้นหาผู้ใช้ด้วย Google User ID (uid)
  findById: async (uid) => {
    const query = "SELECT * FROM users WHERE uid = $1";
    try {
      const { rows } = await db.query(query, [uid]);
      return rows[0]; // Returns user object or undefined
    } catch (error) {
      console.error("Error finding user by ID:", error);
      throw error; // Re-throw the error to be handled by the controller/error handler
    }
  },

  // Find user by email
  // Thai: ค้นหาผู้ใช้ด้วยอีเมล
  findByEmail: async (email) => {
    const query = "SELECT * FROM users WHERE email = $1";
    try {
      const { rows } = await db.query(query, [email]);
      return rows[0]; // Returns user object or undefined
    } catch (error) {
      console.error("Error finding user by email:", error);
      throw error;
    }
  },

  // Create a new user or update existing user based on Google OAuth info
  // Thai: สร้างผู้ใช้ใหม่ หรืออัปเดตข้อมูลผู้ใช้เดิม (กรณีล็อกอินด้วย Google)
  findOrCreate: async (googleProfile) => {
    const { sub: uid, email, name: displayName, picture: photoURL } = googleProfile;

    try {
      // Check if user exists by UID
      let user = await User.findById(uid);

      if (user) {
        // User exists, update display name and photo URL if changed
        if (user.display_name !== displayName || user.photo_url !== photoURL) {
          const updateQuery = "UPDATE users SET display_name = $1, photo_url = $2, updated_at = NOW() WHERE uid = $3 RETURNING *";
          const { rows } = await db.query(updateQuery, [displayName, photoURL, uid]);
          console.log(`User ${uid} updated.`);
          return rows[0];
        } else {
          // No changes needed
          return user;
        }
      } else {
        // User does not exist, create new user
        // Determine role and approval status (admin email gets special treatment)
        const isAdminEmail = email === process.env.ADMIN_EMAIL; // Ensure ADMIN_EMAIL is set in .env
        const role = isAdminEmail ? "admin" : "user";
        const approved = isAdminEmail; // Auto-approve admin

        const insertQuery = `
          INSERT INTO users (uid, email, display_name, photo_url, role, approved)
          VALUES ($1, $2, $3, $4, $5, $6)
          RETURNING *
        `;
        const { rows } = await db.query(insertQuery, [uid, email, displayName, photoURL, role, approved]);
        console.log(`New user ${uid} created.`);
        return rows[0];
      }
    } catch (error) {
      console.error("Error in findOrCreate user:", error);
      // Handle potential unique constraint violation on email if UID changes but email exists
      if (error.code === "23505" && error.constraint === "users_email_key") {
        throw new Error(`อีเมล ${email} ถูกใช้งานโดยบัญชีอื่นแล้ว`);
      }
      throw error;
    }
  },

  // Get all users (for admin)
  // Thai: ดึงข้อมูลผู้ใช้ทั้งหมด (สำหรับแอดมิน)
  findAll: async () => {
    const query = "SELECT uid, email, display_name, photo_url, role, approved, created_at, updated_at FROM users ORDER BY created_at DESC";
    try {
      const { rows } = await db.query(query);
      return rows;
    } catch (error) {
      console.error("Error finding all users:", error);
      throw error;
    }
  },

  // Update user role (for admin)
  // Thai: อัปเดตบทบาทของผู้ใช้ (สำหรับแอดมิน)
  updateRole: async (uid, role) => {
    const query = "UPDATE users SET role = $1, updated_at = NOW() WHERE uid = $2 RETURNING *";
    try {
      const { rows } = await db.query(query, [role, uid]);
      if (rows.length === 0) {
        throw new Error("ไม่พบผู้ใช้ที่ต้องการอัปเดตบทบาท"); // Or return null/false
      }
      console.log(`User ${uid} role updated to ${role}.`);
      return rows[0];
    } catch (error) {
      console.error("Error updating user role:", error);
      throw error;
    }
  },

  // Update user approval status (for admin)
  // Thai: อัปเดตสถานะการอนุมัติของผู้ใช้ (สำหรับแอดมิน)
  updateApproval: async (uid, approved) => {
    const query = "UPDATE users SET approved = $1, updated_at = NOW() WHERE uid = $2 RETURNING *";
    try {
      const { rows } = await db.query(query, [approved, uid]);
      if (rows.length === 0) {
        throw new Error("ไม่พบผู้ใช้ที่ต้องการอัปเดตสถานะการอนุมัติ");
      }
      console.log(`User ${uid} approval status set to ${approved}.`);
      return rows[0];
    } catch (error) {
      console.error("Error updating user approval:", error);
      throw error;
    }
  },

  // Delete user (for admin)
  // Thai: ลบผู้ใช้ (สำหรับแอดมิน)
  delete: async (uid) => {
    const query = "DELETE FROM users WHERE uid = $1 RETURNING uid";
    try {
      // Optional: Add check to prevent deleting the primary admin
      const userToDelete = await User.findById(uid);
      if (userToDelete && userToDelete.email === process.env.ADMIN_EMAIL) {
        throw new Error("ไม่สามารถลบแอดมินหลักได้");
      }

      const { rowCount } = await db.query(query, [uid]);
      if (rowCount === 0) {
        throw new Error("ไม่พบผู้ใช้ที่ต้องการลบ");
      }
      console.log(`User ${uid} deleted.`);
      return true; // Indicate successful deletion
    } catch (error) {
      console.error("Error deleting user:", error);
      throw error;
    }
  },
};

module.exports = User;

