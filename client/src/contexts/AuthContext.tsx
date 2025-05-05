// Context for managing authentication state and user data

// Thai: Context สำหรับจัดการสถานะการยืนยันตัวตน (authentication state) และข้อมูลผู้ใช้
// - เก็บข้อมูลผู้ใช้ที่ล็อกอินอยู่ (user)
// - จัดการสถานะ loading และ error
// - มีฟังก์ชันสำหรับ login, logout, ตรวจสอบสถานะ
// - (สำหรับ Admin) จัดการข้อมูลผู้ใช้ทั้งหมด (allUsers) และฟังก์ชันจัดการผู้ใช้ (update role, approve, delete)

import React, { createContext, useContext, useState, useEffect, useCallback } from "react";
import {
  verifyTokenAndLogin,
  signOut,
  getCurrentUser,
  getToken, // Import getToken
} from "../services/AuthService";
import { User, UserRole } from "../types"; // Assuming User and UserRole types are defined here
import { CredentialResponse } from "@react-oauth/google";

const API_URL = process.env.REACT_APP_API_URL || "/api";

// Helper function for API calls, now includes Authorization header
// Thai: ฟังก์ชันช่วยสำหรับการเรียก API, เพิ่มการใส่ Authorization header พร้อม JWT token โดยอัตโนมัติ
const fetchAPI = async (url: string, options: RequestInit = {}, expectJson: boolean = true) => {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 20000); // 20s timeout

  const token = getToken(); // Get JWT token from AuthService
  const headers = {
    "Content-Type": "application/json",
    ...(options.headers || {}),
  };
  // Thai: ถ้ามี token ให้เพิ่ม Authorization header
  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  try {
    const response = await fetch(url, {
      ...options,
      headers: headers, // Use the combined headers
      signal: controller.signal,
    });

    clearTimeout(timeoutId);

    // Thai: หาก response status เป็น 401 (Unauthorized) อาจหมายถึง token หมดอายุ ให้ logout
    if (response.status === 401) {
        console.warn("API request returned 401 Unauthorized. Logging out.");
        // Trigger logout (this might need a way to call the logout function from AuthContext)
        // For now, just remove the token and throw an error
        localStorage.removeItem("authToken"); // Use the correct key
        throw new Error("Session หมดอายุหรือ Token ไม่ถูกต้อง, กรุณาล็อกอินใหม่");
    }

    if (!response.ok) {
      let errorMessage = `API Error: ${response.status} ${response.statusText}`;
      try {
        const errorData = await response.json();
        errorMessage = errorData.message || errorMessage;
      } catch (e) { /* Ignore if response is not JSON */ }
      throw new Error(errorMessage);
    }

    if (!expectJson || response.status === 204) {
      return null; // Handle No Content or non-JSON responses
    }

    return await response.json();
  } catch (error: unknown) {
    clearTimeout(timeoutId);
    if (error instanceof Error && error.name === "AbortError") {
      throw new Error("การร้องขอข้อมูลใช้เวลานานเกินไป");
    }
    throw error instanceof Error ? error : new Error("เกิดข้อผิดพลาดที่ไม่รู้จักใน fetchAPI");
  }
};

// Define authentication state type
interface AuthState {
  user: User | null;
  loading: boolean;
  error: string | null;
  allUsers: User[];
}

// Define context type
interface AuthContextType {
  authState: AuthState;
  login: (credentialResponse: CredentialResponse) => Promise<void>; // Updated type
  logout: () => Promise<void>;
  checkAuthStatus: () => Promise<void>;
  fetchAllUsers: () => Promise<void>;
  updateUserRole: (uid: string, role: UserRole) => Promise<void>;
  approveUser: (uid: string, approved: boolean) => Promise<void>; // Added approved parameter
  deleteUser: (uid: string) => Promise<void>;
  api: (url: string, options?: RequestInit, expectJson?: boolean) => Promise<any>; // Expose fetchAPI
}

