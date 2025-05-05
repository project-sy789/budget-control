// Controller for handling user management logic (Admin only)

// Thai: คอนโทรลเลอร์สำหรับจัดการตรรกะเกี่ยวกับการจัดการผู้ใช้ (สำหรับแอดมินเท่านั้น)
// - ดึงข้อมูลผู้ใช้ทั้งหมด
// - อัปเดตบทบาทผู้ใช้
// - อนุมัติผู้ใช้
// - ลบผู้ใช้

const User = require("../models/User");

// Get all users
// Thai: ดึงข้อมูลผู้ใช้ทั้งหมด
exports.getAllUsers = async (req, res, next) => {
  try {
    // Thai: เรียกใช้โมเดล User เพื่อดึงข้อมูลผู้ใช้ทั้งหมดจากฐานข้อมูล
    const users = await User.findAll();
    // Thai: ส่งข้อมูลผู้ใช้ทั้งหมดกลับไปใน response
    res.status(200).json({
      success: true,
      count: users.length,
      data: users,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error("Error getting all users:", error);
    next(error); // Pass error to the centralized error handler
  }
};

// Update user role
// Thai: อัปเดตบทบาทของผู้ใช้
exports.updateUserRole = async (req, res, next) => {
  const { uid } = req.params; // User ID from URL parameter
  const { role } = req.body; // New role from request body

  // Thai: ตรวจสอบว่าระบุบทบาทที่ถูกต้องหรือไม่ ("admin" หรือ "user")
  if (!["admin", "user"].includes(role)) {
    const error = new Error("บทบาทที่ระบุไม่ถูกต้อง (ต้องเป็น 'admin' หรือ 'user')");
    error.statusCode = 400; // Bad Request
    return next(error);
  }

  try {
    // Thai: (ป้องกันเพิ่มเติม) ตรวจสอบว่าผู้ใช้ที่ต้องการแก้ไขไม่ใช่แอดมินหลัก
    const userToUpdate = await User.findById(uid);
    if (!userToUpdate) {
        const error = new Error("ไม่พบผู้ใช้ที่ต้องการอัปเดตบทบาท");
        error.statusCode = 404; // Not Found
        return next(error);
    }
    if (userToUpdate.email === process.env.ADMIN_EMAIL && role !== 'admin') {
        const error = new Error("ไม่สามารถเปลี่ยนบทบาทของแอดมินหลักได้");
        error.statusCode = 403; // Forbidden
        return next(error);
    }

    // Thai: เรียกใช้โมเดล User เพื่ออัปเดตบทบาทในฐานข้อมูล
    const updatedUser = await User.updateRole(uid, role);

    // Thai: ส่งข้อมูลผู้ใช้ที่อัปเดตแล้วกลับไปใน response
    res.status(200).json({
      success: true,
      message: `อัปเดตบทบาทของผู้ใช้ ${uid} เป็น ${role} สำเร็จ`,
      data: updatedUser,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error updating role for user ${uid}:`, error);
    next(error);
  }
};

// Approve a user
// Thai: อนุมัติผู้ใช้
exports.approveUser = async (req, res, next) => {
  const { uid } = req.params; // User ID from URL parameter
  const { approved } = req.body; // Approval status from request body (expecting true)

  // Thai: ตรวจสอบว่าค่า approved ที่ส่งมาเป็น boolean หรือไม่
  if (typeof approved !== 'boolean') {
      const error = new Error("สถานะการอนุมัติไม่ถูกต้อง (ต้องเป็น true หรือ false)");
      error.statusCode = 400; // Bad Request
      return next(error);
  }

  try {
    // Thai: เรียกใช้โมเดล User เพื่ออัปเดตสถานะการอนุมัติในฐานข้อมูล
    const updatedUser = await User.updateApproval(uid, approved);

    // Thai: ส่งข้อมูลผู้ใช้ที่อัปเดตแล้วกลับไปใน response
    res.status(200).json({
      success: true,
      message: `อัปเดตสถานะการอนุมัติของผู้ใช้ ${uid} เป็น ${approved} สำเร็จ`,
      data: updatedUser,
    });
  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error updating approval for user ${uid}:`, error);
    next(error);
  }
};

// Delete a user
// Thai: ลบผู้ใช้
exports.deleteUser = async (req, res, next) => {
  const { uid } = req.params; // User ID from URL parameter

  try {
    // Thai: เรียกใช้โมเดล User เพื่อลบผู้ใช้ออกจากฐานข้อมูล
    // การป้องกันการลบแอดมินหลักอยู่ใน Model แล้ว
    await User.delete(uid);

    // Thai: ส่ง response ยืนยันการลบ (Status 204 No Content)
    res.status(204).send(); // No content to send back

  } catch (error) {
    // Thai: หากเกิดข้อผิดพลาด ส่งต่อไปยัง error handler middleware
    console.error(`Error deleting user ${uid}:`, error);
    next(error);
  }
};

