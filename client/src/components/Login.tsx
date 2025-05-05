// Component for handling user login via Google OAuth

// Thai: คอมโพเนนต์สำหรับหน้าล็อกอิน
// - แสดงปุ่ม Google Sign-In
// - เรียกใช้ฟังก์ชัน login จาก AuthContext เมื่อล็อกอินสำเร็จ
// - แสดงข้อความ loading หรือ error
// - Redirect ผู้ใช้ไปยังหน้าหลักเมื่อล็อกอินสำเร็จ

import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { GoogleLogin, CredentialResponse } from '@react-oauth/google'; // Import GoogleLogin and CredentialResponse
import { useAuth } from '../contexts/AuthContext'; // Import the hook to use AuthContext
import { Container, Box, Typography, CircularProgress, Alert } from '@mui/material';

const Login: React.FC = () => {
  const navigate = useNavigate();
  const { authState, login } = useAuth(); // Get authState and login function from context

  // Thai: ตรวจสอบสถานะการล็อกอิน ถ้าล็อกอินแล้ว ให้ redirect ไปหน้าหลัก
  useEffect(() => {
    if (authState.user) {
      console.log("User already logged in, redirecting...");
      navigate('/'); // Redirect to home page if already logged in
    }
  }, [authState.user, navigate]);

  // Thai: ฟังก์ชันที่จะถูกเรียกเมื่อ Google Sign-In สำเร็จ
  const handleLoginSuccess = async (credentialResponse: CredentialResponse) => {
    console.log("Google Login Success:", credentialResponse);
    try {
      // Thai: เรียกฟังก์ชัน login จาก AuthContext เพื่อส่ง token ไปยืนยันที่ backend
      await login(credentialResponse);
      console.log("Backend verification successful, navigating...");
      // Navigation is handled by the useEffect above, or could be done here
      // navigate('/');
    } catch (error) {
      // Error is already set in authState by the login function
      console.error("Login failed after Google success:", error);
      // UI will show the error from authState.error
    }
  };

  // Thai: ฟังก์ชันที่จะถูกเรียกเมื่อ Google Sign-In ล้มเหลว
  const handleLoginFailure = () => {
    console.error("Google Login Failed");
    // Optionally set a local error state or rely on AuthContext error if applicable
    // For now, just log it, as AuthContext won't be involved yet.
  };

  return (
    <Container component="main" maxWidth="xs">
      <Box
        sx={{
          marginTop: 8,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
        }}
      >
        <Typography component="h1" variant="h5">
          เข้าสู่ระบบ
        </Typography>
        <Box sx={{ mt: 3, mb: 2 }}>
          {/* Thai: แสดงข้อความ loading ขณะกำลังล็อกอิน */} 
          {authState.loading ? (
            <CircularProgress />
          ) : (
            <> 
              {/* Thai: แสดงปุ่ม Google Sign-In */} 
              <GoogleLogin
                onSuccess={handleLoginSuccess}
                onError={handleLoginFailure}
                useOneTap // Optional: Enable One Tap login
              />
              {/* Thai: แสดงข้อความ error หากการล็อกอิน (ฝั่ง backend) ล้มเหลว */} 
              {authState.error && (
                <Alert severity="error" sx={{ mt: 2 }}>
                  {authState.error}
                </Alert>
              )}
            </>
          )}
        </Box>
      </Box>
    </Container>
  );
};

export default Login;

