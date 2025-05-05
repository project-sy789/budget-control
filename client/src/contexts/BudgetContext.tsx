// Context for managing budget data (projects and transactions)

// Thai: Context สำหรับจัดการข้อมูลเกี่ยวกับงบประมาณ (โครงการและธุรกรรม)
// - เก็บรายการโครงการ (projects) และธุรกรรม (transactions)
// - จัดการสถานะ loading และ error สำหรับการดึงข้อมูล
// - มีฟังก์ชันสำหรับดึง, เพิ่ม, แก้ไข, ลบ โครงการและธุรกรรม โดยเรียกใช้ API ผ่าน AuthContext

import React, { createContext, useContext, useState, useCallback, ReactNode } from "react";
import { Project, Transaction, BudgetSummaryData } from "../types"; // Assuming types are defined here
import { useAuth } from "./AuthContext"; // Import useAuth to access the authenticated API function

// Define the shape of the budget state
interface BudgetState {
  projects: Project[];
  transactions: Transaction[];
  currentProject: Project | null; // Track the currently selected project for detail views
  transactionPagination: {
      currentPage: number;
      totalPages: number;
      totalItems: number;
      limit: number;
  };
  loadingProjects: boolean;
  loadingTransactions: boolean;
  error: string | null;
}

// Define the context type
interface BudgetContextType {
  budgetState: BudgetState;
  fetchProjects: () => Promise<void>;
  fetchTransactions: (projectId?: string, page?: number, limit?: number) => Promise<void>;
  fetchProjectById: (id: string) => Promise<Project | null>;
  addProject: (projectData: Omit<Project, "id" | "created_at" | "updated_at" | "total_income" | "total_expense" | "current_balance">) => Promise<void>;
  updateProject: (id: string, projectData: Omit<Project, "id" | "created_at" | "updated_at" | "total_income" | "total_expense" | "current_balance">) => Promise<void>;
  deleteProject: (id: string) => Promise<void>;
  addTransaction: (transactionData: Omit<Transaction, "id" | "created_at" | "updated_at">) => Promise<void>;
  updateTransaction: (id: string, transactionData: Omit<Transaction, "id" | "created_at" | "updated_at">) => Promise<void>;
  deleteTransaction: (id: string) => Promise<void>;
  setCurrentProject: (project: Project | null) => void;
  exportProjectTransactions: (projectId: string) => Promise<void>; // Function for export
}

// Create the budget context
const BudgetContext = createContext<BudgetContextType | undefined>(undefined);

