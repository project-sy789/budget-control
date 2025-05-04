import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { Project as ProjectType, Transaction as TransactionType } from '../types';

// Define API URL
const API_URL = process.env.REACT_APP_API_URL || '/api';

interface Project extends ProjectType {}
interface Transaction extends TransactionType {}

export interface BudgetContextType {
  projects: Project[];
  transactions: Transaction[];
  addProject: (project: Omit<Project, 'id'>) => Promise<Project>; // Return the created project
  updateProject: (id: string, project: Partial<Project>) => Promise<void>;
  deleteProject: (id: string) => Promise<void>;
  addTransaction: (transaction: Omit<Transaction, 'id'>) => Promise<Transaction>; // Return the created transaction
  updateTransaction: (id: string, transaction: Partial<Transaction>) => Promise<void>;
  deleteTransaction: (id: string) => Promise<void>;
  setProjects: (projects: Project[] | ((prev: Project[]) => Project[])) => void;
  setTransactions: (transactions: Transaction[] | ((prev: Transaction[]) => Transaction[])) => void;
  getProjectTransactions: (projectId: string) => Transaction[];
  getProjectBalance: (projectId: string) => number;
  loading: boolean;
  error: string | null;
  refreshData: () => Promise<void>;
}

export const BudgetContext = createContext<BudgetContextType | undefined>(undefined);

const LOADING_TIMEOUT = 20000; // 20 seconds timeout

// Helper function for API calls
const fetchAPI = async (url: string, options: RequestInit = {}) => {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), LOADING_TIMEOUT);

  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
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
      } catch (e) {
        // Ignore if response is not JSON
      }
      throw new Error(errorMessage);
    }

    // Handle 204 No Content response for DELETE requests
    if (response.status === 204) {
      return null; // Or return a specific indicator if needed
    }

    return await response.json();
  } catch (error: unknown) { // Specify error type as unknown
    clearTimeout(timeoutId);
    // Check if error is an instance of Error before accessing properties
    if (error instanceof Error && error.name === 'AbortError') {
      throw new Error('การร้องขอข้อมูลใช้เวลานานเกินไป');
    }
    // Re-throw the original error or a generic one if it's not an Error instance
    throw error instanceof Error ? error : new Error("เกิดข้อผิดพลาดที่ไม่รู้จักใน fetchAPI");
  }
};

export const BudgetProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [projects, setProjects] = useState<Project[]>([]);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    console.log("BudgetContext: Loading data...");
    setLoading(true);
    setError(null);
    try {
      // Fetch projects and transactions concurrently
      const [projectsData, transactionsData] = await Promise.all([
        fetchAPI(`${API_URL}/projects`),
        fetchAPI(`${API_URL}/transactions`)
      ]);
      console.log("BudgetContext: Data fetched", { projectsData, transactionsData });
      setProjects(Array.isArray(projectsData) ? projectsData : []);
      setTransactions(Array.isArray(transactionsData) ? transactionsData : []);
    } catch (err) {
      console.error('BudgetContext: Error loading data:', err);
      setError(err instanceof Error ? err.message : 'ไม่สามารถโหลดข้อมูลได้');
      setProjects([]); // Clear data on error
      setTransactions([]);
    } finally {
      setLoading(false);
      console.log("BudgetContext: Loading finished.");
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const addProject = async (project: Omit<Project, 'id'>): Promise<Project> => {
    setLoading(true);
    try {
      const newProject = await fetchAPI(`${API_URL}/projects`, {
        method: 'POST',
        body: JSON.stringify(project),
      });
      setProjects(prev => [...prev, newProject]);
      setError(null);
      return newProject;
    } catch (err) {
      console.error('BudgetContext: Error adding project:', err);
      setError(err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการเพิ่มโครงการ');
      throw err; // Re-throw error for component handling
    } finally {
      setLoading(false);
    }
  };

  const updateProject = async (id: string, projectUpdate: Partial<Project>) => {
    setLoading(true);
    try {
      const updatedProject = await fetchAPI(`${API_URL}/projects/${id}`, {
        method: 'PUT',
        body: JSON.stringify(projectUpdate),
      });
      setProjects(prev => prev.map(p => (p.id === id ? updatedProject : p)));
      setError(null);
    } catch (err) {
      console.error('BudgetContext: Error updating project:', err);
      setError(err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการอัปเดตโครงการ');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const deleteProject = async (id: string) => {
    setLoading(true);
    try {
      await fetchAPI(`${API_URL}/projects/${id}`, {
        method: 'DELETE',
      });
      // Reload data to ensure consistency (backend deletes associated transactions)
      await loadData();
      setError(null);
    } catch (err) {
      console.error('BudgetContext: Error deleting project:', err);
      setError(err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการลบโครงการ');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const addTransaction = async (transaction: Omit<Transaction, 'id'>): Promise<Transaction> => {
    setLoading(true);
    try {
      const newTransaction = await fetchAPI(`${API_URL}/transactions`, {
        method: 'POST',
        body: JSON.stringify(transaction),
      });
      setTransactions(prev => [...prev, newTransaction]);
      setError(null);
      return newTransaction;
    } catch (err) {
      console.error('BudgetContext: Error adding transaction:', err);
      setError(err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการเพิ่มธุรกรรม');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const updateTransaction = async (id: string, transactionUpdate: Partial<Transaction>) => {
    setLoading(true);
    try {
      const updatedTransaction = await fetchAPI(`${API_URL}/transactions/${id}`, {
        method: 'PUT',
        body: JSON.stringify(transactionUpdate),
      });
      setTransactions(prev => prev.map(t => (t.id === id ? updatedTransaction : t)));
      setError(null);
    } catch (err) {
      console.error('BudgetContext: Error updating transaction:', err);
      setError(err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการอัปเดตธุรกรรม');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const deleteTransaction = async (id: string) => {
    setLoading(true);
    try {
      await fetchAPI(`${API_URL}/transactions/${id}`, {
        method: 'DELETE',
      });
      setTransactions(prev => prev.filter(t => t.id !== id));
      setError(null);
    } catch (err) {
      console.error('BudgetContext: Error deleting transaction:', err);
      setError(err instanceof Error ? err.message : 'เกิดข้อผิดพลาดในการลบธุรกรรม');
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const getProjectTransactions = (projectId: string) => {
    return transactions.filter(t => t.projectId === projectId);
  };

  const getProjectBalance = (projectId: string) => {
    const projectTransactions = getProjectTransactions(projectId);
    // Ensure amount is treated as a number
    return projectTransactions.reduce((sum, t) => sum + Number(t.amount || 0), 0);
  };

  return (
    <BudgetContext.Provider value={{
      projects,
      transactions,
      addProject,
      updateProject,
      deleteProject,
      addTransaction,
      updateTransaction,
      deleteTransaction,
      setProjects,
      setTransactions,
      getProjectTransactions,
      getProjectBalance,
      loading,
      error,
      refreshData: loadData
    }}>
      {/* Optional: Global loading indicator can be added here */}
      {children}
    </BudgetContext.Provider>
  );
};

export const useBudget = () => {
  const context = useContext(BudgetContext);
  if (context === undefined) {
    throw new Error('useBudget must be used within a BudgetProvider');
  }
  return context;
};

