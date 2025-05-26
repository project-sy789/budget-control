// Component for protecting routes based on authentication status and user role

// Thai: คอมโพเนนต์สำหรับป้องกันเส้นทาง (Route Guard)
// - ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
// - ตรวจสอบว่าผู้ใช้มีสิทธิ์ (role) ตามที่กำหนดสำหรับเส้นทางนั้นๆ หรือไม่
// - ถ้ายังไม่ได้ล็อกอิน หรือไม่มีสิทธิ์ ให้ redirect ไปยังหน้า Login หรือหน้าหลัก

import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext'; // Import the hook for AuthContext
import { CircularProgress, Box } from '@mui/material';

interface AuthGuardProps {
  requiredRole: 'admin' | 'user' | 'any'; // Define expected roles: admin, user, or any logged-in user
}

const AuthGuard: React.FC<AuthGuardProps> = ({ requiredRole }) => {
  const { authState } = useAuth(); // Get authentication state from context
  const location = useLocation(); // Get current location to redirect back after login

  // Thai: แสดง Loading spinner ขณะกำลังตรวจสอบสถานะการล็อกอิน
  if (authState.loading) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="100vh">
        <CircularProgress />
      </Box>
    );
  }

  // Thai: ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
  if (!authState.user) {
    // User not logged in
    console.log("AuthGuard: User not logged in. Redirecting to login.");
    // Thai: ถ้ายังไม่ได้ล็อกอิน ให้ redirect ไปหน้า Login พร้อมจำ state เดิมไว้เผื่อ redirect กลับมา
    return <Navigate to="/login" state={{ from: location }} replace />;
  }
  // Thai: ตรวจสอบว่าบัญชีผู้ใช้ได้รับการอนุมัติหรือยัง
  // If user exists but is not approved, redirect to login with a message.
  else if (authState.user && authState.user.approved === false) {
    console.log("AuthGuard: Unapproved user attempting to access route. Redirecting to login.");
    return <Navigate to="/login" state={{ from: location, error: "Your account is pending approval by an administrator." }} replace />;
  }

  // Thai: ตรวจสอบ Role ของผู้ใช้
  if (requiredRole === 'admin' && authState.user.role !== 'admin') {
    // User is logged in but does not have the required admin role
    console.log("AuthGuard: Admin role required. Access denied.");
    // Thai: ถ้าต้องการสิทธิ์ Admin แต่ผู้ใช้ไม่ใช่ Admin ให้ redirect ไปหน้าหลัก
    return <Navigate to="/" replace />; // Redirect to home or an unauthorized page
  }

  // Thai: ถ้าผู้ใช้ล็อกอินและมีสิทธิ์ถูกต้อง ให้แสดงคอมโพเนนต์ลูก (Outlet)
  // User is logged in and has the required role (or 'any' role is required)
  return <Outlet />; // Render the child route components
};

export default AuthGuard;

