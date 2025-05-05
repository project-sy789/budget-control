// Middleware for handling errors

// Thai: Middleware สำหรับจัดการข้อผิดพลาดที่เกิดขึ้นในแอปพลิเคชัน
// ทำหน้าที่ดักจับข้อผิดพลาดที่ส่งมาจากส่วนต่างๆ และส่ง response กลับไปยัง client ในรูปแบบ JSON ที่เข้าใจง่าย

const errorHandler = (err, req, res, next) => {
  console.error("Error Handler Middleware Caught:", err.stack); // Log the full error stack for debugging

  // Default error status and message
  let statusCode = err.statusCode || 500; // Use specific status code if available, otherwise default to 500
  let message = err.message || "เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์"; // Use specific message if available

  // Customize error messages based on error types (optional but recommended)
  if (err.name === "ValidationError") { // Example: Handle validation errors specifically
    statusCode = 400;
    message = "ข้อมูลที่ส่งมาไม่ถูกต้อง: " + err.message;
  }
  if (err.code === "23505") { // Example: Handle PostgreSQL unique constraint violation
    statusCode = 409; // Conflict
    // Extract detail from PostgreSQL error message if possible
    message = `ข้อมูลซ้ำซ้อน: ${err.detail || "มีข้อมูลนี้อยู่ในระบบแล้ว"}`;
  }
  if (err.name === "UnauthorizedError") { // Example: Handle JWT authentication errors
    statusCode = 401;
    message = "การยืนยันตัวตนล้มเหลว: " + err.message;
  }

  // Send the error response back to the client
  res.status(statusCode).json({
    success: false,
    status: statusCode,
    message: message,
    // Optionally include stack trace in development environment
    stack: process.env.NODE_ENV === "development" ? err.stack : undefined,
  });
};

module.exports = errorHandler;

