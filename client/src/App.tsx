// Main application component

// Thai: คอมโพเนนต์หลักของแอปพลิเคชัน (App.tsx)
// - ตั้งค่า Theme, Localization, และ Context Providers (Auth, Budget)
// - กำหนดโครงสร้างการ Routing หลักของแอปพลิเคชัน
// - ใช้ AuthGuard เพื่อป้องกันเส้นทางที่ต้องการการยืนยันตัวตนและสิทธิ์การเข้าถึง

import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import thLocale from 'date-fns/locale/th'; // Thai locale for date pickers
import { GoogleOAuthProvider } from '@react-oauth/google'; // Import Google OAuth Provider

import theme from './theme';
import { AuthProvider } from './contexts/AuthContext';
import { BudgetProvider } from './contexts/BudgetContext';
import AuthGuard from './components/AuthGuard'; // Ensure AuthGuard uses the new AuthContext
import Layout from './components/Layout'; // Ensure Layout uses the new AuthContext
import Login from './components/Login'; // Ensure Login uses the new AuthContext
import ProjectManagement from './components/ProjectManagement'; // Needs update for BudgetContext
import BudgetControl from './components/BudgetControl'; // Needs update for BudgetContext (pagination)
import BudgetSummary from './components/BudgetSummary'; // Needs update for BudgetContext
import UserManagement from './components/UserManagement'; // Needs update for AuthContext (admin functions)

import './App.css';

// Thai: ดึง Google Client ID จาก environment variable
const googleClientId = process.env.REACT_APP_GOOGLE_CLIENT_ID;

if (!googleClientId) {
  console.error("Fatal Error: REACT_APP_GOOGLE_CLIENT_ID is not defined.");
  // Optionally render an error message or prevent app load
}

const App: React.FC = () => {
  return (
    <ThemeProvider theme={theme}>
      <LocalizationProvider dateAdapter={AdapterDateFns} adapterLocale={thLocale}>
        <CssBaseline />
        {/* Thai: ใช้ GoogleOAuthProvider ห่อหุ้มแอปพลิเคชัน และใส่ Client ID */} 
        <GoogleOAuthProvider clientId={googleClientId || ""}> 
          {/* Thai: ใช้ AuthProvider เพื่อจัดการสถานะการล็อกอินและข้อมูลผู้ใช้ */} 
          <AuthProvider>
            {/* Thai: ใช้ BudgetProvider เพื่อจัดการข้อมูลโครงการและธุรกรรม */} 
            <BudgetProvider>
              <Router>
                <Routes>
                  {/* Thai: เส้นทางสำหรับหน้าล็อกอิน */} 
                  <Route path="/login" element={<Login />} />
                  
                  {/* Thai: เส้นทางที่ต้องการการล็อกอิน (ทุก role) */} 
                  <Route element={<AuthGuard requiredRole="any" />}>
                    {/* Thai: ใช้ Layout เป็นโครงสร้างหน้า */} 
                    <Route path="/" element={<Layout><ProjectManagement /></Layout>} />
                    <Route path="/projects" element={<Layout><ProjectManagement /></Layout>} />
                    <Route path="/budget-control" element={<Layout><BudgetControl /></Layout>} />
                    <Route path="/budget-summary" element={<Layout><BudgetSummary /></Layout>} />
                  </Route>
                  
                  {/* Thai: เส้นทางที่ต้องการสิทธิ์ Admin */} 
                  <Route element={<AuthGuard requiredRole="admin" />}>
                    <Route path="/user-management" element={<Layout><UserManagement /></Layout>} />
                  </Route>
                  
                  {/* Thai: หากไม่พบเส้นทาง ให้ redirect ไปหน้าหลัก */} 
                  <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
              </Router>
            </BudgetProvider>
          </AuthProvider>
        </GoogleOAuthProvider>
      </LocalizationProvider>
    </ThemeProvider>
  );
};

export default App;

