import React, { createContext, useContext, useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import thLocale from 'date-fns/locale/th';
import theme from './theme';

// Components
import Layout from './components/Layout';
// import ProjectForm from './components/ProjectForm';
import BudgetControl from './components/BudgetControl';
import BudgetSummary from './components/BudgetSummary';
import ProjectManagement from './components/ProjectManagement';
import { BudgetProvider } from './contexts/BudgetContext';
import './App.css';
import Login from './components/Login';
import UserManagement from './components/UserManagement';
import AuthGuard from './components/AuthGuard';
import { AuthProvider } from './contexts/AuthContext';

const App: React.FC = () => {
  return (
    <ThemeProvider theme={theme}>
      <LocalizationProvider dateAdapter={AdapterDateFns} adapterLocale={thLocale}>
        <CssBaseline />
          <AuthProvider>
            <BudgetProvider>
              <Router>
                <Routes>
                  <Route path="/login" element={<Login />} />
                  
                  <Route element={<AuthGuard requiredRole="any" />}>
                    <Route element={<Layout><ProjectManagement /></Layout>} path="/" />
                    <Route element={<Layout><BudgetControl /></Layout>} path="/budget-control" />
                    <Route element={<Layout><BudgetSummary /></Layout>} path="/budget-summary" />
                  </Route>
                  
                  <Route element={<AuthGuard requiredRole="admin" />}>
                    <Route element={<Layout><UserManagement /></Layout>} path="/user-management" />
                  </Route>
                  
                  <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
              </Router>
            </BudgetProvider>
          </AuthProvider>
      </LocalizationProvider>
    </ThemeProvider>
  );
};

export default App;
