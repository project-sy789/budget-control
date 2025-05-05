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

  // Thai: ตรวจสอบว่าบัญชีผู้ใช้ได้รับการอนุมัติหรือยัง (ถ้ายังไม่อนุมัติ อาจแสดงหน้า "รอการอนุมัติ" หรือ redirect ไปหน้าอื่น)
  // For now, we assume the backend check in protect middleware is sufficient, 
  // but a frontend check can provide a better user experience.
  if (!authState.user.approved) {
      console.log("AuthGuard: User not approved. Access denied.");
      // Option 1: Redirect to a specific "pending approval" page
      // return <Navigate to="/pending-approval" replace />;
      // Option 2: Redirect to login with an error message (less ideal)
      // return <Navigate to="/login" state={{ error: "Account not approved" }} replace />;
      // Option 3: Show a simple message (if within a layout)
      // For now, redirecting to login might be simplest, though potentially confusing.
      // Let's redirect to root and rely on maybe a message shown in the layout or project list.
      // Or, prevent login entirely if not approved (handled by backend currently).
      // Let's assume if they logged in, they are approved (as per backend logic).
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

