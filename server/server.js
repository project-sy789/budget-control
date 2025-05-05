// Main application file: server.js

require("dotenv").config(); // Load environment variables from .env file
const express = require("express");
const cors = require("cors");
const path = require("path");
const config = require("./config");
const errorHandler = require("./middleware/errorHandler"); // Import error handler middleware

// Import route handlers
// Thai: นำเข้าไฟล์กำหนดเส้นทางสำหรับแต่ละส่วนของ API
const authRoutes = require("./routes/auth");
const userRoutes = require("./routes/users");
const projectRoutes = require("./routes/projects");
const transactionRoutes = require("./routes/transactions");

const app = express();

// --- Middleware --- //

// Enable Cross-Origin Resource Sharing (CORS)
// Thai: เปิดใช้งาน CORS เพื่อให้ frontend (ที่อาจจะอยู่คนละ domain/port) สามารถเรียก API ได้
// Configure allowed origins in production for security
app.use(cors());

// Parse JSON request bodies
// Thai: ทำให้ Express สามารถอ่านข้อมูล JSON ที่ส่งมาใน request body ได้
app.use(express.json());

// --- API Routes --- //
// Thai: กำหนดเส้นทางหลักสำหรับ API แต่ละส่วน
app.use("/api/auth", authRoutes);
app.use("/api/users", userRoutes);
app.use("/api/projects", projectRoutes);
app.use("/api/transactions", transactionRoutes);

// --- Serve React Frontend --- //
// Thai: การตั้งค่าสำหรับให้บริการไฟล์ frontend (React build) ใน production mode
// In production, serve the static build files from the client directory
if (process.env.NODE_ENV === "production") {
  // Thai: กำหนดให้ Express ให้บริการไฟล์ static จากโฟลเดอร์ build ของ client
  app.use(express.static(path.join(__dirname, "../client/build")));

  // For any request that doesn't match an API route or static file, serve index.html
  // Thai: สำหรับ request ใดๆ ที่ไม่ตรงกับ API route หรือไฟล์ static ให้ส่ง index.html กลับไป (เพื่อให้ React Router จัดการ)
  app.get("*", (req, res) => {
    res.sendFile(path.resolve(__dirname, "..", "client", "build", "index.html"));
  });
} else {
  // In development, you might just run the backend and frontend separately
  // Thai: ใน development mode, แสดงข้อความว่า API กำลังทำงาน
  app.get("/", (req, res) => {
    res.send("Budget Control API is running in development mode.");
  });
}

// --- Error Handling Middleware --- //
// Thai: ใช้ error handling middleware ที่สร้างไว้ (ต้องอยู่หลังสุด)
// This should be the last middleware added
app.use(errorHandler);

// --- Start Server --- //
const PORT = config.port;
app.listen(PORT, () => {
  console.log(`Server listening on port ${PORT}`);
  // Log database connection status (already logged in db.js)
});

// Optional: Handle unhandled promise rejections
// Thai: จัดการกับ Promise rejections ที่ไม่ถูกดักจับ (เช่น ลืม .catch() ใน async function)
process.on("unhandledRejection", (err, promise) => {
  console.error(`Unhandled Rejection: ${err.message}`, err);
  // Close server & exit process (optional, consider using a process manager)
  // server.close(() => process.exit(1));
});

