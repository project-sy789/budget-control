import React, { useState, useRef } from 'react';
import {
  Box,
  Typography,
  Button,
  Grid,
  Card,
  CardContent,
  Dialog,
  DialogTitle,
  DialogContent,
  TextField,
  MenuItem,
  IconButton,
  Chip,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TablePagination,
  Tooltip,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import { Project, Transaction, BudgetCategory, BudgetCategoryType, BudgetCategoryItem } from '../types';
import ProjectForm from './ProjectForm';
import * as XLSX from 'xlsx';
import { useBudget } from '../contexts/BudgetContext';

// กำหนดสีของแต่ละกลุ่มงานให้ตรงกับที่ใช้ใน BudgetSummary.tsx
interface CustomChipColorProps {
  color: string;
  backgroundColor: string;
  borderColor: string;
}

// ใช้สีเดียวกับ BudgetSummary.tsx
const workGroupCustomColors: Record<string, CustomChipColorProps> = {
  academic: { color: '#ffffff', backgroundColor: '#1976d2', borderColor: '#1976d2' }, // สีน้ำเงิน
  budget: { color: '#ffffff', backgroundColor: '#9c27b0', borderColor: '#9c27b0' }, // สีม่วง
  hr: { color: '#ffffff', backgroundColor: '#ff9800', borderColor: '#ff9800' }, // สีส้ม
  general: { color: '#ffffff', backgroundColor: '#2e7d32', borderColor: '#2e7d32' }, // สีเขียวเข้ม
  other: { color: '#ffffff', backgroundColor: '#424242', borderColor: '#424242' } // สีเทาเข้ม
};

const getWorkGroupLabel = (workGroup: string) => {
  switch (workGroup) {
    case 'academic':
      return 'กลุ่มงานบริหารวิชาการ';
    case 'budget':
      return 'กลุ่มงานงบประมาณ';
    case 'hr':
      return 'กลุ่มงานบริหารงานบุคคล';
    case 'general':
      return 'กลุ่มงานบริหารทั่วไป';
    case 'other':
      return 'อื่น ๆ';
    default:
      return workGroup;
  }
};

const ProjectManagement: React.FC = () => {
  const { 
    projects, 
    transactions, 
    addProject, 
    updateProject, 
    deleteProject, 
    setProjects,
    addTransaction
  } = useBudget();
  const [openForm, setOpenForm] = useState(false);
  const [selectedProject, setSelectedProject] = useState<Project | null>(null);
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(5);
  const [filterWorkGroup, setFilterWorkGroup] = useState<string>('all');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleOpenForm = (project?: Project) => {
    if (project) {
      const validProject: Project = {
        ...project,
        description: project.description || '',
        budgetCategories: Array.isArray(project.budgetCategories) 
          ? project.budgetCategories.map(cat => ({
              category: cat.category as BudgetCategoryType,
              amount: Number(cat.amount) || 0,
              description: cat.description || ''
            }))
          : [],
        budget: Number(project.budget) || 0
      };
      setSelectedProject(validProject);
    } else {
      setSelectedProject(null);
    }
    setOpenForm(true);
  };

  const handleCloseForm = () => {
    setOpenForm(false);
    setSelectedProject(null);
  };

  const handleSubmit = (projectData: Omit<Project, 'id'>) => {
    try {
    if (selectedProject) {
        updateProject(selectedProject.id, projectData);
    } else {
        // Check for duplicate project name
        const isDuplicate = projects.some(p => 
          p.name.trim().toLowerCase() === projectData.name.trim().toLowerCase()
        );
        
        if (isDuplicate) {
          alert(`โครงการชื่อ "${projectData.name}" มีอยู่ในระบบแล้ว กรุณาใช้ชื่อโครงการอื่น`);
          return;
        }
        
      addProject(projectData);
    }
    handleCloseForm();
    } catch (error) {
      console.error('Error saving project:', error);
      if (error instanceof Error) {
        alert(error.message);
      } else {
        alert('เกิดข้อผิดพลาดในการบันทึกโครงการ กรุณาลองใหม่อีกครั้ง');
      }
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await deleteProject(id);
      setProjects(prev => prev.filter(project => project.id !== id));
    } catch (error) {
      console.error('Error deleting project:', error);
      alert('เกิดข้อผิดพลาดในการลบโครงการ กรุณาลองใหม่อีกครั้ง');
    }
  };

  const handleChangePage = (event: unknown, newPage: number) => {
    setPage(newPage);
  };

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10));
    setPage(0);
  };

  const filteredProjects = filterWorkGroup === 'all' 
    ? projects 
    : projects.filter(project => project.workGroup === filterWorkGroup);

  const paginatedProjects = filteredProjects.slice(
    page * rowsPerPage,
    page * rowsPerPage + rowsPerPage
  );

  // Calculate total budget
  const totalBudget = projects.reduce((sum, project) => sum + project.budget, 0);

  // Get unique work groups
  const workGroups = Array.from(new Set(projects.map(project => project.workGroup)));

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    try {
      const data = await file.arrayBuffer();
      const workbook = XLSX.read(data);
      
      // Verify workbook has at least one sheet
      if (!workbook.SheetNames || workbook.SheetNames.length === 0) {
        alert('ไฟล์ Excel ไม่มีข้อมูล กรุณาตรวจสอบไฟล์อีกครั้ง');
        return;
      }

      // Get project sheet data - ลองหา sheet ที่มีชื่อเกี่ยวกับการนำเข้าก่อน
      const prioritySheetNames = ["นำเข้าโครงการ", "โครงการทั้งหมด", "แบบฟอร์มโครงการ"];
      let projectSheetName = null;
      
      // พยายามหา sheet ที่เหมาะสมสำหรับนำเข้าข้อมูล
      for (const name of prioritySheetNames) {
        if (workbook.SheetNames.includes(name)) {
          projectSheetName = name;
          break;
        }
      }
      
      // ถ้าไม่พบ sheet ในรายการที่กำหนด ใช้ sheet แรก
      if (!projectSheetName) {
        projectSheetName = workbook.SheetNames[0];
      }
      
      console.log("Reading data from sheet:", projectSheetName);
      
      const wsProjects = workbook.Sheets[projectSheetName];
      const projectData = XLSX.utils.sheet_to_json<{
        name: string;
        budget: number | string;
        workGroup: string;
        responsiblePerson: string;
        description?: string;
        startDate: string;
        endDate: string;
        status: string;
        budgetCategories: string;
      }>(wsProjects);
      
      // ตรวจสอบว่ามีข้อมูลหรือไม่
      if (!Array.isArray(projectData) || projectData.length === 0) {
        // ถ้าไม่พบข้อมูลใน sheet แรก ลองอ่านจาก sheet อื่นๆ
        console.log("No data found in first sheet, trying other sheets");
        
        for (const sheetName of workbook.SheetNames) {
          if (sheetName !== projectSheetName && !sheetName.includes("คำอธิบาย") && !sheetName.includes("รายการ")) {
            const wsAlternative = workbook.Sheets[sheetName];
            const alternativeData = XLSX.utils.sheet_to_json(wsAlternative);
            
            if (Array.isArray(alternativeData) && alternativeData.length > 0) {
              console.log(`Found data in sheet: ${sheetName}`, alternativeData);
              
              // ตรวจสอบว่ามีข้อมูลที่จำเป็นหรือไม่
              if (alternativeData.some((item: any) => 'name' in item && item.name)) {
                projectSheetName = sheetName;
                console.log(`Using data from sheet: ${sheetName}`);
                break;
              }
            }
          }
        }
      }

      console.log("Imported project data:", projectData);

      // Verify project data is valid
      if (!Array.isArray(projectData) || projectData.length === 0) {
        alert('ไม่พบข้อมูลโครงการในไฟล์ Excel กรุณาตรวจสอบรูปแบบไฟล์อีกครั้ง');
        return;
      }

      // Get transaction data if available
      let transactionData: any[] = [];
      const transactionSheetName = workbook.SheetNames.find(name => 
        name === "รายการทั้งหมด" || name.includes("Transactions") || name.includes("รายการ")
      );
      
      if (transactionSheetName) {
        console.log("Reading transactions from sheet:", transactionSheetName);
        const wsTransactions = workbook.Sheets[transactionSheetName];
        const rawTransactionData = XLSX.utils.sheet_to_json(wsTransactions);
        
        // Process raw transaction data to convert types correctly
        transactionData = rawTransactionData.map((item: any) => {
          // Process boolean values
          const isTransfer = item.isTransfer === true || 
                            item.isTransfer === 'true' || 
                            item.isTransfer === 'TRUE' ||
                            item.isTransfer === 'ใช่' || 
                            item.isTransfer === 'yes' || 
                            item.isTransfer === 'YES' ||
                            (item.description || '').includes('[โอนงบประมาณ]');
          
          const isTransferIn = item.isTransferIn === true || 
                             item.isTransferIn === 'true' || 
                             item.isTransferIn === 'TRUE' ||
                             item.isTransferIn === 'ใช่' || 
                             item.isTransferIn === 'yes' || 
                             item.isTransferIn === 'YES' ||
                             (item.description || '').includes('รับจากโครงการ:');
                             
          // Process amounts
          const amount = typeof item.amount === 'string' 
            ? Number(item.amount.replace(/,/g, '')) 
            : Number(item.amount) || 0;

          return {
            ...item,
            isTransfer,
            isTransferIn,
            amount,
            date: item.date || new Date().toISOString().split('T')[0]
          };
        });
        
        console.log("Imported transaction data (processed):", transactionData);
      }

      // Track duplicate projects and their IDs
      const duplicateProjects: string[] = [];
      const projectNameToIdMap: { [key: string]: string } = {};
      const importedProjects: string[] = [];
      // Track duplicate transactions
      const duplicateTransactions: string[] = [];
      
      // Define a type for tracking imported transfers
      interface ImportedTransfer {
        date: string;
        fromProjectId: string;
        toProjectId: string;
        amount: number;
      }
      
      // Track imported transactions to prevent duplicates within the current import
      const importedTransactions: ImportedTransfer[] = [];
      const errorMessages: string[] = [];

      // Process projects
      for (const project of projectData) {
        try {
          // Debug info
          console.log("Processing project:", project);
          
          // Basic validation
          if (!project.name) {
            const keys = Object.keys(project);
            errorMessages.push(`พบโครงการที่ไม่มีชื่อ: มีคอลัมน์ ${keys.join(', ')}`);
            continue;
          }

          // Validate budget
          const budget = typeof project.budget === 'string' 
            ? Number(project.budget.replace(/,/g, '')) 
            : Number(project.budget) || 0;
            
          if (isNaN(budget)) {
            errorMessages.push(`โครงการ "${project.name}" มีงบประมาณไม่ถูกต้อง`);
            continue;
          }

          // Validate work group
          const validWorkGroups = ['academic', 'budget', 'hr', 'general', 'other'];
          if (!validWorkGroups.includes(project.workGroup)) {
            errorMessages.push(`โครงการ "${project.name}" มีกลุ่มงานไม่ถูกต้อง ต้องเป็น: ${validWorkGroups.join(', ')}`);
            continue;
          }

          // Validate dates
          if (!project.startDate || !project.endDate) {
            errorMessages.push(`โครงการ "${project.name}" ไม่มีวันที่เริ่มต้นหรือวันที่สิ้นสุด`);
            continue;
          }

          // Validate status
          const validStatus = ['active', 'completed'];
          if (!validStatus.includes(project.status)) {
            // Try to fix common status labels
            if (project.status === 'ดำเนินการ') {
              project.status = 'active';
            } else if (project.status === 'เสร็จสิ้น') {
              project.status = 'completed';
            } else {
              errorMessages.push(`โครงการ "${project.name}" มีสถานะไม่ถูกต้อง ต้องเป็น: ${validStatus.join(', ')}`);
              continue;
            }
          }

          // Check if project already exists by name
          const existingProject = projects.find(p => 
            p.name.trim().toLowerCase() === project.name.trim().toLowerCase()
          );
          if (existingProject) {
            duplicateProjects.push(project.name);
            projectNameToIdMap[project.name] = existingProject.id;
            // Skip adding this project since it's a duplicate
            continue;
          }
          
          // Parse budget categories from the project data
          let parsedCategories: BudgetCategoryItem[] = [];
          try {
            if (typeof project.budgetCategories === 'string') {
              // Handle different possible formats
              let categoriesJson = project.budgetCategories.trim();
              
              // Try to parse the JSON
              try {
                const categories = JSON.parse(categoriesJson);
                parsedCategories = (Array.isArray(categories) ? categories.map((cat: any) => ({
                  category: cat.category,
                  amount: Number(cat.amount) || 0,
                  description: cat.description || ''
                })) : []) as BudgetCategoryItem[];
              } catch (jsonError) {
                console.error('JSON parse error:', jsonError);
                console.log('Invalid JSON:', project.budgetCategories);
                errorMessages.push(`โครงการ "${project.name}" มีรูปแบบหมวดงบประมาณไม่ถูกต้อง`);
                throw jsonError;
              }
            } else if (Array.isArray(project.budgetCategories)) {
              parsedCategories = (project.budgetCategories as any[]).map((cat: any) => ({
                category: cat.category || 'SUBSIDY',
                amount: Number(cat.amount) || 0,
                description: cat.description || ''
              }));
            }
          } catch (error) {
            console.error('Error parsing budget categories:', error);
            // Continue with a default category instead of failing
            parsedCategories = [];
          }

          // Create default category if none found
          if (parsedCategories.length === 0) {
            parsedCategories = [{
              category: 'SUBSIDY',
              amount: budget,
              description: BudgetCategory.SUBSIDY
            }];
          }

          // Verify total budget matches category sum
          const categorySum = parsedCategories.reduce((sum, cat) => sum + cat.amount, 0);
          if (categorySum !== budget) {
            console.warn(`โครงการ "${project.name}" มีงบประมาณรวม (${budget}) ไม่ตรงกับผลรวมของหมวดงบประมาณ (${categorySum})`);
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            let adjustedBudget = categorySum;
          }

          const projectToAdd: Omit<Project, 'id'> = {
            name: project.name,
            workGroup: project.workGroup as 'academic' | 'budget' | 'hr' | 'general' | 'other',
            status: project.status as 'active' | 'completed',
            description: project.description || '',
            budgetCategories: parsedCategories,
            startDate: project.startDate,
            endDate: project.endDate,
            budget: budget,
            responsiblePerson: project.responsiblePerson || ''
          };
          
          await addProject(projectToAdd);
          importedProjects.push(project.name);
          
          // The project will be added to the projects array with a new ID
          const addedProject = projects.find(p => p.name === project.name);
          if (addedProject) {
            projectNameToIdMap[project.name] = addedProject.id;
          }
        } catch (projectError: any) {
          console.error('Error adding project:', projectError);
          errorMessages.push(`ไม่สามารถเพิ่มโครงการ "${project.name}" ได้: ${projectError?.message || 'Unknown error'}`);
        }
      }

      // ต้องอัปเดตรายชื่อโครงการทั้งหมดให้เป็นปัจจุบันก่อนนำเข้ารายการ
      const updatedProjects = [...projects];
      
      // Process transactions if available
      let successfulTransactions = 0;
      let successfulTransferPairs = 0;
      let successfulRegularTransactions = 0;
      let transferTransactions: any[] = [];
      
      if (transactionData && transactionData.length > 0) {
        console.log(`Found ${transactionData.length} total transactions in import file`);
        
        // แยกรายการโอนงบไว้ทำทีหลัง เพราะต้องรอให้โครงการทั้งหมดถูกนำเข้าก่อน
        const regularTransactions = transactionData.filter(t => !t.isTransfer && !t.description?.includes("[โอนงบประมาณ]"));
        transferTransactions = transactionData.filter(t => 
          t.isTransfer || 
          t.description?.includes("[โอนงบประมาณ]") || 
          t.transferToProjectName || 
          t.transferFromProjectName
        );

        console.log(`Classified ${regularTransactions.length} regular transactions and ${transferTransactions.length} transfer transactions`);
        
        // นำเข้ารายการปกติก่อน
        for (const transaction of regularTransactions) {
          try {
            if (!transaction.projectName) {
              errorMessages.push('พบรายการที่ไม่มีชื่อโครงการ');
              continue;
            }
            
            const projectId = projectNameToIdMap[transaction.projectName] || 
                             updatedProjects.find(p => p.name === transaction.projectName)?.id;
                             
            if (!projectId) {
              errorMessages.push(`ไม่พบโครงการ "${transaction.projectName}" สำหรับรายการ "${transaction.description}"`);
              continue;
            }
            
            // Validate amount
            const amount = typeof transaction.amount === 'string' 
              ? Number(transaction.amount.replace(/,/g, '')) 
              : Number(transaction.amount) || 0;
              
            if (isNaN(amount)) {
              errorMessages.push(`รายการ "${transaction.description}" มีจำนวนเงินไม่ถูกต้อง`);
              continue;
            }
            
            // Validate date
            if (!transaction.date) {
              errorMessages.push(`รายการ "${transaction.description}" ไม่มีวันที่`);
              continue;
            }
            
            // Validate budget category
            const validBudgetCategories = Object.keys(BudgetCategory);
            let budgetCategory = transaction.budgetCategory;
            
            // Try to match Thai category name to the key
            if (!validBudgetCategories.includes(budgetCategory)) {
              // Check if it matches any value in BudgetCategory
              const categoryEntry = Object.entries(BudgetCategory).find(([_key, value]) => value === budgetCategory);
              if (categoryEntry) {
                budgetCategory = categoryEntry[0];
              } else {
                errorMessages.push(`รายการ "${transaction.description}" มีหมวดงบประมาณ "${budgetCategory}" ไม่ถูกต้อง`);
                continue;
              }
            }

            // Check for duplicate transaction
            const isDuplicate = transactions.some(t => 
              t.projectId === projectId &&
              t.date === transaction.date &&
              Math.abs(t.amount - amount) < 0.01 && // Using approximate comparison for floating point
              t.description.trim().toLowerCase() === transaction.description.trim().toLowerCase() &&
              t.budgetCategory === budgetCategory
            );

            if (isDuplicate) {
              duplicateTransactions.push(`${transaction.description} (${transaction.projectName}, ${transaction.date})`);
              continue; // Skip adding this transaction since it's a duplicate
            }

            const transactionToAdd: Omit<Transaction, 'id'> = {
              projectId: projectId,
              description: transaction.description || '',
              amount: amount,
              date: transaction.date,
              budgetCategory: budgetCategory as BudgetCategoryType,
              note: transaction.note || '',
              // เพิ่มการตรวจสอบและกำหนดค่า isTransfer, isTransferIn สำหรับธุรกรรมปกติ
              isTransfer: transaction.isTransfer === true || 
                          transaction.isTransfer === 'true' || 
                          transaction.isTransfer === 'ใช่' || 
                          (transaction.description || '').includes('[โอนงบประมาณ]') || false,
              isTransferIn: transaction.isTransferIn === true || 
                            transaction.isTransferIn === 'true' || 
                            transaction.isTransferIn === 'ใช่' ||
                            (transaction.description || '').includes('รับจากโครงการ:') || false,
              // เพิ่มข้อมูลการโอนถ้ามี
              transferToProjectId: transaction.transferToProjectName ? 
                                  (projectNameToIdMap[transaction.transferToProjectName] || 
                                  updatedProjects.find(p => p.name === transaction.transferToProjectName)?.id) : 
                                  undefined,
              transferToCategory: transaction.transferToCategory ? 
                                  transaction.transferToCategory as BudgetCategoryType : 
                                  undefined,
              transferFromProjectId: transaction.transferFromProjectName ? 
                                    (projectNameToIdMap[transaction.transferFromProjectName] || 
                                    updatedProjects.find(p => p.name === transaction.transferFromProjectName)?.id) : 
                                    undefined,
              transferFromCategory: transaction.transferFromCategory ?
                                    transaction.transferFromCategory as BudgetCategoryType :
                                    undefined
            };
            
            await addTransaction(transactionToAdd);
            successfulTransactions++;
            successfulRegularTransactions++;
          } catch (transactionError: any) {
            console.error('Error adding transaction:', transactionError);
            errorMessages.push(`ไม่สามารถเพิ่มรายการได้: ${transactionError?.message || 'Unknown error'}`);
          }
        }
        
        // ประมวลผลรายการโอนงบประมาณ
        console.log("Processing transfer transactions:", transferTransactions);
        
        for (const transfer of transferTransactions) {
          try {
            console.log("Processing transfer:", {
              projectName: transfer.projectName,
              description: transfer.description,
              amount: transfer.amount,
              isTransfer: transfer.isTransfer,
              isTransferIn: transfer.isTransferIn,
              transferToProjectName: transfer.transferToProjectName,
              transferFromProjectName: transfer.transferFromProjectName
            });
            
            // ตรวจสอบว่าเป็นรายการโอนงบประมาณจริงๆ
            const isTransfer = transfer.isTransfer === true || 
                              transfer.isTransfer === 'true' || 
                              transfer.isTransfer === 'ใช่' ||
                              transfer.description?.includes("[โอนงบประมาณ]");
            
            if (!isTransfer) {
              console.log("Skipping non-transfer transaction:", transfer);
              continue;
            }
            
            // ตรวจสอบว่าเป็นการรับโอนหรือโอนออก
            let isTransferIn = false;
            let fromProjectName = '';
            let toProjectName = '';
            let fromCategory = '';
            let toCategory = '';
            
            if (transfer.description?.includes("[โอนงบประมาณ]")) {
              // ตรวจสอบข้อความในคำอธิบายรายการ
              const desc = transfer.description;
              
              if (desc.includes("รับจากโครงการ:")) {
                isTransferIn = true;
                
                // ดึงชื่อโครงการและหมวดงบจากคำอธิบาย
                const match = desc.match(/รับจากโครงการ: ([^(]+) \(([^)]+)\)/);
                if (match) {
                  fromProjectName = match[1].trim();
                  fromCategory = match[2].trim();
                  toProjectName = transfer.projectName;
                  toCategory = transfer.budgetCategory;
                } else {
                  // หากไม่สามารถแยกข้อมูลจากคำอธิบายได้ ให้ใช้ข้อมูลจากฟิลด์อื่น
                  fromProjectName = transfer.transferFromProjectName || '';
                  fromCategory = transfer.transferFromCategory || '';
                  toProjectName = transfer.projectName;
                  toCategory = transfer.budgetCategory;
                }
              } else if (desc.includes("โอนไปยังโครงการ:")) {
                isTransferIn = false;
                
                // ดึงชื่อโครงการและหมวดงบจากคำอธิบาย
                const match = desc.match(/โอนไปยังโครงการ: ([^(]+) \(([^)]+)\)/);
                if (match) {
                  toProjectName = match[1].trim();
                  toCategory = match[2].trim();
                  fromProjectName = transfer.projectName;
                  fromCategory = transfer.budgetCategory;
                } else {
                  // หากไม่สามารถแยกข้อมูลจากคำอธิบายได้ ให้ใช้ข้อมูลจากฟิลด์อื่น
                  toProjectName = transfer.transferToProjectName || '';
                  toCategory = transfer.transferToCategory || '';
                  fromProjectName = transfer.projectName;
                  fromCategory = transfer.budgetCategory;
                }
              }
            } else {
              // ใช้ข้อมูลจากฟิลด์โดยตรง
              isTransferIn = transfer.isTransferIn === true || 
                           transfer.isTransferIn === 'true' || 
                           transfer.isTransferIn === 'ใช่';
              
              if (isTransferIn) {
                fromProjectName = transfer.transferFromProjectName || '';
                fromCategory = transfer.transferFromCategory || '';
                toProjectName = transfer.projectName;
                toCategory = transfer.budgetCategory;
              } else {
                toProjectName = transfer.transferToProjectName || '';
                toCategory = transfer.transferToCategory || '';
                fromProjectName = transfer.projectName;
                fromCategory = transfer.budgetCategory;
              }
            }
            
            // ตรวจสอบข้อมูลที่จำเป็น
            if (!fromProjectName || !toProjectName) {
              errorMessages.push(`รายการโอนงบประมาณไม่ระบุโครงการต้นทางหรือปลายทาง: "${transfer.description}"`);
              continue;
            }
            
            // ค้นหา ID ของโครงการต้นทางและปลายทาง
            const fromProjectId = projectNameToIdMap[fromProjectName] || 
                                 updatedProjects.find(p => p.name === fromProjectName)?.id;
                                 
            const toProjectId = projectNameToIdMap[toProjectName] || 
                               updatedProjects.find(p => p.name === toProjectName)?.id;
                                
            if (!fromProjectId) {
              errorMessages.push(`ไม่พบโครงการต้นทาง "${fromProjectName}" สำหรับรายการโอนงบประมาณ`);
              continue;
            }
            
            if (!toProjectId) {
              errorMessages.push(`ไม่พบโครงการปลายทาง "${toProjectName}" สำหรับรายการโอนงบประมาณ`);
              continue;
            }
            
            // ตรวจสอบหมวดงบประมาณ
            const validBudgetCategories = Object.keys(BudgetCategory);
            
            // ตรวจสอบหมวดงบประมาณต้นทาง
            let fromBudgetCategory = fromCategory;
            if (!validBudgetCategories.includes(fromBudgetCategory)) {
              // พยายามแปลงจากชื่อไทยเป็นรหัส
              const categoryEntry = Object.entries(BudgetCategory).find(([_key, value]) => value === fromBudgetCategory);
              if (categoryEntry) {
                fromBudgetCategory = categoryEntry[0];
              } else {
                errorMessages.push(`รายการโอนงบประมาณมีหมวดงบประมาณต้นทางไม่ถูกต้อง: "${fromBudgetCategory}"`);
                continue;
              }
            }
            
            // ตรวจสอบหมวดงบประมาณปลายทาง
            let toBudgetCategory = toCategory;
            if (!validBudgetCategories.includes(toBudgetCategory)) {
              // พยายามแปลงจากชื่อไทยเป็นรหัส
              const categoryEntry = Object.entries(BudgetCategory).find(([_key, value]) => value === toBudgetCategory);
              if (categoryEntry) {
                toBudgetCategory = categoryEntry[0];
              } else {
                errorMessages.push(`รายการโอนงบประมาณมีหมวดงบประมาณปลายทางไม่ถูกต้อง: "${toBudgetCategory}"`);
                continue;
              }
            }
            
            // ตรวจสอบจำนวนเงิน
            const amount = typeof transfer.amount === 'string' 
              ? Number(transfer.amount.replace(/,/g, '')) 
              : Number(transfer.amount) || 0;
              
            if (isNaN(amount) || amount <= 0) {
              errorMessages.push(`รายการโอนงบประมาณมีจำนวนเงินไม่ถูกต้อง: ${amount}`);
              continue;
            }
            
            // ตรวจสอบวันที่
            if (!transfer.date) {
              errorMessages.push(`รายการโอนงบประมาณไม่มีวันที่`);
              continue;
            }
            
            // Check for duplicate transfer out transaction
            const isDuplicateTransferOut = transactions.some(t => 
              t.projectId === fromProjectId &&
              t.date === transfer.date &&
              t.isTransfer === true &&
              t.isTransferIn === false &&
              t.transferToProjectId === toProjectId &&
              t.budgetCategory === fromBudgetCategory && // Check source category
              Math.abs(Math.abs(t.amount) - amount) < 0.01 // Using approximate comparison for floating point
            );

            // Check for duplicate transfer in transaction
            const isDuplicateTransferIn = transactions.some(t => 
              t.projectId === toProjectId &&
              t.date === transfer.date &&
              t.isTransfer === true &&
              t.isTransferIn === true &&
              t.transferFromProjectId === fromProjectId &&
              t.budgetCategory === toBudgetCategory && // Check target category
              Math.abs(t.amount - amount) < 0.01 // Using approximate comparison for floating point
            );

            // If either the source or target transaction already exists, skip creating both
            if (isDuplicateTransferOut || isDuplicateTransferIn) {
              duplicateTransactions.push(`[โอนงบประมาณ] จาก ${fromProjectName} ไปยัง ${toProjectName} (${transfer.date})`);
              console.log(`Skipping duplicate transfer: ${fromProjectName} -> ${toProjectName}, amount: ${amount}`);
              continue; // Skip adding this transfer transaction since it's a duplicate
            }

            // Check for these specific transactions in the PROCESSED transfers to avoid duplicating
            // transfers that we've already processed from the current import file
            const isDuplicateInCurrentBatch = importedTransactions.some(importedTxn => 
              importedTxn.date === transfer.date &&
              ((importedTxn.fromProjectId === fromProjectId && 
                importedTxn.toProjectId === toProjectId) ||
               (importedTxn.fromProjectId === toProjectId && 
                importedTxn.toProjectId === fromProjectId))
            );

            if (isDuplicateInCurrentBatch) {
              duplicateTransactions.push(`[โอนงบประมาณ] (ในการนำเข้าครั้งนี้) จาก ${fromProjectName} ไปยัง ${toProjectName} (${transfer.date})`);
              console.log(`Skipping duplicate in current batch: ${fromProjectName} -> ${toProjectName}, amount: ${amount}`);
              continue; // Skip adding this transfer transaction if a matching pair was already imported
            }
            
            // Add this transfer to our imported transfers tracking
            importedTransactions.push({
              date: transfer.date,
              fromProjectId: fromProjectId,
              toProjectId: toProjectId,
              amount: amount
            });
            
            // สร้างรายการโอนออก
            const transferOutTransaction: Omit<Transaction, 'id'> = {
              projectId: fromProjectId,
              description: `[โอนงบประมาณ] โอนไปยังโครงการ: ${toProjectName} (${BudgetCategory[toBudgetCategory as BudgetCategoryType]})`,
              amount: -amount, // ติดลบเพราะเป็นการโอนออก
              date: transfer.date,
              budgetCategory: fromBudgetCategory as BudgetCategoryType,
              note: transfer.note || '',
              isTransfer: true,
              isTransferIn: false,
              transferToProjectId: toProjectId,
              transferToCategory: toBudgetCategory as BudgetCategoryType
            };
            
            // สร้างรายการรับโอน
            const transferInTransaction: Omit<Transaction, 'id'> = {
              projectId: toProjectId,
              description: `[โอนงบประมาณ] รับจากโครงการ: ${fromProjectName} (${BudgetCategory[fromBudgetCategory as BudgetCategoryType]})`,
              amount: amount, // เป็นบวกเพราะเป็นการรับโอน
              date: transfer.date,
              budgetCategory: toBudgetCategory as BudgetCategoryType,
              note: transfer.note || '',
              isTransfer: true,
              isTransferIn: true,
              transferFromProjectId: fromProjectId,
              transferFromCategory: fromBudgetCategory as BudgetCategoryType
            };
            
            // บันทึกรายการโอนออก
            await addTransaction(transferOutTransaction);
            successfulTransactions++;
            
            // บันทึกรายการรับโอน
            await addTransaction(transferInTransaction);
            successfulTransactions++;
            
            // เพิ่มจำนวนคู่โอนที่สำเร็จ
            successfulTransferPairs++;
          } catch (transferError: any) {
            console.error('Error adding transfer transaction:', transferError);
            errorMessages.push(`ไม่สามารถเพิ่มรายการโอนงบประมาณได้: ${transferError?.message || 'Unknown error'}`);
          }
        }
      }

      // Show import results with improved debug info
      let resultMessage = '';
      
      if (importedProjects.length > 0) {
        resultMessage += `นำเข้าโครงการสำเร็จ ${importedProjects.length} โครงการ\n`;
      }
      
      if (successfulTransactions > 0) {
        resultMessage += `นำเข้ารายการสำเร็จ ${successfulTransactions} รายการ\n`;
        // Add detail on transaction types
        if (successfulRegularTransactions > 0 || successfulTransferPairs > 0) {
          resultMessage += `- รายการปกติ: ${successfulRegularTransactions} รายการ\n`;
          resultMessage += `- โอนงบประมาณ: ${successfulTransferPairs} คู่ (${successfulTransferPairs * 2} รายการ)\n`;
        }
      }
      
      if (duplicateProjects.length > 0) {
        resultMessage += `ข้ามการนำเข้าโครงการซ้ำ ${duplicateProjects.length} โครงการ: ${duplicateProjects.join(', ')}\n`;
      }
      
      if (duplicateTransactions.length > 0) {
        resultMessage += `ข้ามการนำเข้ารายการซ้ำ ${duplicateTransactions.length} รายการ\n`;
      }
      
      if (errorMessages.length > 0) {
        resultMessage += `พบข้อผิดพลาด ${errorMessages.length} รายการ:\n`;
        resultMessage += errorMessages.slice(0, 5).join('\n');
        
        if (errorMessages.length > 5) {
          resultMessage += `\n...และอื่นๆ อีก ${errorMessages.length - 5} รายการ`;
        }
        
        // เพิ่มข้อมูลสำหรับการดีบัก
        console.error("Import errors:", errorMessages);
      }
      
      alert(resultMessage || 'นำเข้าข้อมูลสำเร็จ');
      
      // Reset the file input
        if (fileInputRef.current) {
          fileInputRef.current.value = '';
        }
      
    } catch (error: any) {
      console.error('Error importing projects:', error);
      alert(`เกิดข้อผิดพลาดในการนำเข้าโครงการ: ${error?.message || 'Unknown error'}`);
      
      // Reset the file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handleExportProjects = () => {
    try {
      const wb = XLSX.utils.book_new();
      
      // Prepare data for export in a format suitable for importing back
      const exportData = projects.map(project => ({
        name: project.name,
        budget: project.budget,
        workGroup: project.workGroup,
        responsiblePerson: project.responsiblePerson,
        description: project.description || '',
        startDate: project.startDate,
        endDate: project.endDate,
        status: project.status,
        budgetCategories: JSON.stringify(project.budgetCategories)
      }));

      // Create project sheet with a simpler format
      const ws = XLSX.utils.json_to_sheet(exportData);
      
      // Create template sheet for easier import format reference
      const wsTemplate = XLSX.utils.json_to_sheet([
        {
          name: 'ตัวอย่างโครงการ',
          budget: 150000,
          workGroup: 'academic',
          responsiblePerson: 'นายตัวอย่าง ใจดี',
          description: 'รายละเอียดโครงการตัวอย่าง',
          startDate: new Date().toISOString().split('T')[0],
          endDate: new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0],
          status: 'active',
          budgetCategories: JSON.stringify([
            {
              category: 'SUBSIDY',
              amount: 100000,
              description: BudgetCategory.SUBSIDY
            },
            {
              category: 'DEVELOPMENT',
              amount: 50000,
              description: BudgetCategory.DEVELOPMENT
            }
          ])
        }
      ]);

      // Keep the detailed documentation for reference
      const wsDescription = XLSX.utils.aoa_to_sheet([
        ['คอลัมน์', 'คำอธิบาย', 'ตัวอย่าง', 'ข้อควรระวัง'],
        ['name', 'ชื่อโครงการ', 'โครงการพัฒนาคุณภาพการศึกษา', 'ห้ามเป็นค่าว่าง'],
        ['budget', 'งบประมาณรวม (บาท)', '150000', 'ต้องเป็นตัวเลขเท่านั้น ไม่มีเครื่องหมายคอมม่า'],
        ['workGroup', 'กลุ่มงาน', 'academic', 'ต้องเลือกจาก: academic, budget, hr, general, other'],
        ['responsiblePerson', 'ผู้รับผิดชอบโครงการ', 'นายสมชาย ใจดี', 'ระบุชื่อ-นามสกุลให้ครบถ้วน'],
        ['description', 'รายละเอียดโครงการ', 'โครงการเพื่อพัฒนาคุณภาพการศึกษา...', 'ควรระบุวัตถุประสงค์และรายละเอียดที่สำคัญ'],
        ['startDate', 'วันที่เริ่มต้น', '2023-01-01', 'ต้องเป็นรูปแบบ YYYY-MM-DD'],
        ['endDate', 'วันที่สิ้นสุด', '2023-12-31', 'ต้องเป็นรูปแบบ YYYY-MM-DD และต้องไม่น้อยกว่าวันที่เริ่มต้น'],
        ['status', 'สถานะโครงการ', 'active หรือ completed', 'active = ดำเนินการ, completed = เสร็จสิ้น'],
        ['budgetCategories', 'หมวดงบประมาณ (JSON)', '[{"category":"SUBSIDY","amount":100000,"description":"เงินอุดหนุนรายหัว"}]', 'ต้องเป็น JSON array ที่ถูกต้อง'],
        ['', '', '', ''],
        ['หมวดงบประมาณที่มีในระบบ', '', '', ''],
        ['SUBSIDY', 'เงินอุดหนุนรายหัว', '', ''],
        ['DEVELOPMENT', 'เงินพัฒนาผู้เรียน', '', ''],
        ['INCOME', 'เงินรายได้สถานศึกษา', '', ''],
        ['EQUIPMENT', 'เงินค่าอุปกรณ์การเรียน', '', ''],
        ['UNIFORM', 'เงินค่าเครื่องแบบ', '', ''],
        ['BOOKS', 'เงินค่าหนังสือ', '', ''],
        ['LUNCH', 'เงินอาหารกลางวัน', '', '']
      ]);

      // Create a clean format sheet for actual data import
      const importFormatData = projects.map(project => ({
        name: project.name,
        budget: project.budget,
        workGroup: project.workGroup,
        responsiblePerson: project.responsiblePerson,
        description: project.description || '',
        startDate: project.startDate,
        endDate: project.endDate,
        status: project.status,
        budgetCategories: JSON.stringify(project.budgetCategories)
      }));
      
      const wsImportFormat = XLSX.utils.json_to_sheet(importFormatData);

      // Add transaction data if available
      if (transactions && transactions.length > 0) {
        // แยกประเภทรายการ
        const regularTransactions = transactions.filter(t => !t.isTransfer);
        const transferTransactions = transactions.filter(t => t.isTransfer);
        
        // เตรียมข้อมูลรายการปกติสำหรับการส่งออก
        const regularTransactionData = regularTransactions.map(transaction => {
          const project = projects.find(p => p.id === transaction.projectId);
          return {
            projectName: project?.name || '',
          date: transaction.date,
          description: transaction.description,
          amount: transaction.amount,
            budgetCategory: transaction.budgetCategory,
            note: transaction.note || ''
          };
        });
        
        // เตรียมข้อมูลรายการโอนงบประมาณสำหรับการส่งออก
        const transferTransactionData = transferTransactions.map(transaction => {
          const project = projects.find(p => p.id === transaction.projectId);
          const targetProject = transaction.isTransferIn 
            ? projects.find(p => p.id === transaction.transferFromProjectId)
            : projects.find(p => p.id === transaction.transferToProjectId);
          
          return {
            projectName: project?.name || '',
            date: transaction.date,
            description: transaction.description,
            amount: Math.abs(transaction.amount), // ใช้ค่าสัมบูรณ์เพื่อให้ทุกค่าเป็นบวก
          budgetCategory: transaction.budgetCategory,
          note: transaction.note || '',
            isTransfer: true,
            isTransferIn: transaction.isTransferIn,
            transferToProjectName: transaction.isTransferIn ? '' : targetProject?.name || '',
            transferToCategory: transaction.isTransferIn ? '' : transaction.transferToCategory || '',
            transferFromProjectName: transaction.isTransferIn ? targetProject?.name || '' : '',
            transferFromCategory: transaction.isTransferIn ? transaction.transferFromCategory || '' : ''
          };
        });
        
        // รวมข้อมูลทั้งหมดเพื่อส่งออก
        const transactionExportData = [...regularTransactionData, ...transferTransactionData];
        
        // สร้าง sheet สำหรับข้อมูลรายการ
        const wsTransactions = XLSX.utils.json_to_sheet(transactionExportData);
        
        // สร้าง sheet ตัวอย่างสำหรับการนำเข้ารายการปกติ
        const wsTransactionTemplate = XLSX.utils.json_to_sheet([
          {
            projectName: 'ตัวอย่างโครงการ',
            date: new Date().toISOString().split('T')[0],
            description: 'ตัวอย่างรายการปกติ',
            amount: 5000,
            budgetCategory: 'SUBSIDY',
            note: 'หมายเหตุเพิ่มเติม (ไม่บังคับ)'
          }
        ]);
        
        // สร้าง sheet ตัวอย่างสำหรับการนำเข้ารายการโอนงบประมาณ
        const wsTransferTemplate = XLSX.utils.json_to_sheet([
          {
            projectName: 'โครงการต้นทาง',
            date: new Date().toISOString().split('T')[0],
            description: '[โอนงบประมาณ] โอนไปยังโครงการ: โครงการปลายทาง (เงินอุดหนุนรายหัว)',
            amount: 10000,
            budgetCategory: 'SUBSIDY',
            note: 'ตัวอย่างการโอนงบประมาณไปยังโครงการอื่น',
            isTransfer: true,
            isTransferIn: false,
            transferToProjectName: 'โครงการปลายทาง',
            transferToCategory: 'SUBSIDY'
          },
          {
            projectName: 'โครงการปลายทาง',
            date: new Date().toISOString().split('T')[0],
            description: '[โอนงบประมาณ] รับจากโครงการ: โครงการต้นทาง (เงินอุดหนุนรายหัว)',
            amount: 10000,
            budgetCategory: 'SUBSIDY',
            note: 'ตัวอย่างการรับโอนงบประมาณจากโครงการอื่น',
            isTransfer: true,
            isTransferIn: true,
            transferFromProjectName: 'โครงการต้นทาง',
            transferFromCategory: 'SUBSIDY'
          }
        ]);
        
        // สร้าง sheet คำอธิบายรายการ
        const wsTransactionDescription = XLSX.utils.aoa_to_sheet([
          ['คอลัมน์', 'คำอธิบาย', 'ตัวอย่าง', 'ข้อควรระวัง'],
          ['projectName', 'ชื่อโครงการ', 'โครงการพัฒนาคุณภาพการศึกษา', 'ต้องตรงกับชื่อโครงการที่มีอยู่ในระบบ'],
          ['date', 'วันที่ทำรายการ', '2023-02-15', 'ต้องเป็นรูปแบบ YYYY-MM-DD'],
          ['description', 'รายละเอียดรายการ', 'จัดซื้อวัสดุการเรียน', 'ควรระบุรายละเอียดให้ชัดเจน'],
          ['amount', 'จำนวนเงิน (บาท)', '5000', 'ต้องเป็นตัวเลขเท่านั้น ไม่มีเครื่องหมายคอมม่า'],
          ['budgetCategory', 'หมวดงบประมาณ', 'SUBSIDY', 'ต้องเป็นรหัสหมวดงบประมาณที่มีในระบบ เช่น SUBSIDY, DEVELOPMENT, ฯลฯ'],
          ['note', 'หมายเหตุ', 'ค่าใช้จ่ายสำหรับการจัดซื้อวัสดุ', 'ข้อมูลเพิ่มเติมเกี่ยวกับรายการ (ไม่บังคับ)'],
          ['isTransfer', 'เป็นรายการโอนงบประมาณหรือไม่', 'true', 'ระบุ true หากเป็นรายการโอนงบประมาณ'],
          ['isTransferIn', 'เป็นการรับโอนหรือไม่', 'true', 'ระบุ true หากเป็นการรับโอน, false หากเป็นการโอนออก'],
          ['transferToProjectName', 'ชื่อโครงการปลายทาง', 'โครงการพัฒนา', 'ใช้กับการโอนออก (isTransferIn = false)'],
          ['transferToCategory', 'หมวดงบประมาณปลายทาง', 'SUBSIDY', 'ใช้กับการโอนออก (isTransferIn = false)'],
          ['transferFromProjectName', 'ชื่อโครงการต้นทาง', 'โครงการพัฒนา', 'ใช้กับการรับโอน (isTransferIn = true)'],
          ['transferFromCategory', 'หมวดงบประมาณต้นทาง', 'SUBSIDY', 'ใช้กับการรับโอน (isTransferIn = true)']
        ]);

        // เพิ่ม sheets ทั้งหมดเข้าไปในไฟล์ Excel
        XLSX.utils.book_append_sheet(wb, wsTransactions, "รายการทั้งหมด");
        XLSX.utils.book_append_sheet(wb, wsTransactionTemplate, "แบบฟอร์มรายการ");
        XLSX.utils.book_append_sheet(wb, wsTransferTemplate, "แบบฟอร์มโอนงบ");
        XLSX.utils.book_append_sheet(wb, wsTransactionDescription, "คำอธิบายรายการ");
      }

      // Add all sheets to the workbook
      XLSX.utils.book_append_sheet(wb, wsImportFormat, "นำเข้าโครงการ"); 
      XLSX.utils.book_append_sheet(wb, ws, "โครงการทั้งหมด");
      XLSX.utils.book_append_sheet(wb, wsTemplate, "แบบฟอร์มโครงการ");
      XLSX.utils.book_append_sheet(wb, wsDescription, "คำอธิบายโครงการ");
      
      // Generate file name with current date and time
      const now = new Date();
      const formattedDate = now.toISOString().split('T')[0];
      const formattedTime = now.toTimeString().split(' ')[0].replace(/:/g, '-');
      const fileName = `budget_control_export_${formattedDate}_${formattedTime}.xlsx`;
      
      // Save file
      XLSX.writeFile(wb, fileName);
      
      alert(`ส่งออกข้อมูลสำเร็จ: ${fileName} (${projects.length} โครงการ${transactions ? ', ' + transactions.length + ' รายการ' : ''})`);
    } catch (error: any) {
      console.error('Error exporting projects:', error);
      alert(`เกิดข้อผิดพลาดในการส่งออกข้อมูล: ${error?.message || 'Unknown error'}`);
    }
  };

  return (
    <Box>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
        <Typography variant="h4">จัดการโครงการ</Typography>
        <Box>
          <input
            type="file"
            accept=".xlsx,.xls"
            style={{ display: 'none' }}
            ref={fileInputRef}
            onChange={handleFileUpload}
          />
          <Button
            variant="outlined"
            onClick={() => fileInputRef.current?.click()}
            sx={{ mr: 2 }}
          >
            นำเข้าโครงการจาก Excel
          </Button>
          <Button
            variant="outlined"
            color="secondary"
            startIcon={<FileDownloadIcon />}
            onClick={handleExportProjects}
            sx={{ mr: 2 }}
          >
            ส่งออกโครงการ
          </Button>
          <Button
            variant="contained"
            color="primary"
            startIcon={<AddIcon />}
            onClick={() => handleOpenForm()}
          >
            เพิ่มโครงการ
          </Button>
        </Box>
      </Box>

      <Card elevation={3} sx={{ mb: 4 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>ภาพรวมโครงการ</Typography>
          <Grid container spacing={3}>
            <Grid item xs={12} md={4}>
              <Paper sx={{ p: 2, bgcolor: 'primary.light', color: 'white' }}>
                <Typography variant="subtitle2">จำนวนโครงการทั้งหมด</Typography>
                <Typography variant="h4">{projects.length}</Typography>
              </Paper>
            </Grid>
            <Grid item xs={12} md={4}>
              <Paper sx={{ p: 2, bgcolor: 'success.light', color: 'white' }}>
                <Typography variant="subtitle2">งบประมาณรวม</Typography>
                <Typography variant="h4">{totalBudget.toLocaleString()} บาท</Typography>
              </Paper>
            </Grid>
            <Grid item xs={12} md={4}>
              <Paper sx={{ p: 2, bgcolor: 'info.light', color: 'white' }}>
                <Typography variant="subtitle2">จำนวนกลุ่มงาน</Typography>
                <Typography variant="h4">{workGroups.length}</Typography>
              </Paper>
            </Grid>
          </Grid>
        </CardContent>
      </Card>

      <Card elevation={3}>
        <CardContent>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
            <Typography variant="h6">รายการโครงการ</Typography>
            <TextField
              select
              label="กรองตามกลุ่มงาน"
              value={filterWorkGroup}
              onChange={(e) => setFilterWorkGroup(e.target.value)}
              size="small"
              sx={{ minWidth: 200 }}
            >
              <MenuItem value="all">ทั้งหมด</MenuItem>
              {Object.entries(workGroupCustomColors).map(([key, colors]) => (
                <MenuItem key={key} value={key} sx={{ 
                  display: 'flex',
                  alignItems: 'center',
                  gap: 1
                }}>
                  <Box 
                    sx={{ 
                      width: 16, 
                      height: 16, 
                      borderRadius: '50%', 
                      display: 'inline-block',
                      bgcolor: colors.backgroundColor
                    }} 
                  />
                  {getWorkGroupLabel(key)}
                </MenuItem>
              ))}
            </TextField>
          </Box>

          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>ชื่อโครงการ</TableCell>
                  <TableCell>กลุ่มงาน</TableCell>
                  <TableCell align="right">งบประมาณ</TableCell>
                  <TableCell>ผู้รับผิดชอบ</TableCell>
                  <TableCell>สถานะ</TableCell>
                  <TableCell align="center">จัดการ</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {paginatedProjects.map((project) => (
                  <TableRow key={project.id} hover>
                    <TableCell>
                      <Typography variant="body1" fontWeight="medium">
                        {project.name}
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        {project.description}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Chip 
                        label={getWorkGroupLabel(project.workGroup)}
                        sx={{ 
                          color: workGroupCustomColors[project.workGroup]?.color,
                          bgcolor: workGroupCustomColors[project.workGroup]?.backgroundColor,
                          borderColor: workGroupCustomColors[project.workGroup]?.borderColor,
                        }}
                        size="small"
                        variant="filled"
                      />
                    </TableCell>
                    <TableCell align="right">
                      <Typography variant="body1">
                        {project.budget.toLocaleString()} บาท
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body1">
                        {project.responsiblePerson}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Chip 
                        label={project.status === 'active' ? 'ดำเนินการ' : 'เสร็จสิ้น'} 
                        color={project.status === 'active' ? 'success' : 'default'}
                        size="small"
                      />
                    </TableCell>
                    <TableCell align="center">
                      <Tooltip title="แก้ไข">
                        <IconButton 
                          size="small" 
                          color="primary" 
                          onClick={() => handleOpenForm(project)}
                        >
                          <EditIcon />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title="ลบ">
                        <IconButton 
                          size="small" 
                          color="error" 
                          onClick={() => handleDelete(project.id)}
                        >
                          <DeleteIcon />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination
            rowsPerPageOptions={[5, 10, 25]}
            component="div"
            count={filteredProjects.length}
            rowsPerPage={rowsPerPage}
            page={page}
            onPageChange={handleChangePage}
            onRowsPerPageChange={handleChangeRowsPerPage}
            labelRowsPerPage="จำนวนแถวต่อหน้า:"
          />
        </CardContent>
      </Card>

      <Dialog open={openForm} onClose={handleCloseForm} maxWidth="md" fullWidth>
        <DialogTitle>
          {selectedProject ? 'แก้ไขโครงการ' : 'เพิ่มโครงการใหม่'}
        </DialogTitle>
        <DialogContent>
          <ProjectForm
            initialData={selectedProject}
            onSubmit={handleSubmit}
            onCancel={handleCloseForm}
          />
        </DialogContent>
      </Dialog>
    </Box>
  );
};

export default ProjectManagement;