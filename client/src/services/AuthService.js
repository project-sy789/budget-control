// Service for handling authentication logic with the backend API

// Thai: เซอร์วิสสำหรับจัดการตรรกะการยืนยันตัวตนผ่าน Backend API
// - ส่ง Google token ไปยืนยันที่ backend
// - จัดการ JWT token ที่ได้รับจาก backend (บันทึก/ลบ)
// - ดึงข้อมูลผู้ใช้ปัจจุบันจาก JWT token ที่บันทึกไว้

import { jwtDecode } from "jwt-decode"; // Use jwt-decode to decode token payload

const API_URL = process.env.REACT_APP_API_URL || "/api";
const JWT_TOKEN_KEY = "authToken"; // Key for storing JWT in localStorage

// Verify Google token with backend and store JWT
// Thai: ส่ง Google credential response ไปยัง backend เพื่อยืนยัน และรับ JWT token กลับมาเก็บไว้
export const verifyTokenAndLogin = async (credentialResponse) => {
  console.log("AuthService: Verifying token with backend...");
  try {
    const response = await fetch(`${API_URL}/auth/verify-google`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ token: credentialResponse.credential }), // Send the ID token
    });

    const data = await response.json();

    if (!response.ok) {
      // Thai: หาก backend ตอบกลับมาว่าไม่สำเร็จ ให้โยน Error พร้อมข้อความจาก backend
      console.error("Backend verification failed:", data);
      throw new Error(data.message || `เกิดข้อผิดพลาด (${response.status})`);
    }

    // Thai: หากสำเร็จ บันทึก JWT token ที่ได้รับลงใน localStorage
    if (data.token) {
      localStorage.setItem(JWT_TOKEN_KEY, data.token);
      console.log("AuthService: JWT Token stored.");
      // Thai: ส่งข้อมูลผู้ใช้ที่ได้จาก backend กลับไป (อาจไม่จำเป็นถ้า AuthContext จัดการเอง)
      return data.user;
    } else {
      // Thai: กรณี backend ตอบกลับสำเร็จแต่ไม่มี token (ไม่ควรเกิดขึ้น)
      throw new Error("ไม่ได้รับ token จาก backend");
    }
  } catch (error) {
    console.error("Error during backend token verification:", error);
    // Thai: ลบ token เก่า (ถ้ามี) หากเกิดข้อผิดพลาด
    localStorage.removeItem(JWT_TOKEN_KEY);
    throw error; // Re-throw the error for AuthContext to handle
  }
};

// Sign out: Remove JWT token from localStorage
// Thai: ออกจากระบบ โดยการลบ JWT token ออกจาก localStorage
export const signOut = async () => {
  console.log("AuthService: Signing out...");
  localStorage.removeItem(JWT_TOKEN_KEY);
  // No backend call needed for simple JWT logout
};

// Get current user data from stored JWT token
// Thai: ดึงข้อมูลผู้ใช้ปัจจุบันจาก JWT token ที่เก็บไว้ใน localStorage
export const getCurrentUser = () => {
  try {
    const token = localStorage.getItem(JWT_TOKEN_KEY);
    if (!token) {
      return null; // No token found
    }

    // Decode the token to get user payload
    // Thai: ถอดรหัส token เพื่อเอาข้อมูล payload (uid, email, role, approved)
    const decodedToken = jwtDecode(token);

    // Optional: Check token expiration (jwt-decode doesn't verify)
    // You might want a library like `jsonwebtoken` on the client if needed,
    // but typically the backend handles expiration checks on API calls.
    // const isExpired = decodedToken.exp * 1000 < Date.now();
    // if (isExpired) {
    //   console.log("AuthService: Token expired.");
    //   localStorage.removeItem(JWT_TOKEN_KEY);
    //   return null;
    // }

    // Return the user data from the token payload
    // Thai: คืนค่าข้อมูลผู้ใช้ที่ได้จาก payload ของ token
    return {
      uid: decodedToken.uid,
      email: decodedToken.email,
      role: decodedToken.role,
      approved: decodedToken.approved,
      // Add other fields if they are included in the JWT payload
      // displayName and photoURL might come from the initial login response or a separate profile fetch
    };
  } catch (error) {
    console.error("Error decoding token or getting current user:", error);
    // If token is invalid, remove it
    localStorage.removeItem(JWT_TOKEN_KEY);
    return null;
  }
};

// Get the stored JWT token
// Thai: ดึง JWT token ที่เก็บไว้ใน localStorage (สำหรับใช้ใน header ของ API requests อื่นๆ)
export const getToken = () => {
  return localStorage.getItem(JWT_TOKEN_KEY);
};

