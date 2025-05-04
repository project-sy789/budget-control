import React, { useState, useEffect, useRef } from "react";
import {
  Container,
  Box,
  Typography,
  Button,
  CircularProgress,
  Alert,
  Card,
  CardContent,
  Link,
} from "@mui/material";
import { GoogleOAuthProvider, GoogleLogin, CredentialResponse } from "@react-oauth/google";
import { useAuth } from "../contexts/AuthContext";
import { useNavigate } from "react-router-dom";

// Retrieve Client ID from environment variable or use the one provided
const GOOGLE_CLIENT_ID = process.env.REACT_APP_GOOGLE_CLIENT_ID || "1068541751492-j1g5a8np8shnd4tnfkmp2bnpfdolam0m.apps.googleusercontent.com";

const Login: React.FC = () => {
  const { authState, login } = useAuth();
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const navigate = useNavigate();
  const isMounted = useRef(true);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      isMounted.current = false;
    };
  }, []);

  // Redirect if already logged in and approved
  useEffect(() => {
    if (authState.user && authState.user.approved) {
      console.log("User approved, navigating to home.");
      navigate("/");
    }
  }, [authState.user, navigate]);

  // Handle successful Google Sign-In
  const handleGoogleSuccess = async (credentialResponse: CredentialResponse) => {
    console.log("Google Sign-In Success:", credentialResponse);
    setErrorMessage(null); // Clear previous errors
    try {
      await login(credentialResponse); // Call login from AuthContext
      // Navigation or state update is handled by AuthContext/useEffect
    } catch (error) {
      console.error("Error during login process:", error);
      if (isMounted.current) {
        setErrorMessage(
          error instanceof Error ? error.message : "เกิดข้อผิดพลาดในการยืนยันตัวตนกับเซิร์ฟเวอร์"
        );
      }
    }
  };

  // Handle Google Sign-In failure
  const handleGoogleError = () => {
    console.error("Google Sign-In Error");
    if (isMounted.current) {
      setErrorMessage("การเข้าสู่ระบบด้วย Google ล้มเหลว กรุณาลองใหม่อีกครั้ง");
    }
  };

  // Show loading indicator from context or login button
  if (authState.loading) {
    return (
      <Container maxWidth="sm" sx={{ mt: 8, textAlign: "center" }}>
        <CircularProgress />
        <Typography sx={{ mt: 2 }}>กำลังตรวจสอบสถานะ...</Typography>
      </Container>
    );
  }

  return (
    <Container maxWidth="sm" sx={{ mt: 8 }}>
      <Card elevation={3} sx={{ borderRadius: 3, overflow: "hidden" }}>
        <CardContent sx={{ p: 4 }}>
          <Box sx={{ textAlign: "center", mb: 4 }}>
            <Typography variant="h4" component="h1" gutterBottom>
              ระบบควบคุมงบประมาณโครงการ
            </Typography>
            <Typography variant="subtitle1" color="text.secondary">
              โรงเรียนซับใหญ่วิทยาคม
            </Typography>
            {!navigator.onLine && (
              <Alert severity="warning" sx={{ mt: 2, width: "100%" }}>
                ไม่พบการเชื่อมต่ออินเทอร์เน็ต กรุณาตรวจสอบการเชื่อมต่อของคุณ
              </Alert>
            )}
          </Box>

          {/* Display general auth errors from context */}
          {authState.error && (
            <Alert severity="error" sx={{ mb: 3 }}>
              {authState.error}
            </Alert>
          )}

          {/* Display specific errors from login attempt */}
          {errorMessage && !authState.error && (
            <Alert severity="error" sx={{ mb: 3 }}>
              {errorMessage}
            </Alert>
          )}

          {/* Display pending approval message */}
          {authState.user && !authState.user.approved && (
            <Alert severity="warning" sx={{ mb: 3 }}>
              บัญชีของคุณ ({authState.user.email}) ยังไม่ได้รับการอนุมัติ กรุณาติดต่อผู้ดูแลระบบ
              <br />
              <Link href="mailto:nutrawee@subyaischool.ac.th">
                nutrawee@subyaischool.ac.th
              </Link>
            </Alert>
          )}

          {/* Login Button - Show only if not logged in or pending approval */}
          {(!authState.user || !authState.user.approved) && (
            <Box sx={{ display: "flex", justifyContent: "center" }}>
              <GoogleOAuthProvider clientId={GOOGLE_CLIENT_ID}>
                <GoogleLogin
                  onSuccess={handleGoogleSuccess}
                  onError={handleGoogleError}
                  useOneTap={false} // Can enable one-tap later if desired
                  shape="rectangular"
                  theme="outline"
                  size="large"
                  text="signin_with"
                  logo_alignment="left"
                />
              </GoogleOAuthProvider>
            </Box>
          )}
        </CardContent>
      </Card>
    </Container>
  );
};

export default Login;

