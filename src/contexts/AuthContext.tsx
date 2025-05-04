import React, { createContext, useContext, useState, useEffect, useCallback } from "react";
import { verifyTokenAndLogin, signOut, getCurrentUser } from "../services/AuthService";
import { User, UserRole } from "../types"; // Assuming User and UserRole types are defined here

import { CredentialResponse } from "@react-oauth/google"; // Import the type
const API_URL = process.env.REACT_APP_API_URL || "/api";

// Helper function for API calls (similar to BudgetContext)
const fetchAPI = async (url: string, options: RequestInit = {}, expectJson: boolean = true) => {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 20000); // 20s timeout

  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        ...(options.headers || {}),
      },
      signal: controller.signal,
    });

    clearTimeout(timeoutId);

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
  } catch (error: unknown) { // Specify error type as unknown
    clearTimeout(timeoutId);
    // Check if error is an instance of Error before accessing properties
    if (error instanceof Error && error.name === "AbortError") {
      throw new Error("การร้องขอข้อมูลใช้เวลานานเกินไป");
    }
    // Re-throw the original error or a generic one if it's not an Error instance
    throw error instanceof Error ? error : new Error("เกิดข้อผิดพลาดที่ไม่รู้จักใน fetchAPI");
  }
};

// Define authentication state type
interface AuthState {
  user: User | null;
  loading: boolean;
  error: string | null;
  allUsers: User[]; // Add state for all users
}

// Define context type
interface AuthContextType {
  authState: AuthState;
  login: (credentialResponse: any) => Promise<void>;
  logout: () => Promise<void>;
  checkAuthStatus: () => Promise<void>;
  fetchAllUsers: () => Promise<void>; // Function to fetch all users
  updateUserRole: (uid: string, role: UserRole) => Promise<void>; // Function to update role
  approveUser: (uid: string) => Promise<void>; // Function to approve user
  deleteUser: (uid: string) => Promise<void>; // Function to delete user
}

