// Middleware for checking user roles

// Thai: Middleware สำหรับตรวจสอบสิทธิ์การเข้าถึงตามบทบาทของผู้ใช้ (Authorization)
// ใช้ร่วมกับ `protect` middleware เพื่อจำกัดการเข้าถึง API endpoints เฉพาะบทบาทที่กำหนด

const authorize = (...roles) => {
  return (req, res, next) => {
    // Thai: ตรวจสอบว่า req.user ถูกสร้างโดย protect middleware หรือไม่ และมี role หรือไม่
    if (!req.user || !req.user.role) {
      const error = new Error("ข้อมูลผู้ใช้ไม่สมบูรณ์สำหรับการตรวจสอบสิทธิ์");
      error.statusCode = 401; // Unauthorized (or 500 Internal Server Error)
      return next(error);
    }

    // Thai: ตรวจสอบว่า role ของผู้ใช้ที่ล็อกอินอยู่ในรายการ roles ที่ได้รับอนุญาตหรือไม่
    if (!roles.includes(req.user.role)) {
      const error = new Error(`ผู้ใช้ไม่มีสิทธิ์เข้าถึงส่วนนี้ (ต้องการบทบาท: ${roles.join(" หรือ ")})`);
      error.statusCode = 403; // Forbidden
      return next(error);
    }

    // Thai: หากผู้ใช้มีสิทธิ์ ดำเนินการต่อไปยัง middleware หรือ route handler ถัดไป
    next();
  };
};

module.exports = authorize;

