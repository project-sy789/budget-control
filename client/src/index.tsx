import React, { Component, ErrorInfo, ReactNode } from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App';
import reportWebVitals from './reportWebVitals';
import { AuthProvider } from './contexts/AuthContext';

// Error Boundary component to catch Firebase connection errors
class ErrorBoundary extends Component<{children: ReactNode}, {hasError: boolean, errorInfo: string}> {
  constructor(props: {children: ReactNode}) {
    super(props);
    this.state = { hasError: false, errorInfo: '' };
  }

  static getDerivedStateFromError(_: Error) {
    // Update state so the next render will show the fallback UI
    return { hasError: true };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log the error to console
    console.error('Application Error:', error);
    console.error('Error Info:', errorInfo);
    
    // Check if error is related to Firebase
    const isFirebaseError = error.message.includes('firebase') || 
                            error.message.includes('firestore') ||
                            error.message.includes('WebChannel') ||
                            error.message.includes('400');
    
    // Update state with error information
    this.setState({
      hasError: true,
      errorInfo: isFirebaseError 
        ? 'เกิดข้อผิดพลาดในการเชื่อมต่อกับ Firebase กรุณาลองใหม่อีกครั้ง'
        : 'เกิดข้อผิดพลาดในแอปพลิเคชัน กรุณาลองใหม่อีกครั้ง'
    });
    
    // Auto reload for Firebase connection errors after 5 seconds
    if (isFirebaseError && navigator.onLine) {
      console.log('Firebase connection error detected. Will reload in 5 seconds...');
      setTimeout(() => {
        window.location.reload();
      }, 5000);
    }
  }

  render() {
    if (this.state.hasError) {
      // Fallback UI when an error occurs
      return (
        <div style={{ 
          padding: '20px', 
          textAlign: 'center', 
          fontFamily: 'sans-serif',
          marginTop: '50px' 
        }}>
          <h2>ขออภัย เกิดข้อผิดพลาดขึ้น</h2>
          <p>{this.state.errorInfo}</p>
          <p>กำลังพยายามเชื่อมต่อใหม่อัตโนมัติ...</p>
          <button 
            onClick={() => window.location.reload()} 
            style={{
              padding: '10px 15px',
              backgroundColor: '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer',
              marginTop: '15px'
            }}
          >
            โหลดใหม่
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}

const root = ReactDOM.createRoot(
  document.getElementById('root') as HTMLElement
);

root.render(
  <React.StrictMode>
    <ErrorBoundary>
      <AuthProvider>
        <App />
      </AuthProvider>
    </ErrorBoundary>
  </React.StrictMode>
);

// If you want to start measuring performance in your app, pass a function
// to log results (for example: reportWebVitals(console.log))
// or send to an analytics endpoint. Learn more: https://bit.ly/CRA-vitals
reportWebVitals();