// Create the context provider component
export const BudgetProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const { api } = useAuth(); // Get the authenticated fetchAPI function from AuthContext
  const [budgetState, setBudgetState] = useState<BudgetState>({
    projects: [],
    transactions: [],
    currentProject: null,
    transactionPagination: { currentPage: 1, totalPages: 1, totalItems: 0, limit: 10 },
    loadingProjects: false,
    loadingTransactions: false,
    error: null,
  });

  // --- Project Functions --- //

  // Fetch all projects
  // Thai: ฟังก์ชันดึงข้อมูลโครงการทั้งหมดจาก API
  const fetchProjects = useCallback(async () => {
    setBudgetState(prev => ({ ...prev, loadingProjects: true, error: null }));
    try {
      // Thai: เรียก API /api/projects โดยใช้ `api` function จาก useAuth (ซึ่งมี token อยู่แล้ว)
      const response = await api("/api/projects");
      if (response && response.success && Array.isArray(response.data)) {
        setBudgetState(prev => ({ ...prev, projects: response.data, loadingProjects: false }));
      } else {
        throw new Error("รูปแบบข้อมูลโครงการที่ได้รับไม่ถูกต้อง");
      }
    } catch (err) {
      console.error("Error fetching projects:", err);
      setBudgetState(prev => ({ ...prev, loadingProjects: false, error: err instanceof Error ? err.message : "ไม่สามารถดึงข้อมูลโครงการได้" }));
    }
  }, [api]);

  // Fetch a single project by ID
  // Thai: ฟังก์ชันดึงข้อมูลโครงการเดียวตาม ID
  const fetchProjectById = useCallback(async (id: string): Promise<Project | null> => {
      setBudgetState(prev => ({ ...prev, loadingProjects: true, error: null }));
      try {
          const response = await api(`/api/projects/${id}`);
          if (response && response.success && response.data) {
              setBudgetState(prev => ({ ...prev, currentProject: response.data, loadingProjects: false }));
              return response.data;
          } else {
              throw new Error("ไม่พบโครงการ หรือรูปแบบข้อมูลไม่ถูกต้อง");
          }
      } catch (err) {
          console.error(`Error fetching project ${id}:`, err);
          setBudgetState(prev => ({ ...prev, loadingProjects: false, error: err instanceof Error ? err.message : `ไม่สามารถดึงข้อมูลโครงการ ${id} ได้` }));
          return null;
      }
  }, [api]);

  // Add a new project
  // Thai: ฟังก์ชันเพิ่มโครงการใหม่ผ่าน API
  const addProject = useCallback(async (projectData: Omit<Project, "id" | "created_at" | "updated_at" | "total_income" | "total_expense" | "current_balance">) => {
    setBudgetState(prev => ({ ...prev, loadingProjects: true, error: null }));
    try {
      const response = await api("/api/projects", { method: "POST", body: JSON.stringify(projectData) });
      if (response && response.success && response.data) {
        // Refetch projects to get the updated list including the new one
        await fetchProjects();
      } else {
        throw new Error("การเพิ่มโครงการล้มเหลว หรือไม่ได้รับข้อมูลที่ถูกต้องกลับมา");
      }
    } catch (err) {
      console.error("Error adding project:", err);
      setBudgetState(prev => ({ ...prev, loadingProjects: false, error: err instanceof Error ? err.message : "เกิดข้อผิดพลาดในการเพิ่มโครงการ" }));
      throw err; // Re-throw for component
    }
  }, [api, fetchProjects]);

  // Update an existing project
  // Thai: ฟังก์ชันอัปเดตข้อมูลโครงการผ่าน API
  const updateProject = useCallback(async (id: string, projectData: Omit<Project, "id" | "created_at" | "updated_at" | "total_income" | "total_expense" | "current_balance">) => {
    setBudgetState(prev => ({ ...prev, loadingProjects: true, error: null }));
    try {
      const response = await api(`/api/projects/${id}`, { method: "PUT", body: JSON.stringify(projectData) });
      if (response && response.success && response.data) {
        // Refetch projects to get the updated list
        await fetchProjects();
      } else {
        throw new Error("การอัปเดตโครงการล้มเหลว หรือไม่ได้รับข้อมูลที่ถูกต้องกลับมา");
      }
    } catch (err) {
      console.error(`Error updating project ${id}:`, err);
      setBudgetState(prev => ({ ...prev, loadingProjects: false, error: err instanceof Error ? err.message : `เกิดข้อผิดพลาดในการอัปเดตโครงการ ${id}` }));
      throw err; // Re-throw for component
    }
  }, [api, fetchProjects]);

  // Delete a project
  // Thai: ฟังก์ชันลบโครงการผ่าน API
  const deleteProject = useCallback(async (id: string) => {
    setBudgetState(prev => ({ ...prev, loadingProjects: true, error: null }));
    try {
      await api(`/api/projects/${id}`, { method: "DELETE" }, false); // Expect 204 No Content
      // Refetch projects to update the list
      await fetchProjects();
      // Also clear transactions if they belong to the deleted project
      setBudgetState(prev => ({ ...prev, transactions: prev.transactions.filter(t => t.project_id !== id) }));
    } catch (err) {
      console.error(`Error deleting project ${id}:`, err);
      setBudgetState(prev => ({ ...prev, loadingProjects: false, error: err instanceof Error ? err.message : `เกิดข้อผิดพลาดในการลบโครงการ ${id}` }));
      throw err; // Re-throw for component
    }
  }, [api, fetchProjects]);

  // --- Transaction Functions --- //

  // Fetch transactions with pagination
  // Thai: ฟังก์ชันดึงข้อมูลธุรกรรมจาก API (รองรับการกรองและแบ่งหน้า)
  const fetchTransactions = useCallback(async (projectId?: string, page: number = 1, limit: number = 10) => {
    setBudgetState(prev => ({ ...prev, loadingTransactions: true, error: null }));
    try {
      const queryParams = new URLSearchParams({
          page: page.toString(),
          limit: limit.toString(),
      });
      if (projectId) {
          queryParams.append("projectId", projectId);
      }
      // Thai: เรียก API /api/transactions พร้อม query parameters
      const response = await api(`/api/transactions?${queryParams.toString()}`);
      if (response && response.success && Array.isArray(response.data) && response.pagination) {
        setBudgetState(prev => ({
          ...prev,
          transactions: response.data,
          transactionPagination: {
              currentPage: response.pagination.currentPage,
              totalPages: response.pagination.totalPages,
              totalItems: response.pagination.totalItems,
              limit: response.pagination.limit,
          },
          loadingTransactions: false
        }));
      } else {
        throw new Error("รูปแบบข้อมูลธุรกรรมที่ได้รับไม่ถูกต้อง");
      }
    } catch (err) {
      console.error("Error fetching transactions:", err);
      setBudgetState(prev => ({ ...prev, loadingTransactions: false, error: err instanceof Error ? err.message : "ไม่สามารถดึงข้อมูลธุรกรรมได้" }));
    }
  }, [api]);

  // Add a new transaction
  // Thai: ฟังก์ชันเพิ่มธุรกรรมใหม่ผ่าน API
  const addTransaction = useCallback(async (transactionData: Omit<Transaction, "id" | "created_at" | "updated_at">) => {
    setBudgetState(prev => ({ ...prev, loadingTransactions: true, error: null }));
    try {
      const response = await api("/api/transactions", { method: "POST", body: JSON.stringify(transactionData) });
      if (response && response.success && response.data) {
        // Refetch transactions for the current view/page
        // Or simply add to the start of the list if sorted by date desc
        // For simplicity, refetching based on current pagination
        await fetchTransactions(
            budgetState.currentProject?.id, // Use current project ID if available
            budgetState.transactionPagination.currentPage,
            budgetState.transactionPagination.limit
        );
        // Also refetch projects to update budget summaries
        await fetchProjects();
      } else {
        throw new Error("การเพิ่มธุรกรรมล้มเหลว หรือไม่ได้รับข้อมูลที่ถูกต้องกลับมา");
      }
    } catch (err) {
      console.error("Error adding transaction:", err);
      setBudgetState(prev => ({ ...prev, loadingTransactions: false, error: err instanceof Error ? err.message : "เกิดข้อผิดพลาดในการเพิ่มธุรกรรม" }));
      throw err; // Re-throw for component
    }
  }, [api, fetchTransactions, fetchProjects, budgetState.currentProject, budgetState.transactionPagination]);

  // Update an existing transaction
  // Thai: ฟังก์ชันอัปเดตข้อมูลธุรกรรมผ่าน API
  const updateTransaction = useCallback(async (id: string, transactionData: Omit<Transaction, "id" | "created_at" | "updated_at">) => {
    setBudgetState(prev => ({ ...prev, loadingTransactions: true, error: null }));
    try {
      const response = await api(`/api/transactions/${id}`, { method: "PUT", body: JSON.stringify(transactionData) });
      if (response && response.success && response.data) {
        // Refetch transactions for the current view/page
        await fetchTransactions(
            budgetState.currentProject?.id,
            budgetState.transactionPagination.currentPage,
            budgetState.transactionPagination.limit
        );
        // Also refetch projects to update budget summaries
        await fetchProjects();
      } else {
        throw new Error("การอัปเดตธุรกรรมล้มเหลว หรือไม่ได้รับข้อมูลที่ถูกต้องกลับมา");
      }
    } catch (err) {
      console.error(`Error updating transaction ${id}:`, err);
      setBudgetState(prev => ({ ...prev, loadingTransactions: false, error: err instanceof Error ? err.message : `เกิดข้อผิดพลาดในการอัปเดตธุรกรรม ${id}` }));
      throw err; // Re-throw for component
    }
  }, [api, fetchTransactions, fetchProjects, budgetState.currentProject, budgetState.transactionPagination]);

  // Delete a transaction
  // Thai: ฟังก์ชันลบธุรกรรมผ่าน API
  const deleteTransaction = useCallback(async (id: string) => {
    setBudgetState(prev => ({ ...prev, loadingTransactions: true, error: null }));
    try {
      await api(`/api/transactions/${id}`, { method: "DELETE" }, false); // Expect 204 No Content
      // Refetch transactions for the current view/page
      await fetchTransactions(
          budgetState.currentProject?.id,
          budgetState.transactionPagination.currentPage,
          budgetState.transactionPagination.limit
      );
      // Also refetch projects to update budget summaries
      await fetchProjects();
    } catch (err) {
      console.error(`Error deleting transaction ${id}:`, err);
      setBudgetState(prev => ({ ...prev, loadingTransactions: false, error: err instanceof Error ? err.message : `เกิดข้อผิดพลาดในการลบธุรกรรม ${id}` }));
      throw err; // Re-throw for component
    }
  }, [api, fetchTransactions, fetchProjects, budgetState.currentProject, budgetState.transactionPagination]);

  // Set the currently selected project
  // Thai: ฟังก์ชันกำหนดโครงการที่กำลังเลือกดู
  const setCurrentProject = useCallback((project: Project | null) => {
      setBudgetState(prev => ({ ...prev, currentProject: project }));
      // When project changes, fetch its transactions (page 1)
      if (project) {
          fetchTransactions(project.id, 1, budgetState.transactionPagination.limit);
      } else {
          // If project is deselected, maybe clear transactions or fetch all?
          // Clearing for now:
          setBudgetState(prev => ({ ...prev, transactions: [], transactionPagination: { currentPage: 1, totalPages: 1, totalItems: 0, limit: 10 } }));
      }
  }, [fetchTransactions, budgetState.transactionPagination.limit]);

  // Export transactions for a specific project
  // Thai: ฟังก์ชันเรียก API เพื่อส่งออกธุรกรรมของโครงการที่ระบุเป็น Excel
  const exportProjectTransactions = useCallback(async (projectId: string) => {
      try {
          const token = localStorage.getItem("authToken"); // Need token for direct fetch
          if (!token) throw new Error("Authentication token not found.");

          const response = await fetch(`/api/transactions/export?projectId=${projectId}`, {
              headers: {
                  'Authorization': `Bearer ${token}`
              }
          });

          if (!response.ok) {
              let errorMessage = `Export failed: ${response.status} ${response.statusText}`;
              try {
                  const errorData = await response.json();
                  errorMessage = errorData.message || errorMessage;
              } catch (e) { /* Ignore if response is not JSON */ }
              throw new Error(errorMessage);
          }

          // Handle the file download
          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          // Extract filename from content-disposition header if possible, otherwise use default
          const disposition = response.headers.get('content-disposition');
          let filename = `transactions_export_${projectId}.xlsx`;
          if (disposition && disposition.indexOf('attachment') !== -1) {
              const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
              const matches = filenameRegex.exec(disposition);
              if (matches != null && matches[1]) {
                  filename = matches[1].replace(/['"]/g, '');
              }
          }
          a.href = url;
          a.download = filename;
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);

      } catch (err) {
          console.error(`Error exporting transactions for project ${projectId}:`, err);
          // Set error state to show in UI
          setBudgetState(prev => ({ ...prev, error: err instanceof Error ? err.message : `เกิดข้อผิดพลาดในการส่งออกข้อมูล` }));
          throw err; // Re-throw for component if needed
      }
  }, []); // No dependencies on state needed here

  // Provide the state and functions through the context
  return (
    <BudgetContext.Provider value={{
      budgetState,
      fetchProjects,
      fetchTransactions,
      fetchProjectById,
      addProject,
      updateProject,
      deleteProject,
      addTransaction,
      updateTransaction,
      deleteTransaction,
      setCurrentProject,
      exportProjectTransactions
    }}>
      {children}
    </BudgetContext.Provider>
  );
};

// Hook for using the budget context
// Thai: Hook สำหรับเรียกใช้ BudgetContext ใน components ต่างๆ
export const useBudget = () => {
  const context = useContext(BudgetContext);
  if (context === undefined) {
    throw new Error("useBudget must be used within a BudgetProvider");
  }
  return context;
};

