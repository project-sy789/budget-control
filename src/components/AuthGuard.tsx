import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

interface AuthGuardProps {
  requiredRole?: 'admin' | 'user' | 'any';
}

const AuthGuard: React.FC<AuthGuardProps> = ({ requiredRole = 'any' }) => {
  const { authState } = useAuth();
  const { user, loading } = authState;
  
  // While checking auth state or if there's an error, redirect to login immediately
  if (loading || authState.error) {
    // ถ้ามีการโหลดหรือมีข้อผิดพลาด ให้นำทางไปยังหน้าล็อกอินทันที
    // เพื่อป้องกันการค้างที่หน้า "กำลังตรวจสอบสิทธิ์..."
    return <Navigate to="/login" replace />;
  }
  
  // Not authenticated, redirect to login
  if (!user) {
    return <Navigate to="/login" replace />;
  }
  
  // Check role restrictions
  if (requiredRole === 'admin' && user.role !== 'admin') {
    return <Navigate to="/" replace />;
  }
  
  // If user is not approved
  if (!user.approved) {
    // Redirect to login or show a specific message page
    // For now, let's show a message similar to the pending role check
    // Consider creating a dedicated 'Pending Approval' page/component later
    return <div>บัญชีของคุณอยู่ระหว่างรอการอนุมัติ กรุณาติดต่อผู้ดูแลระบบ</div>;
    // Alternatively, redirect back to login: return <Navigate to="/login" replace />;
  }
  
  // User is authenticated and authorized
  return <Outlet />;
};

export default AuthGuard;