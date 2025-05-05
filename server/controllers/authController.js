// Controller for handling authentication logic

// Thai: คอนโทรลเลอร์สำหรับจัดการตรรกะเกี่ยวกับการยืนยันตัวตน (Authentication)
// - จัดการการยืนยัน Google ID token
// - สร้างหรือค้นหาผู้ใช้ในฐานข้อมูล
// - สร้าง JSON Web Token (JWT) สำหรับการยืนยันตัวตนในระบบ

const { OAuth2Client } = require("google-auth-library");
const jwt = require("jsonwebtoken");
const User = require("../models/User");
const config = require("../config");

const client = new OAuth2Client(config.google.clientId);

// Handle Google Sign-In verification and JWT generation
// Thai: จัดการการยืนยัน Google Sign-In และสร้าง JWT
exports.verifyGoogleToken = async (req, res, next) => {
  const { token } = req.body;

  // Thai: ตรวจสอบว่ามี token ส่งมาใน request body หรือไม่
  if (!token) {
    const error = new Error("ไม่พบ Google ID token");
    error.statusCode = 400; // Bad Request
    return next(error);
  }

  try {
    // Thai: ยืนยัน ID token กับ Google
    const ticket = await client.verifyIdToken({
      idToken: token,
      audience: config.google.clientId,
    });
    const payload = ticket.getPayload();

    // Thai: ตรวจสอบว่า payload ที่ได้จาก Google มีข้อมูลครบถ้วนหรือไม่
    if (!payload || !payload.sub || !payload.email || !payload.name) {
        const error = new Error("ข้อมูลโปรไฟล์จาก Google ไม่สมบูรณ์");
        error.statusCode = 400;
        return next(error);
    }

    // Thai: ค้นหาหรือสร้างผู้ใช้ในฐานข้อมูล PostgreSQL
    const user = await User.findOrCreate(payload);

    // Thai: ตรวจสอบว่าผู้ใช้ได้รับการอนุมัติให้เข้าระบบหรือไม่
    if (!user.approved) {
      const error = new Error("บัญชีของคุณยังไม่ได้รับการอนุมัติ");
      error.statusCode = 403; // Forbidden
      return next(error);
    }

    // Thai: สร้าง JWT payload ซึ่งประกอบด้วยข้อมูลที่จำเป็นของผู้ใช้
    const jwtPayload = {
      uid: user.uid,
      email: user.email,
      role: user.role,
      approved: user.approved,
      // ไม่ควรใส่ข้อมูลละเอียดอ่อนใน JWT payload
    };

    // Thai: สร้าง JWT token โดยใช้ secret key และกำหนดเวลาหมดอายุ
    const jwtToken = jwt.sign(jwtPayload, config.jwt.secret, {
      expiresIn: config.jwt.expiresIn,
    });

    // Thai: ส่งข้อมูลผู้ใช้และ JWT token กลับไปให้ client
    res.status(200).json({
      success: true,
      message: "การยืนยันตัวตนสำเร็จ",
      token: jwtToken,
      user: {
        uid: user.uid,
        email: user.email,
        displayName: user.display_name,
        photoURL: user.photo_url,
        role: user.role,
        approved: user.approved,
      },
    });
  } catch (error) {
    // Thai: จัดการข้อผิดพลาดที่อาจเกิดขึ้นระหว่างการยืนยัน token หรือการจัดการฐานข้อมูล
    console.error("Error verifying Google token or finding/creating user:", error);
    // Pass the error to the centralized error handler
    // Ensure the error has a message and potentially a status code
    if (!error.statusCode) {
        error.statusCode = 401; // Unauthorized or specific error code from model
    }
    if (!error.message) {
        error.message = "การยืนยัน Google token ล้มเหลว";
    }
    next(error);
  }
};