// Create authentication context
const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Create context provider
export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [authState, setAuthState] = useState<AuthState>({
    user: null,
    loading: true,
    error: null,
    allUsers: [],
  });

  // Internal function to fetch users (used by checkAuthStatus and login)
  // Thai: ฟังก์ชันภายในสำหรับดึงข้อมูลผู้ใช้ทั้งหมด (เรียกใช้โดย checkAuthStatus และ login)
  const fetchAllUsersInternal = useCallback(async () => {
    console.log("Fetching all users...");
    try {
      // Thai: ใช้ fetchAPI ที่มีการใส่ token อัตโนมัติ
      const response = await fetchAPI(`${API_URL}/users`);
      // The backend returns { success: true, count: ..., data: [...] }
      if (response && response.success && Array.isArray(response.data)) {
          setAuthState(prev => ({ ...prev, allUsers: response.data }));
          console.log("Fetched users:", response.data);
      } else {
          throw new Error("รูปแบบข้อมูลผู้ใช้ที่ได้รับไม่ถูกต้อง");
      }
    } catch (err) {
      console.error("Error fetching all users:", err);
      setAuthState(prev => ({ ...prev, error: err instanceof Error ? err.message : "ไม่สามารถดึงข้อมูลผู้ใช้ทั้งหมดได้" }));
    }
  }, []);

  // Function to check authentication status using stored JWT
  // Thai: ฟังก์ชันตรวจสอบสถานะการล็อกอิน โดยดูจาก JWT token ที่เก็บไว้
  const checkAuthStatus = useCallback(async () => {
    console.log("Checking auth status...");
    setAuthState((prev) => ({ ...prev, loading: true, error: null }));
    try {
      const userFromToken = getCurrentUser(); // Get user data from decoded token
      console.log("User from stored token:", userFromToken);
      if (userFromToken) {
          // Optionally, verify token validity with a lightweight backend endpoint
          // For now, assume token is valid if it exists and decodes
          setAuthState((prev) => ({ ...prev, user: userFromToken, loading: false, error: null }));
          if (userFromToken.role === "admin") {
            await fetchAllUsersInternal();
          }
      } else {
          // No valid token found
          setAuthState((prev) => ({ ...prev, user: null, loading: false, error: null, allUsers: [] }));
      }
    } catch (error) {
      console.error("Error checking auth status:", error);
      localStorage.removeItem("authToken"); // Use correct key
      setAuthState((prev) => ({
        ...prev,
        user: null,
        loading: false,
        error: error instanceof Error ? error.message : "เกิดข้อผิดพลาดในการตรวจสอบสถานะ",
        allUsers: [],
      }));
    } finally {
        setAuthState((prev) => ({ ...prev, loading: false }));
    }
  }, [fetchAllUsersInternal]);

  // Check login status when the app loads
  useEffect(() => {
    checkAuthStatus();
  }, [checkAuthStatus]);

  // Login function using Google CredentialResponse
  // Thai: ฟังก์ชันล็อกอิน รับ Google credential, ส่งไปยืนยันที่ backend, และอัปเดต state
  const login = async (credentialResponse: CredentialResponse) => {
    console.log("AuthContext: login called");
    setAuthState((prev) => ({ ...prev, loading: true, error: null }));
    try {
      // Thai: เรียก AuthService เพื่อยืนยัน token กับ backend และรับข้อมูล user กลับมา
      const loggedInUser = await verifyTokenAndLogin(credentialResponse);
      console.log("AuthContext: User verified by backend:", loggedInUser);
      setAuthState((prev) => ({ ...prev, user: loggedInUser, loading: false, error: null }));
      if (loggedInUser?.role === "admin") {
        await fetchAllUsersInternal();
      }
    } catch (error) {
      console.error("Login error in AuthContext:", error);
      setAuthState((prev) => ({
        ...prev,
        user: null,
        loading: false,
        error: error instanceof Error ? error.message : "เกิดข้อผิดพลาดในการล็อกอิน",
        allUsers: [],
      }));
      throw error; // Re-throw for the component to handle (e.g., show error message)
    } finally {
        setAuthState((prev) => ({ ...prev, loading: false }));
    }
  };

  // Logout function
  // Thai: ฟังก์ชันออกจากระบบ ลบ token และเคลียร์ state
  const logout = async () => {
    console.log("AuthContext: logout called");
    setAuthState((prev) => ({ ...prev, loading: true }));
    try {
      await signOut(); // Remove token from localStorage via AuthService
      setAuthState({
        user: null,
        loading: false,
        error: null,
        allUsers: [],
      });
      console.log("AuthContext: Logout successful");
    } catch (error) {
      console.error("Logout error in AuthContext:", error);
      // Even if signOut fails locally, clear the state
      setAuthState({
        user: null,
        loading: false,
        error: error instanceof Error ? error.message : "เกิดข้อผิดพลาดในการล็อกเอาท์",
        allUsers: [],
      });
    }
  };

  // --- User Management Functions (Admin) --- //

  // Exposed function for components to trigger fetch all users
  // Thai: ฟังก์ชันสำหรับให้ component เรียกเพื่อดึงข้อมูลผู้ใช้ทั้งหมด (สำหรับ Admin)
  const fetchAllUsers = useCallback(async () => {
    if (authState.user?.role !== "admin") {
        console.warn("Attempted to fetch all users without admin privileges.");
        setAuthState(prev => ({ ...prev, error: "ไม่มีสิทธิ์ดึงข้อมูลผู้ใช้" }));
        return;
    }
    setAuthState(prev => ({ ...prev, loading: true }));
    await fetchAllUsersInternal();
    setAuthState(prev => ({ ...prev, loading: false }));
  }, [authState.user?.role, fetchAllUsersInternal]);

  // Update user role
  // Thai: ฟังก์ชันอัปเดตบทบาทผู้ใช้ (สำหรับ Admin)
  const updateUserRole = async (uid: string, role: UserRole) => {
    if (authState.user?.role !== "admin") throw new Error("ไม่มีสิทธิ์เปลี่ยนบทบาทผู้ใช้");
    setAuthState(prev => ({ ...prev, loading: true }));
    try {
      // Thai: เรียก API endpoint สำหรับอัปเดต role
      const response = await fetchAPI(`${API_URL}/users/${uid}/role`, {
        method: "PUT",
        body: JSON.stringify({ role }),
      });
      // The backend returns { success: true, data: updatedUser }
      if (response && response.success && response.data) {
          setAuthState(prev => ({
            ...prev,
            allUsers: prev.allUsers.map(u => u.uid === uid ? response.data : u),
            error: null,
          }));
      } else {
          throw new Error("การอัปเดตบทบาทล้มเหลว หรือไม่ได้รับข้อมูลที่ถูกต้องกลับมา");
      }
    } catch (err) {
      console.error("Error updating user role:", err);
      setAuthState(prev => ({ ...prev, error: err instanceof Error ? err.message : "เกิดข้อผิดพลาดในการอัปเดตบทบาท" }));
      throw err;
    } finally {
      setAuthState(prev => ({ ...prev, loading: false }));
    }
  };

  // Approve or Unapprove user
  // Thai: ฟังก์ชันอนุมัติหรือยกเลิกการอนุมัติผู้ใช้ (สำหรับ Admin)
  const approveUser = async (uid: string, approved: boolean) => {
    if (authState.user?.role !== "admin") throw new Error("ไม่มีสิทธิ์อนุมัติผู้ใช้");
    setAuthState(prev => ({ ...prev, loading: true }));
    try {
      // Thai: เรียก API endpoint สำหรับอัปเดตสถานะ approved
      const response = await fetchAPI(`${API_URL}/users/${uid}/approve`, {
        method: "PUT",
        body: JSON.stringify({ approved }), // Send boolean status
      });
      // The backend returns { success: true, data: updatedUser }
      if (response && response.success && response.data) {
          setAuthState(prev => ({
            ...prev,
            allUsers: prev.allUsers.map(u => u.uid === uid ? response.data : u),
            error: null,
          }));
      } else {
          throw new Error("การอัปเดตสถานะการอนุมัติล้มเหลว หรือไม่ได้รับข้อมูลที่ถูกต้องกลับมา");
      }
    } catch (err) {
      console.error("Error updating user approval:", err);
      setAuthState(prev => ({ ...prev, error: err instanceof Error ? err.message : "เกิดข้อผิดพลาดในการอัปเดตสถานะการอนุมัติ" }));
      throw err;
    } finally {
      setAuthState(prev => ({ ...prev, loading: false }));
    }
  };

  // Delete user
  // Thai: ฟังก์ชันลบผู้ใช้ (สำหรับ Admin)
  const deleteUser = async (uid: string) => {
    if (authState.user?.role !== "admin") throw new Error("ไม่มีสิทธิ์ลบผู้ใช้");
    setAuthState(prev => ({ ...prev, loading: true }));
    try {
      // Thai: เรียก API endpoint สำหรับลบผู้ใช้ (expecting 204 No Content)
      await fetchAPI(`${API_URL}/users/${uid}`, { method: "DELETE" }, false);
      setAuthState(prev => ({
        ...prev,
        allUsers: prev.allUsers.filter(u => u.uid !== uid),
        error: null,
      }));
    } catch (err) {
      console.error("Error deleting user:", err);
      setAuthState(prev => ({ ...prev, error: err instanceof Error ? err.message : "เกิดข้อผิดพลาดในการลบผู้ใช้" }));
      throw err;
    } finally {
      setAuthState(prev => ({ ...prev, loading: false }));
    }
  };

  // Provide context value including user management functions and fetchAPI
  return (
    <AuthContext.Provider value={{
      authState,
      login,
      logout,
      checkAuthStatus,
      fetchAllUsers,
      updateUserRole,
      approveUser,
      deleteUser,
      api: fetchAPI // Expose the fetchAPI function
    }}>
      {children}
    </AuthContext.Provider>
  );
};

// Hook for using the context
// Thai: Hook สำหรับเรียกใช้ AuthContext ใน components ต่างๆ
export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
};

