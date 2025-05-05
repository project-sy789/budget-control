// Middleware for verifying JWT token and attaching user to request

// Thai: Middleware สำหรับตรวจสอบ JWT token และแนบข้อมูลผู้ใช้ (user payload) ไปกับ request object
// ใช้สำหรับป้องกัน API endpoints ที่ต้องการการยืนยันตัวตน

const jwt = require("jsonwebtoken");
const config = require("../config");
const User = require("../models/User"); // Needed to check if user still exists and is approved

const protect = async (req, res, next) => {
  let token;

  // Thai: ตรวจสอบว่ามี Authorization header และขึ้นต้นด้วย "Bearer " หรือไม่
  if (
    req.headers.authorization &&
    req.headers.authorization.startsWith("Bearer")
  ) {
    try {
      // Thai: ดึง token ออกมาจาก header (ตัดคำว่า "Bearer " ออก)
      token = req.headers.authorization.split(" ")[1];

      // Thai: ตรวจสอบความถูกต้องของ token โดยใช้ secret key
      const decoded = jwt.verify(token, config.jwt.secret);

      // Thai: ค้นหาผู้ใช้ในฐานข้อมูลด้วย uid ที่ได้จาก token payload
      // เพิ่มการตรวจสอบว่าผู้ใช้ยังคงมีอยู่ในระบบและได้รับการอนุมัติหรือไม่
      const currentUser = await User.findById(decoded.uid);

      if (!currentUser) {
          const error = new Error("ไม่พบผู้ใช้ที่เกี่ยวข้องกับ token นี้");
          error.statusCode = 401; // Unauthorized
          return next(error);
      }

      if (!currentUser.approved) {
          const error = new Error("บัญชีผู้ใช้ยังไม่ได้รับการอนุมัติ");
          error.statusCode = 403; // Forbidden
          return next(error);
      }

      // Thai: แนบข้อมูลผู้ใช้ (เฉพาะส่วนที่จำเป็นและปลอดภัย) ไปกับ request object
      // ไม่ควรแนบ password หรือข้อมูลละเอียดอ่อนอื่นๆ
      req.user = {
          uid: currentUser.uid,
          email: currentUser.email,
          role: currentUser.role,
          approved: currentUser.approved
      };

      // Thai: ดำเนินการต่อไปยัง middleware หรือ route handler ถัดไป
      next();
    } catch (error) {
      // Thai: จัดการข้อผิดพลาดที่เกิดจากการตรวจสอบ token (เช่น token หมดอายุ, ไม่ถูกต้อง)
      console.error("Token verification failed:", error.message);
      const authError = new Error("การยืนยันตัวตนล้มเหลว, กรุณาล็อกอินใหม่");
      authError.statusCode = 401; // Unauthorized
      // If the error is specifically about token expiration
      if (error.name === 'TokenExpiredError') {
          authError.message = "Session หมดอายุ, กรุณาล็อกอินใหม่";
      }
      next(authError);
    }
  } else {
    // Thai: กรณีไม่มี token ใน header
    const error = new Error("ไม่พบ Authorization token");
    error.statusCode = 401; // Unauthorized
    next(error);
  }
};

module.exports = protect;