// Create authentication context
const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Create context provider
export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [authState, setAuthState] = useState<AuthState>({
    user: null,
    loading: true,
    error: null,
    allUsers: [], // Initialize allUsers
  });

  // Function to check authentication status
  const checkAuthStatus = useCallback(async () => {
    console.log("Checking auth status...");
    setAuthState((prev) => ({ ...prev, loading: true, error: null }));
    try {
      const user = getCurrentUser();
      console.log("User from localStorage:", user);
      setAuthState((prev) => ({ ...prev, user, loading: false, error: null }));
      // If user is admin, fetch all users
      if (user?.role === "admin") {
        await fetchAllUsersInternal();
      }
    } catch (error) {
      console.error("Error checking auth status:", error);
      localStorage.removeItem("user");
      setAuthState((prev) => ({
        ...prev,
        user: null,
        loading: false,
        error: error instanceof Error ? error.message : "เกิดข้อผิดพลาดในการตรวจสอบสถานะ",
        allUsers: [],
      }));
    } finally {
        // Ensure loading is set to false even if fetchAllUsersInternal fails
        setAuthState((prev) => ({ ...prev, loading: false }));
    }
  }, []);

  // Check login status when the app loads
  useEffect(() => {
    checkAuthStatus();
  }, [checkAuthStatus]);

  // Login function
  const login = async (credentialResponse: CredentialResponse) => {
    console.log("AuthContext: login called");
    setAuthState((prev) => ({ ...prev, loading: true, error: null }));
    try {
      const user = await verifyTokenAndLogin(credentialResponse);
      console.log("AuthContext: User verified:", user);
      setAuthState((prev) => ({ ...prev, user, loading: false, error: null }));
      // If the logged-in user is admin, fetch all users
      if (user?.role === "admin") {
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
      throw error;
    } finally {
        setAuthState((prev) => ({ ...prev, loading: false }));
    }
  };

  // Logout function
  const logout = async () => {
    console.log("AuthContext: logout called");
    setAuthState((prev) => ({ ...prev, loading: true }));
    try {
      await signOut();
      setAuthState({
        user: null,
        loading: false,
        error: null,
        allUsers: [], // Clear user list on logout
      });
      console.log("AuthContext: Logout successful");
    } catch (error) {
      console.error("Logout error in AuthContext:", error);
      setAuthState({
        user: null,
        loading: false,
        error: error instanceof Error ? error.message : "เกิดข้อผิดพลาดในการล็อกเอาท์",
        allUsers: [],
      });
    }
  };

  // --- User Management Functions --- //

  // Internal function to fetch users (used by checkAuthStatus and login)
  const fetchAllUsersInternal = async () => {
    console.log("Fetching all users...");
    try {
      const usersData = await fetchAPI(`${API_URL}/users`);
      setAuthState(prev => ({ ...prev, allUsers: Array.isArray(usersData) ? usersData : [] }));
      console.log("Fetched users:", usersData);
    } catch (err) {
      console.error("Error fetching all users:", err);
      setAuthState(prev => ({ ...prev, error: err instanceof Error ? err.message : "ไม่สามารถดึงข้อมูลผู้ใช้ทั้งหมดได้" }));
      // Don't clear allUsers here, maybe show previous list with error
    }
  };

  // Exposed function for components to trigger fetch
  const fetchAllUsers = useCallback(async () => {
    if (authState.user?.role !== "admin") {
        console.warn("Attempted to fetch all users without admin privileges.");
        return; // Or set an error
    }
    setAuthState(prev => ({ ...prev, loading: true }));
    await fetchAllUsersInternal();
    setAuthState(prev => ({ ...prev, loading: false }));
  }, [authState.user?.role]);

  const updateUserRole = async (uid: string, role: UserRole) => {
    if (authState.user?.role !== "admin") throw new Error("ไม่มีสิทธิ์เปลี่ยนบทบาทผู้ใช้");
    setAuthState(prev => ({ ...prev, loading: true }));
    try {
      const updatedUser = await fetchAPI(`${API_URL}/users/${uid}/role`, {
        method: "PUT",
        body: JSON.stringify({ role }),
      });
      setAuthState(prev => ({
        ...prev,
        allUsers: prev.allUsers.map(u => u.uid === uid ? updatedUser : u),
        error: null,
      }));
    } catch (err) {
      console.error("Error updating user role:", err);
      setAuthState(prev => ({ ...prev, error: err instanceof Error ? err.message : "เกิดข้อผิดพลาดในการอัปเดตบทบาท" }));
      throw err;
    } finally {
      setAuthState(prev => ({ ...prev, loading: false }));
    }
  };

  const approveUser = async (uid: string) => {
    if (authState.user?.role !== "admin") throw new Error("ไม่มีสิทธิ์อนุมัติผู้ใช้");
    setAuthState(prev => ({ ...prev, loading: true }));
    try {
      const updatedUser = await fetchAPI(`${API_URL}/users/${uid}/approve`, {
        method: "PUT",
        body: JSON.stringify({ approved: true }),
      });
      setAuthState(prev => ({
        ...prev,
        allUsers: prev.allUsers.map(u => u.uid === uid ? updatedUser : u),
        error: null,
      }));
    } catch (err) {
      console.error("Error approving user:", err);
      setAuthState(prev => ({ ...prev, error: err instanceof Error ? err.message : "เกิดข้อผิดพลาดในการอนุมัติผู้ใช้" }));
      throw err;
    } finally {
      setAuthState(prev => ({ ...prev, loading: false }));
    }
  };

  const deleteUser = async (uid: string) => {
    if (authState.user?.role !== "admin") throw new Error("ไม่มีสิทธิ์ลบผู้ใช้");
    setAuthState(prev => ({ ...prev, loading: true }));
    try {
      await fetchAPI(`${API_URL}/users/${uid}`, { method: "DELETE" }, false); // Expect no JSON response
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

  // Provide context value including user management functions
  return (
    <AuthContext.Provider value={{
      authState,
      login,
      logout,
      checkAuthStatus,
      fetchAllUsers,
      updateUserRole,
      approveUser,
      deleteUser
    }}>
      {children}
    </AuthContext.Provider>
  );
};

// Hook for using the context
export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
};

