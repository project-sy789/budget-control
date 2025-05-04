import { googleLogout } from "@react-oauth/google";

// URL ของ API server (ใช้ environment variable ถ้ามี, หรือ default เป็น localhost)
const API_URL = process.env.REACT_APP_API_URL || "/api"; // เปลี่ยนเป็น relative path หรือใช้ proxy

/**
 * บริการจัดการการยืนยันตัวตนด้วย Google OAuth และ Backend API
 */

// ฟังก์ชันสำหรับส่ง token ไปตรวจสอบที่ backend
export const verifyTokenAndLogin = async (credentialResponse) => {
  if (!credentialResponse || !credentialResponse.credential) {
    throw new Error("ไม่ได้รับข้อมูล credential จาก Google");
  }

  const token = credentialResponse.credential;

  try {
    const response = await fetch(`${API_URL}/auth/verify-token`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ token }),
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || `การยืนยัน Token ล้มเหลว (สถานะ: ${response.status})`);
    }

    // บันทึกข้อมูลผู้ใช้ลงใน localStorage
    localStorage.setItem("user", JSON.stringify(data.user));
    console.log("User data saved to localStorage:", data.user);

    return data.user;
  } catch (error) {
    console.error("เกิดข้อผิดพลาดในการตรวจสอบ token กับ backend:", error);
    // ลบข้อมูลผู้ใช้เก่า ถ้ามี
    localStorage.removeItem("user");
    throw error; // ส่ง error ต่อไปให้ AuthContext จัดการ
  }
};

// ฟังก์ชันสำหรับการล็อกเอาท์
export const signOut = async () => {
  try {
    // ลบข้อมูลผู้ใช้จาก localStorage
    localStorage.removeItem("user");
    console.log("User data removed from localStorage.");

    // เรียกใช้ googleLogout จาก @react-oauth/google
    googleLogout();
    console.log("Logged out from Google.");

    return true;
  } catch (error) {
    console.error("เกิดข้อผิดพลาดในการล็อกเอาท์:", error);
    // แม้จะเกิดข้อผิดพลาดในการ logout จาก Google ก็ควรจะลบ local storage อยู่ดี
    localStorage.removeItem("user");
    throw error;
  }
};

// ฟังก์ชันสำหรับดึงข้อมูลผู้ใช้ปัจจุบันจาก localStorage
export const getCurrentUser = () => {
  try {
    const userString = localStorage.getItem("user");
    if (!userString) {
      return null;
    }
    const user = JSON.parse(userString);
    // ตรวจสอบโครงสร้างข้อมูล user พื้นฐาน
    if (user && user.uid && user.email) {
      return user;
    } else {
      console.warn("Invalid user data found in localStorage, removing it.");
      localStorage.removeItem("user");
      return null;
    }
  } catch (error) {
    console.error("เกิดข้อผิดพลาดในการอ่านข้อมูลผู้ใช้จาก localStorage:", error);
    // หาก parse ไม่ได้ ให้ลบข้อมูลที่อาจเสียหายทิ้ง
    localStorage.removeItem("user");
    return null;
  }
};

// ฟังก์ชันสำหรับตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
export const isAuthenticated = () => {
  return getCurrentUser() !== null;
};

