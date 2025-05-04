import React, { useState } from 'react';
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
  DialogActions,
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
  Select,
  FormControl,
  InputLabel,
  InputAdornment,
  SelectChangeEvent,
} from '@mui/material';
import {
  Add as AddIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  SwapHoriz as SwapHorizIcon,
} from '@mui/icons-material';
import { Project, Transaction, BudgetCategory, BudgetCategoryType } from '../types';
import { format } from 'date-fns';
import { th } from 'date-fns/locale';
import { useBudget } from '../contexts/BudgetContext';
import BudgetTransfer from './BudgetTransfer';

interface CustomChipColorProps {
  color: string;
  backgroundColor: string;
  borderColor: string;
}

const BudgetControl: React.FC = () => {
  const {
    projects,
    transactions,
    addTransaction,
    deleteTransaction,
    addProject,
    updateProject,
    updateTransaction,
  } = useBudget();
  const [selectedProject, setSelectedProject] = useState<string>('');
  const [selectedWorkGroup, setSelectedWorkGroup] = useState<string>('all');
  const [openForm, setOpenForm] = useState(false);
  const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null);
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(10);
  const [showProjectDetails, setShowProjectDetails] = useState(false);
  const [newTransaction, setNewTransaction] = useState<Omit<Transaction, 'id'>>({
    projectId: '',
    date: new Date().toISOString().split('T')[0],
    description: '',
    amount: 0,
    budgetCategory: 'SUBSIDY',
    note: '',
  });
  const [showTransferDialog, setShowTransferDialog] = useState(false);
  const [openDialog, setOpenDialog] = useState<boolean>(false);
  const [newProject, setNewProject] = useState<Omit<Project, 'id'>>({
    name: '',
    budget: 0,
    workGroup: 'academic',
    responsiblePerson: '',
    description: '',
    startDate: new Date().toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0],
    budgetCategories: [{
      category: 'SUBSIDY',
      amount: 0,
      description: 'เงินอุดหนุนรายหัว'
    }],
    status: 'active'
  });
  const [selectedCategory, setSelectedCategory] = useState<BudgetCategoryType>('SUBSIDY');

  const workGroupCustomColors: Record<string, CustomChipColorProps> = {
    academic: { color: '#ffffff', backgroundColor: '#1976d2', borderColor: '#1976d2' }, // สีน้ำเงิน
    budget: { color: '#ffffff', backgroundColor: '#9c27b0', borderColor: '#9c27b0' }, // สีม่วง
    hr: { color: '#ffffff', backgroundColor: '#ff9800', borderColor: '#ff9800' }, // สีส้ม
    general: { color: '#ffffff', backgroundColor: '#2e7d32', borderColor: '#2e7d32' }, // สีเขียวเข้ม
    other: { color: '#ffffff', backgroundColor: '#424242', borderColor: '#424242' } // สีเทาเข้ม
  };

  const handleOpenForm = (transaction?: Transaction) => {
    if (transaction) {
      setSelectedTransaction(transaction);
      setSelectedProject(transaction.projectId);
      setNewTransaction({
        projectId: transaction.projectId,
        date: transaction.date,
        description: transaction.description,
        amount: transaction.amount,
        budgetCategory: transaction.budgetCategory,
        note: transaction.note || '',
      });
      setOpenForm(true);
    } else {
      if (!selectedProject) {
        alert('กรุณาเลือกโครงการก่อนเพิ่มรายการ');
        return;
      }
      
      const project = projects.find(p => p.id === selectedProject);
      if (!project) {
        alert('ไม่พบโครงการที่เลือก');
        return;
      }

      // Set the first available budget category as default
      let defaultCategory: BudgetCategoryType = 'SUBSIDY';
      
      // Only try to access budgetCategories if it's a valid array
      if (project.budgetCategories && Array.isArray(project.budgetCategories) && project.budgetCategories.length > 0) {
        defaultCategory = project.budgetCategories[0].category;
      }
      
      setSelectedTransaction(null);
      setNewTransaction({
        projectId: selectedProject,
        date: new Date().toISOString().split('T')[0],
        description: '',
        amount: 0,
        budgetCategory: defaultCategory,
        note: '',
      });
      setOpenForm(true);
    }
  };

  const handleCloseForm = () => {
    setOpenForm(false);
    setSelectedTransaction(null);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedProject) {
      alert('กรุณาเลือกโครงการก่อนบันทึกรายการ');
      return;
    }
    if (selectedTransaction) {
      // Update existing transaction
      console.log('Updating transaction:', selectedTransaction.id);
      updateTransaction(selectedTransaction.id, newTransaction);
    } else {
      // Add new transaction
      console.log('Adding new transaction');
      addTransaction(newTransaction);
    }
    setNewTransaction({
      projectId: selectedProject,
      date: new Date().toISOString().split('T')[0],
      description: '',
      amount: 0,
      budgetCategory: 'SUBSIDY',
      note: '',
    });
    setOpenForm(false);
  };

  const handleDelete = (id: string) => {
    if (window.confirm('คุณแน่ใจหรือไม่ที่จะลบรายการนี้?')) {
      deleteTransaction(id);
    }
  };

  const handleChangePage = (event: unknown, newPage: number) => {
    setPage(newPage);
  };

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10));
    setPage(0);
  };

  const handleAmountChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    // กรองให้กรอกได้เฉพาะตัวเลข
    let rawValue = e.target.value.replace(/[^0-9]/g, '');
    
    // กำจัดเลข 0 นำหน้า - ตัดเลข 0 ออกทั้งหมดก่อน แล้วค่อยใส่กลับมา
    if (rawValue.length > 1 && rawValue.charAt(0) === '0') {
      rawValue = rawValue.replace(/^0+/, '');
    }
    
    // แปลงเป็นตัวเลข
    const numValue = rawValue === '' ? 0 : parseInt(rawValue, 10);
    
    setNewTransaction({
      ...newTransaction,
      amount: numValue
    });
  };

  const handleBudgetCategoryChange = (event: SelectChangeEvent<BudgetCategoryType>) => {
    setNewTransaction({
      ...newTransaction,
      budgetCategory: event.target.value as BudgetCategoryType
    });
  };

  const handleCategoryChange = (event: SelectChangeEvent<BudgetCategoryType>) => {
    const category = event.target.value as BudgetCategoryType;
    setSelectedCategory(category);
    
    // อัปเดต budgetCategories ของโครงการใหม่
    const updatedCategories = [...newProject.budgetCategories];
    
    // ตรวจสอบว่ามีหมวดนี้อยู่แล้วหรือไม่
    const existingCategoryIndex = updatedCategories.findIndex(c => c.category === category);
    
    if (existingCategoryIndex === -1) {
      // ถ้ายังไม่มีหมวดนี้ ให้เพิ่มเข้าไป
      updatedCategories.push({
        category: category,
        amount: newProject.budget,
        description: BudgetCategory[category]
      });
    } else {
      // ถ้ามีอยู่แล้ว อัปเดตจำนวนเงิน
      updatedCategories[existingCategoryIndex].amount = newProject.budget;
    }
    
    setNewProject({ ...newProject, budgetCategories: updatedCategories });
  };
  
  const handleProjectBudgetChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    // กรองให้กรอกได้เฉพาะตัวเลข
    let rawValue = e.target.value.replace(/[^0-9]/g, '');
    
    // กำจัดเลข 0 นำหน้า - ตัดเลข 0 ออกทั้งหมดก่อน แล้วค่อยใส่กลับมา
    if (rawValue.length > 1 && rawValue.charAt(0) === '0') {
      rawValue = rawValue.replace(/^0+/, '');
    }
    
    // แปลงเป็นตัวเลข
    const numValue = rawValue === '' ? 0 : parseInt(rawValue, 10);
    
    // อัปเดตงบประมาณหลัก
    setNewProject(prev => {
      // อัปเดตหมวดงบประมาณที่เลือกในขณะนี้
      const updatedCategories = [...prev.budgetCategories];
      const existingCategoryIndex = updatedCategories.findIndex(c => c.category === selectedCategory);
      
      if (existingCategoryIndex !== -1) {
        updatedCategories[existingCategoryIndex].amount = numValue;
      } else if (numValue > 0) {
        // ถ้ายังไม่มีหมวดงบประมาณนี้และจำนวนเงินมากกว่า 0 ให้เพิ่มเข้าไป
        updatedCategories.push({
          category: selectedCategory,
          amount: numValue,
          description: BudgetCategory[selectedCategory]
        });
      }
      
      return { ...prev, budget: numValue, budgetCategories: updatedCategories };
    });
  };

  const filteredProjects = selectedWorkGroup === 'all'
    ? projects
    : projects.filter(project => project.workGroup === selectedWorkGroup);

  // Fix: When no project is selected, show all transactions from the filtered projects
  const filteredTransactions = selectedProject
    ? transactions.filter(t => t.projectId === selectedProject)
    : transactions;

  // Show all transactions including transfers
  const paginatedTransactions = filteredTransactions
    .sort((a, b) => {
      // First sort by date
      const dateComparison = new Date(b.date).getTime() - new Date(a.date).getTime();
      if (dateComparison !== 0) return dateComparison;
      
      // If dates are the same, sort by id (assuming newer transactions have higher/larger IDs)
      // Convert IDs to numbers if they're numeric, otherwise compare as strings
      const aIdNum = parseInt(a.id);
      const bIdNum = parseInt(b.id);
      
      if (!isNaN(aIdNum) && !isNaN(bIdNum)) {
        return bIdNum - aIdNum; // Newer transactions (higher IDs) first
      }
      
      // If IDs are not numeric or mixed, compare as strings
      return b.id.localeCompare(a.id);
    })
    .slice(
    page * rowsPerPage,
    page * rowsPerPage + rowsPerPage
  );

  const calculateRemainingBudget = () => {
    if (!selectedProject) {
      console.log('No project selected');
      return { 
        total: 0, 
        spent: 0, 
        remaining: 0, 
        percentage: 0, 
        byCategory: {},
        transferInTotal: 0,
        transferOutTotal: 0
      };
    }
    
    const project = projects.find(p => p.id === selectedProject);
    if (!project) {
      console.log('Project not found:', selectedProject);
      return { 
        total: 0, 
        spent: 0, 
        remaining: 0, 
        percentage: 0, 
        byCategory: {},
        transferInTotal: 0,
        transferOutTotal: 0
      };
    }
    
    console.log('Project found:', project);
    
    // Get all transactions for this project
    const projectTransactions = transactions.filter(t => t.projectId === selectedProject);
    
    // Separate regular transactions from transfers
    const regularTransactions = projectTransactions.filter(t => !t.isTransfer);
    const transferInTransactions = projectTransactions.filter(t => t.isTransfer && t.isTransferIn);
    const transferOutTransactions = projectTransactions.filter(t => t.isTransfer && !t.isTransferIn);
    
    // Calculate initial budget from project
    let totalBudget = project.budget || 0;
    
    // Add transfer-in amount
    const transferInTotal = transferInTransactions.reduce((sum, t) => sum + (t.amount || 0), 0);
    
    // Subtract transfer-out amount (use absolute value since amount is negative)
    const transferOutTotal = transferOutTransactions.reduce((sum, t) => sum + Math.abs(t.amount || 0), 0);
    
    // Final budget includes transfers
    totalBudget = totalBudget + transferInTotal - transferOutTotal;
    
    // Calculate total spent from regular transactions only
    const totalSpent = regularTransactions.reduce((sum, t) => sum + (t.amount || 0), 0);
    
    const remaining = totalBudget - totalSpent;
    const percentage = totalBudget > 0 ? (totalSpent / totalBudget) * 100 : 0;
    
    // Calculate by category
    const byCategory: Record<BudgetCategoryType, { budget: number, spent: number, remaining: number }> = {} as Record<BudgetCategoryType, { budget: number, spent: number, remaining: number }>;
    
    // Initialize categories from project budget
    if (project.budgetCategories && Array.isArray(project.budgetCategories)) {
      project.budgetCategories.forEach(categoryItem => {
        byCategory[categoryItem.category] = { 
          budget: categoryItem.amount || 0, 
          spent: 0, 
          remaining: categoryItem.amount || 0 
        };
      });
    }
    
    // Adjust category budgets based on transfers
    transferInTransactions.forEach(transaction => {
      const category = transaction.budgetCategory;
      if (!byCategory[category]) {
        byCategory[category] = { budget: 0, spent: 0, remaining: 0 };
      }
      byCategory[category].budget += transaction.amount;
    });
    
    transferOutTransactions.forEach(transaction => {
      const category = transaction.budgetCategory;
      if (!byCategory[category]) {
        byCategory[category] = { budget: 0, spent: 0, remaining: 0 };
      }
      byCategory[category].budget -= Math.abs(transaction.amount);
    });
    
    // Calculate spent by category (only regular transactions)
    regularTransactions.forEach(transaction => {
      const category = transaction.budgetCategory;
      if (!byCategory[category]) {
        byCategory[category] = { budget: 0, spent: 0, remaining: 0 };
      }
      byCategory[category].spent += transaction.amount;
    });
    
    // Calculate remaining budgets by category
    Object.keys(byCategory).forEach(key => {
      const category = key as BudgetCategoryType;
      byCategory[category].remaining = byCategory[category].budget - byCategory[category].spent;
    });
    
    return {
      total: totalBudget,
      spent: totalSpent,
      remaining,
      percentage,
      byCategory,
      transferInTotal,
      transferOutTotal
    };
  };

  const budgetSummary = calculateRemainingBudget();

  const statusTranslations = {
    active: 'ดำเนินการ',
    completed: 'เสร็จสิ้น',
    '': 'ไม่ระบุสถานะ' // Handle empty status
  };

  return (
    <Box>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
        <Typography variant="h4">
          ควบคุมงบประมาณ
        </Typography>
        <Box>
          <Button
            variant="contained"
            color="primary"
            startIcon={<AddIcon />}
            onClick={() => handleOpenForm()}
            sx={{ mr: 1 }}
          >
            เพิ่มรายการ
          </Button>
          <Button
            variant="contained"
            color="secondary"
            startIcon={<SwapHorizIcon />}
            onClick={() => setShowTransferDialog(true)}
            sx={{ mr: 1 }}
          >
            โอนงบประมาณ
          </Button>
          <Button
            variant="outlined"
            color="primary"
            onClick={() => setShowProjectDetails(true)}
          >
            ดูรายละเอียดโครงการ
          </Button>
        </Box>
      </Box>

      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <Card elevation={3} sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="h6" gutterBottom>กรองข้อมูล</Typography>
              <Grid container spacing={2}>
                <Grid item xs={12}>
                  <FormControl fullWidth>
                    <InputLabel>กลุ่มงาน</InputLabel>
                    <Select
                      value={selectedWorkGroup || 'all'}
                      onChange={(e: SelectChangeEvent) => setSelectedWorkGroup(e.target.value)}
                      label="กลุ่มงาน"
                    >
                      <MenuItem value="all">
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                          <Box sx={{ 
                            width: 16, 
                            height: 16, 
                            borderRadius: '50%', 
                            bgcolor: 'grey.300', 
                            mr: 1 
                          }} />
                          ทั้งหมด
                        </Box>
                      </MenuItem>
                      <MenuItem value="academic">
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                          <Box sx={{ 
                            width: 16, 
                            height: 16, 
                            borderRadius: '50%', 
                            bgcolor: workGroupCustomColors.academic.backgroundColor, 
                            mr: 1 
                          }} />
                          กลุ่มงานบริหารวิชาการ
                        </Box>
                      </MenuItem>
                      <MenuItem value="budget">
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                          <Box sx={{ 
                            width: 16, 
                            height: 16, 
                            borderRadius: '50%', 
                            bgcolor: workGroupCustomColors.budget.backgroundColor, 
                            mr: 1 
                          }} />
                          กลุ่มงานงบประมาณ
                        </Box>
                      </MenuItem>
                      <MenuItem value="hr">
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                          <Box sx={{ 
                            width: 16, 
                            height: 16, 
                            borderRadius: '50%', 
                            bgcolor: workGroupCustomColors.hr.backgroundColor, 
                            mr: 1 
                          }} />
                          กลุ่มงานบริหารงานบุคคล
                        </Box>
                      </MenuItem>
                      <MenuItem value="general">
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                          <Box sx={{ 
                            width: 16, 
                            height: 16, 
                            borderRadius: '50%', 
                            bgcolor: workGroupCustomColors.general.backgroundColor, 
                            mr: 1 
                          }} />
                          กลุ่มงานบริหารทั่วไป
                        </Box>
                      </MenuItem>
                      <MenuItem value="other">
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                          <Box sx={{ 
                            width: 16, 
                            height: 16, 
                            borderRadius: '50%', 
                            bgcolor: workGroupCustomColors.other.backgroundColor, 
                            mr: 1 
                          }} />
                          อื่น ๆ
                        </Box>
                      </MenuItem>
                    </Select>
                  </FormControl>
                </Grid>
                <Grid item xs={12}>
                  <FormControl fullWidth>
                    <InputLabel>โครงการ</InputLabel>
                    <Select
                      value={selectedProject || ''}
                      onChange={(e: SelectChangeEvent) => setSelectedProject(e.target.value)}
                      label="โครงการ"
                    >
                      {filteredProjects.map((project) => (
                        <MenuItem key={project.id} value={project.id}>
                          {project.name}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                </Grid>
              </Grid>
            </CardContent>
          </Card>
        </Grid>

        {selectedProject && (
          <Grid item xs={12} md={6}>
            <Card elevation={3} sx={{ mb: 3 }}>
              <CardContent>
                <Typography variant="h6" gutterBottom sx={{ fontWeight: 'bold', color: 'primary.main', borderBottom: '2px solid #f0f0f0', pb: 1 }}>
                  สรุปงบประมาณ
                </Typography>
                <Box sx={{ mb: 3, bgcolor: 'background.paper', borderRadius: 2, p: 2 }}>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1, alignItems: 'center' }}>
                    <Typography variant="body1" fontWeight="medium">การใช้งบประมาณ</Typography>
                    <Typography 
                      variant="body1" 
                      fontWeight="bold" 
                      sx={{ 
                        color: budgetSummary.percentage > 90 ? "error.main" : 
                              budgetSummary.percentage > 70 ? "warning.main" : "success.main"
                      }}
                    >
                      {budgetSummary.percentage.toFixed(1)}%
                    </Typography>
                  </Box>
                  <Box sx={{ position: 'relative', height: 16, borderRadius: 10, overflow: 'hidden', bgcolor: '#f0f0f0' }}>
                    <Box 
                      sx={{ 
                        position: 'absolute', 
                        top: 0, 
                        left: 0, 
                        height: '100%', 
                        width: `${Math.min(budgetSummary.percentage, 100)}%`,
                        background: budgetSummary.percentage > 90 
                          ? 'linear-gradient(90deg, #ff9a9e 0%, #ff5252 100%)' 
                          : budgetSummary.percentage > 70 
                          ? 'linear-gradient(90deg, #ffd86f 0%, #fc6c15 100%)'
                          : 'linear-gradient(90deg, #84fab0 0%, #4389ec 100%)',
                        borderRadius: 10,
                        transition: 'width 0.8s ease-in-out'
                      }}
                    />
                  </Box>
                </Box>
                <Grid container spacing={2}>
                  <Grid item xs={4}>
                    <Paper sx={{ 
                      p: 2, 
                      bgcolor: 'primary.main', 
                      color: 'white',
                      position: 'relative',
                      overflow: 'hidden',
                      borderRadius: 2,
                      boxShadow: '0 4px 20px rgba(0,0,0,0.1)'
                    }}>
                      <Box sx={{ position: 'absolute', top: 0, right: 0, p: 1, opacity: 0.2 }}>
                        <svg width="50" height="50" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                        </svg>
                      </Box>
                      <Typography variant="subtitle2" sx={{ opacity: 0.9 }}>งบประมาณทั้งหมด</Typography>
                      <Typography variant="h5" sx={{ fontWeight: 'bold', mt: 1 }}>{budgetSummary.total.toLocaleString()} บาท</Typography>
                    </Paper>
                  </Grid>
                  <Grid item xs={4}>
                    <Paper sx={{ 
                      p: 2, 
                      bgcolor: 'error.main', 
                      color: 'white',
                      position: 'relative',
                      overflow: 'hidden',
                      borderRadius: 2,
                      boxShadow: '0 4px 20px rgba(0,0,0,0.1)'
                    }}>
                      <Box sx={{ position: 'absolute', top: 0, right: 0, p: 1, opacity: 0.2 }}>
                        <svg width="50" height="50" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91s4.18 1.39 4.18 3.91c-.01 1.83-1.38 2.83-3.12 3.16z"/>
                        </svg>
                      </Box>
                      <Typography variant="subtitle2" sx={{ opacity: 0.9 }}>ใช้จ่ายแล้ว</Typography>
                      <Typography variant="h5" sx={{ fontWeight: 'bold', mt: 1 }}>{budgetSummary.spent.toLocaleString()} บาท</Typography>
                    </Paper>
                  </Grid>
                  <Grid item xs={4}>
                    <Paper sx={{ 
                      p: 2, 
                      bgcolor: 'success.main', 
                      color: 'white',
                      position: 'relative',
                      overflow: 'hidden',
                      borderRadius: 2,
                      boxShadow: '0 4px 20px rgba(0,0,0,0.1)'
                    }}>
                      <Box sx={{ position: 'absolute', top: 0, right: 0, p: 1, opacity: 0.2 }}>
                        <svg width="50" height="50" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                        </svg>
                      </Box>
                      <Typography variant="subtitle2" sx={{ opacity: 0.9 }}>คงเหลือ</Typography>
                      <Typography variant="h5" sx={{ fontWeight: 'bold', mt: 1 }}>{budgetSummary.remaining.toLocaleString()} บาท</Typography>
                    </Paper>
                  </Grid>
                </Grid>

                {/* รายการโอนเงิน */}
                {((budgetSummary.transferInTotal || 0) > 0 || (budgetSummary.transferOutTotal || 0) > 0) && (
                  <Box sx={{ mt: 3 }}>
                    <Typography variant="subtitle2" gutterBottom sx={{ fontWeight: 'bold', color: 'text.secondary' }}>รายการโอนงบประมาณ</Typography>
                    <Grid container spacing={2}>
                      {(budgetSummary.transferInTotal || 0) > 0 && (
                        <Grid item xs={6}>
                          <Paper sx={{ 
                            p: 2, 
                            bgcolor: 'rgba(76, 175, 80, 0.1)',
                            borderRadius: 2,
                            borderLeft: '4px solid #4caf50'
                          }}>
                            <Typography variant="subtitle2" color="success.main" fontWeight="bold">รับโอนงบประมาณ</Typography>
                            <Typography variant="h6" color="success.main">+{(budgetSummary.transferInTotal || 0).toLocaleString()} บาท</Typography>
                          </Paper>
                        </Grid>
                      )}
                      {(budgetSummary.transferOutTotal || 0) > 0 && (
                        <Grid item xs={6}>
                          <Paper sx={{ 
                            p: 2, 
                            bgcolor: 'rgba(244, 67, 54, 0.1)',
                            borderRadius: 2,
                            borderLeft: '4px solid #f44336'
                          }}>
                            <Typography variant="subtitle2" color="error.main" fontWeight="bold">โอนงบประมาณออก</Typography>
                            <Typography variant="h6" color="error.main">-{(budgetSummary.transferOutTotal || 0).toLocaleString()} บาท</Typography>
                          </Paper>
                        </Grid>
                      )}
                    </Grid>
                  </Box>
                )}
              </CardContent>
            </Card>
          </Grid>
        )}

        {selectedProject && Object.keys(budgetSummary.byCategory).length > 0 && (
          <Grid item xs={12}>
            <Card elevation={3} sx={{ mb: 3 }}>
              <CardContent>
                <Typography variant="h6" gutterBottom>รายละเอียดตามหมวดงบประมาณ</Typography>
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>หมวดงบประมาณ</TableCell>
                        <TableCell align="right">งบประมาณ</TableCell>
                        <TableCell align="right">ใช้ไปแล้ว</TableCell>
                        <TableCell align="right">คงเหลือ</TableCell>
                        <TableCell>สถานะ</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {Object.entries(budgetSummary.byCategory).map(([category, data]) => {
                        const percentage = data.budget > 0 ? (data.spent / data.budget) * 100 : 0;
                        const status = percentage >= 100 ? "ใช้งบประมาณครบแล้ว" : 
                                     percentage > 90 ? "ใกล้หมดงบประมาณ" : 
                                     percentage > 70 ? "ใช้งบประมาณมาก" : 
                                     "ปกติ";
                        const statusColor = percentage >= 100 ? "error" : 
                                          percentage > 90 ? "warning" : 
                                          percentage > 70 ? "info" : 
                                          "success";
                        
                        return (
                          <TableRow 
                            key={category}
                            hover
                            onClick={() => {
                              console.log('Clicked on category:', category);
                              console.log('Selected project:', selectedProject);
                              if (selectedProject) {
                                setShowProjectDetails(true);
                              } else {
                                alert('กรุณาเลือกโครงการก่อน');
                              }
                            }}
                            sx={{ cursor: 'pointer' }}
                          >
                            <TableCell>{BudgetCategory[category as BudgetCategoryType]}</TableCell>
                            <TableCell align="right">{data.budget.toLocaleString()} บาท</TableCell>
                            <TableCell align="right">{data.spent.toLocaleString()} บาท</TableCell>
                            <TableCell align="right">{data.remaining.toLocaleString()} บาท</TableCell>
                            <TableCell>
                              <Chip 
                                label={status} 
                                color={statusColor} 
                                size="small" 
                              />
                            </TableCell>
                          </TableRow>
                        );
                      })}
                    </TableBody>
                  </Table>
                </TableContainer>
              </CardContent>
            </Card>
          </Grid>
        )}

        <Grid item xs={12}>
          <Card elevation={3}>
            <CardContent>
              <Typography variant="h6" gutterBottom>รายการธุรกรรม</Typography>
              <TableContainer>
                <Table>
                  <TableHead>
                    <TableRow>
                      <TableCell>วันที่</TableCell>
                      <TableCell>โครงการ</TableCell>
                      <TableCell>รายละเอียด</TableCell>
                      <TableCell>หมวดงบประมาณ</TableCell>
                      <TableCell align="right">จำนวนเงิน</TableCell>
                      <TableCell>หมายเหตุ</TableCell>
                      <TableCell align="center">จัดการ</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {paginatedTransactions.map((transaction) => {
                      const project = projects.find(p => p.id === transaction.projectId);
                      // ตรวจสอบว่าเป็นธุรกรรมการโอนเงินหรือไม่
                      const isTransfer = Boolean(transaction.isTransfer) || 
                                       Boolean(transaction.description?.includes('[โอนงบประมาณ]'));
                      const isPositive = transaction.amount > 0;
                      
                      return (
                        <TableRow 
                          key={`${transaction.id}-${transaction.date}`}
                          hover
                          sx={{ 
                            bgcolor: isTransfer ? (isPositive ? 'rgba(76, 175, 80, 0.1)' : 'rgba(244, 67, 54, 0.1)') : 'inherit'
                          }}
                        >
                          <TableCell>
                            {format(new Date(transaction.date), 'dd MMM yyyy', { locale: th })}
                          </TableCell>
                          <TableCell>
                            <Typography variant="body1">
                              {project?.name || 'ไม่พบโครงการ'}
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                              {project?.workGroup === 'academic' ? 'กลุ่มงานบริหารวิชาการ' :
                               project?.workGroup === 'budget' ? 'กลุ่มงานงบประมาณ' :
                               project?.workGroup === 'hr' ? 'กลุ่มงานบริหารงานบุคคล' :
                               project?.workGroup === 'general' ? 'กลุ่มงานบริหารทั่วไป' : 'อื่น ๆ'}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <Typography variant="body1">
                              {transaction.description}
                            </Typography>
                            {isTransfer && (
                              <Typography variant="body2" color={isPositive ? "success.main" : "error.main"}>
                                {transaction.description?.includes('รับจากโครงการ:') || Boolean(transaction.isTransferIn) ? "โอนงบประมาณเข้า" : "โอนงบประมาณออก"}
                              </Typography>
                            )}
                          </TableCell>
                          <TableCell>
                            <Chip 
                              label={BudgetCategory[transaction.budgetCategory as BudgetCategoryType]} 
                              size="small"
                              color="primary"
                              variant="outlined"
                            />
                          </TableCell>
                          <TableCell align="right">
                            <Typography 
                              variant="body1" 
                              fontWeight="medium"
                              color={isPositive ? "success.main" : "error.main"}
                            >
                              {transaction.amount.toLocaleString()} บาท
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <Typography variant="body2" color="text.secondary">
                              {transaction.note || '-'}
                            </Typography>
                          </TableCell>
                          <TableCell align="center">
                            <Tooltip title="แก้ไข">
                              <IconButton 
                                size="small" 
                                color="primary" 
                                onClick={() => handleOpenForm(transaction)}
                              >
                                <EditIcon />
                              </IconButton>
                            </Tooltip>
                            <Tooltip title="ลบ">
                              <IconButton 
                                size="small" 
                                color="error" 
                                onClick={() => handleDelete(transaction.id)}
                              >
                                <DeleteIcon />
                              </IconButton>
                            </Tooltip>
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              </TableContainer>
              <TablePagination
                rowsPerPageOptions={[5, 10, 25, 50]}
                component="div"
                count={filteredTransactions.length}
                rowsPerPage={rowsPerPage}
                page={page}
                onPageChange={handleChangePage}
                onRowsPerPageChange={handleChangeRowsPerPage}
                labelRowsPerPage="จำนวนแถวต่อหน้า:"
              />
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Dialog open={openForm} onClose={handleCloseForm} maxWidth="sm" fullWidth>
        <DialogTitle>
          {selectedTransaction ? 'แก้ไขรายการ' : 'เพิ่มรายการใหม่'}
        </DialogTitle>
        <DialogContent>
          <Box component="form" onSubmit={handleSubmit} sx={{ mt: 2 }}>
            <Grid container spacing={2}>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="ชื่อโครงการ"
                  value={projects.find(p => p.id === newTransaction.projectId)?.name || 'ไม่พบโครงการ'}
                  InputProps={{
                    readOnly: true,
                  }}
                />
              </Grid>
              <Grid item xs={12}>
              <TextField
                fullWidth
                label="วันที่"
                type="date"
                value={newTransaction.date}
                  onChange={(e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setNewTransaction({ ...newTransaction, date: e.target.value })}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="รายละเอียด"
                value={newTransaction.description}
                  onChange={(e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setNewTransaction({ ...newTransaction, description: e.target.value })}
              />
            </Grid>
              <Grid item xs={12}>
              <TextField
                fullWidth
                label="จำนวนเงิน"
                  type="text"
                  value={newTransaction.amount === 0 ? '' : String(newTransaction.amount)}
                onChange={handleAmountChange}
                  InputProps={{
                    startAdornment: <InputAdornment position="start">฿</InputAdornment>,
                    inputProps: { 
                      style: { 
                        textAlign: 'right'
                      },
                  inputMode: 'numeric',
                      pattern: '[0-9]*'
                    }
                  }}
                  sx={{
                    '& input::-webkit-outer-spin-button, & input::-webkit-inner-spin-button': {
                      '-webkit-appearance': 'none',
                      display: 'none',
                      margin: 0
                    },
                    '& input': {
                      '-moz-appearance': 'textfield',
                      appearance: 'textfield'
                    }
                }}
              />
            </Grid>
              <Grid item xs={12}>
              <FormControl fullWidth>
                <InputLabel>หมวดงบประมาณ</InputLabel>
                <Select
                    value={newTransaction.budgetCategory}
                    onChange={handleBudgetCategoryChange}
                  label="หมวดงบประมาณ"
                >
                    {(() => {
                    const project = projects.find(p => p.id === selectedProject);
                      const budgetCategories = project?.budgetCategories;
                      
                      if (!budgetCategories || !Array.isArray(budgetCategories) || budgetCategories.length === 0) {
                        return Object.values(BudgetCategory).map((category, index) => (
                          <MenuItem key={Object.keys(BudgetCategory)[index]} value={Object.keys(BudgetCategory)[index]}>
                            {category}
                          </MenuItem>
                        ));
                      }

                      return budgetCategories.map((budgetCat) => (
                        <MenuItem key={budgetCat.category} value={budgetCat.category}>
                          {BudgetCategory[budgetCat.category as BudgetCategoryType]}
                      </MenuItem>
                    ));
                  })()}
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="หมายเหตุ"
                value={newTransaction.note}
                  onChange={(e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setNewTransaction({ ...newTransaction, note: e.target.value })}
              />
            </Grid>
          </Grid>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseForm}>ยกเลิก</Button>
          <Button onClick={handleSubmit} variant="contained" color="primary">
            บันทึก
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={showProjectDetails} onClose={() => setShowProjectDetails(false)} maxWidth="lg" fullWidth>
        <DialogTitle>
          รายละเอียดโครงการ
        </DialogTitle>
        <DialogContent>
          {selectedProject ? (
            <Box sx={{ mt: 2 }}>
              <Grid container spacing={3}>
                <Grid item xs={12}>
                  <Typography variant="h6" gutterBottom>
                    {projects.find(p => p.id === selectedProject)?.name}
                  </Typography>
                  <Typography variant="body1" color="text.secondary" paragraph>
                    {projects.find(p => p.id === selectedProject)?.description}
                  </Typography>
                  <Typography variant="body1" color="text.secondary" paragraph>
                    สถานะ: {statusTranslations[projects.find(p => p.id === selectedProject)?.status || ''] || ''}
                  </Typography>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Typography variant="subtitle1" gutterBottom>ข้อมูลทั่วไป</Typography>
                  <TableContainer component={Paper} variant="outlined">
                    <Table size="small">
                      <TableBody>
                        <TableRow>
                          <TableCell component="th" scope="row">ผู้รับผิดชอบ</TableCell>
                          <TableCell>{projects.find(p => p.id === selectedProject)?.responsiblePerson}</TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">กลุ่มงาน</TableCell>
                          <TableCell>
                            {projects.find(p => p.id === selectedProject)?.workGroup === 'academic' ? 'กลุ่มงานบริหารวิชาการ' :
                             projects.find(p => p.id === selectedProject)?.workGroup === 'budget' ? 'กลุ่มงานงบประมาณ' :
                             projects.find(p => p.id === selectedProject)?.workGroup === 'hr' ? 'กลุ่มงานบริหารงานบุคคล' :
                             projects.find(p => p.id === selectedProject)?.workGroup === 'general' ? 'กลุ่มงานบริหารทั่วไป' : 'อื่น ๆ'}
                          </TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">สถานะ</TableCell>
                          <TableCell>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                              <Chip 
                                label={projects.find(p => p.id === selectedProject)?.status === 'active' ? 'ดำเนินการ' : 'เสร็จสิ้น'} 
                                color={projects.find(p => p.id === selectedProject)?.status === 'active' ? 'success' : 'default'}
                                size="small"
                              />
                              <IconButton
                                size="small"
                                onClick={() => {
                                  const project = projects.find(p => p.id === selectedProject);
                                  if (project) {
                                    const newStatus = project.status === 'active' ? 'completed' : 'active';
                                    updateProject(project.id, { ...project, status: newStatus });
                                  }
                                }}
                              >
                                <EditIcon fontSize="small" />
                              </IconButton>
                            </Box>
                          </TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">วันที่เริ่มต้น</TableCell>
                          <TableCell>
                            {format(new Date(projects.find(p => p.id === selectedProject)?.startDate || ''), 'dd MMM yyyy', { locale: th })}
                          </TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">วันที่สิ้นสุด</TableCell>
                          <TableCell>
                            {format(new Date(projects.find(p => p.id === selectedProject)?.endDate || ''), 'dd MMM yyyy', { locale: th })}
                          </TableCell>
                        </TableRow>
                      </TableBody>
                    </Table>
                  </TableContainer>
                </Grid>
                <Grid item xs={12} md={6}>
                  <Typography variant="subtitle1" gutterBottom>สรุปงบประมาณ</Typography>
                  <TableContainer component={Paper} variant="outlined">
                    <Table size="small">
                      <TableBody>
                        <TableRow>
                          <TableCell component="th" scope="row">งบประมาณรวม</TableCell>
                          <TableCell align="right">{budgetSummary.total.toLocaleString()} บาท</TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">ใช้จ่ายแล้ว</TableCell>
                          <TableCell align="right">{budgetSummary.spent.toLocaleString()} บาท</TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">คงเหลือ</TableCell>
                          <TableCell align="right">{budgetSummary.remaining.toLocaleString()} บาท</TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">การใช้งบประมาณ</TableCell>
                          <TableCell align="right">{budgetSummary.percentage.toFixed(1)}%</TableCell>
                        </TableRow>
                        
                        {/* รายการโอนเงิน */}
                        {((budgetSummary.transferInTotal || 0) > 0 || (budgetSummary.transferOutTotal || 0) > 0) && (
                          <TableRow key="transfer-header">
                            <TableCell colSpan={2} sx={{ fontWeight: 'bold', bgcolor: 'background.paper' }}>
                              รายการโอนงบประมาณ
                            </TableCell>
                          </TableRow>
                        )}
                        
                        {(budgetSummary.transferInTotal || 0) > 0 && (
                          <TableRow key="transfer-in">
                            <TableCell component="th" scope="row" sx={{ color: 'success.main' }}>รับโอนงบประมาณ</TableCell>
                            <TableCell align="right" sx={{ color: 'success.main' }}>+{(budgetSummary.transferInTotal || 0).toLocaleString()} บาท</TableCell>
                          </TableRow>
                        )}
                        
                        {(budgetSummary.transferOutTotal || 0) > 0 && (
                          <TableRow key="transfer-out">
                            <TableCell component="th" scope="row" sx={{ color: 'error.main' }}>โอนงบประมาณออก</TableCell>
                            <TableCell align="right" sx={{ color: 'error.main' }}>-{(budgetSummary.transferOutTotal || 0).toLocaleString()} บาท</TableCell>
                          </TableRow>
                        )}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </Grid>
                <Grid item xs={12}>
                  <Typography variant="subtitle1" gutterBottom>รายการเบิกจ่าย</Typography>
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>วันที่</TableCell>
                          <TableCell>รายละเอียด</TableCell>
                          <TableCell>หมวดงบประมาณ</TableCell>
                          <TableCell align="right">จำนวนเงิน</TableCell>
                          <TableCell>หมายเหตุ</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {transactions
                          .filter(t => t.projectId === selectedProject)
                          .sort((a, b) => {
                            // First sort by date
                            const dateComparison = new Date(b.date).getTime() - new Date(a.date).getTime();
                            if (dateComparison !== 0) return dateComparison;
                            
                            // If dates are the same, sort by id (assuming newer transactions have higher/larger IDs)
                            const aIdNum = parseInt(a.id);
                            const bIdNum = parseInt(b.id);
                            
                            if (!isNaN(aIdNum) && !isNaN(bIdNum)) {
                              return bIdNum - aIdNum; // Newer transactions (higher IDs) first
                            }
                            
                            // If IDs are not numeric or mixed, compare as strings
                            return b.id.localeCompare(a.id);
                          })
                          .map((transaction) => (
                            <TableRow key={transaction.id}>
                              <TableCell>
                                {format(new Date(transaction.date), 'dd MMM yyyy', { locale: th })}
                              </TableCell>
                              <TableCell>{transaction.description}</TableCell>
                              <TableCell>
                                <Chip 
                                  label={BudgetCategory[transaction.budgetCategory as BudgetCategoryType]} 
                                  size="small"
                                  color="primary"
                                  variant="outlined"
                                />
                              </TableCell>
                              <TableCell align="right">
                                {transaction.amount.toLocaleString()} บาท
                              </TableCell>
                              <TableCell>{transaction.note || '-'}</TableCell>
                            </TableRow>
                          ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </Grid>
              </Grid>
            </Box>
          ) : (
            <Typography>ไม่พบข้อมูลโครงการ</Typography>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setShowProjectDetails(false)}>ปิด</Button>
        </DialogActions>
      </Dialog>

      {selectedProject && (
        <BudgetTransfer
          open={showTransferDialog}
          onClose={() => setShowTransferDialog(false)}
          projects={projects}
          transactions={transactions}
          onAddTransaction={addTransaction}
          sourceProjectId={selectedProject}
        />
      )}

      <Dialog open={openDialog} onClose={() => setOpenDialog(false)} maxWidth="md" fullWidth>
        <DialogTitle>
          เพิ่มโครงการใหม่
        </DialogTitle>
        <DialogContent>
          <Grid container spacing={2} sx={{ mt: 1 }}>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="ชื่อโครงการ"
                value={newProject.name}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewProject({ ...newProject, name: e.target.value })}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="งบประมาณ"
                type="text"
                value={newProject.budget === 0 ? '' : String(newProject.budget)}
                onChange={handleProjectBudgetChange}
                InputProps={{
                  startAdornment: <InputAdornment position="start">฿</InputAdornment>,
                  inputProps: { 
                    style: { 
                      textAlign: 'right',
                    },
                    inputMode: 'numeric',
                    pattern: '[0-9]*'
                  }
                }}
                sx={{
                  '& input::-webkit-outer-spin-button, & input::-webkit-inner-spin-button': {
                    display: 'none'
                  },
                  '& input': {
                    MozAppearance: 'textfield'
                  }
                }}
              />
            </Grid>
            <Grid item xs={12}>
              <FormControl fullWidth>
                <InputLabel>ประเภทงบประมาณ</InputLabel>
                <Select
                  value={selectedCategory}
                  onChange={handleCategoryChange}
                  label="ประเภทงบประมาณ"
                >
                  {Object.keys(BudgetCategory).map(key => (
                    <MenuItem key={key} value={key}>
                      {BudgetCategory[key as BudgetCategoryType]}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12}>
              <FormControl fullWidth>
                <InputLabel>กลุ่มงาน</InputLabel>
                <Select
                  value={newProject.workGroup || 'academic'}
                  onChange={(e: SelectChangeEvent) => setNewProject({ ...newProject, workGroup: e.target.value as 'academic' | 'budget' | 'hr' | 'general' | 'other' })}
                  label="กลุ่มงาน"
                >
                  <MenuItem value="academic">กลุ่มงานบริหารวิชาการ</MenuItem>
                  <MenuItem value="budget">กลุ่มงานงบประมาณ</MenuItem>
                  <MenuItem value="hr">กลุ่มงานบริหารงานบุคคล</MenuItem>
                  <MenuItem value="general">กลุ่มงานบริหารทั่วไป</MenuItem>
                  <MenuItem value="other">อื่น ๆ</MenuItem>
                </Select>
              </FormControl>
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="ผู้รับผิดชอบ"
                value={newProject.responsiblePerson}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewProject({ ...newProject, responsiblePerson: e.target.value })}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="วันที่เริ่มต้น"
                type="date"
                value={newProject.startDate}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewProject({ ...newProject, startDate: e.target.value })}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="วันที่สิ้นสุด"
                type="date"
                value={newProject.endDate}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewProject({ ...newProject, endDate: e.target.value })}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="สถานะ"
                select
                value={newProject.status}
                onChange={(e: React.ChangeEvent<HTMLTextAreaElement | HTMLInputElement>) => setNewProject({ ...newProject, status: e.target.value as 'active' | 'completed' })}
              >
                <MenuItem value="active">ดำเนินการ</MenuItem>
                <MenuItem value="completed">เสร็จสิ้น</MenuItem>
              </TextField>
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="รายละเอียด"
                value={newProject.description}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewProject({ ...newProject, description: e.target.value })}
              />
            </Grid>
          </Grid>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpenDialog(false)}>ยกเลิก</Button>
          <Button 
            variant="contained" 
            onClick={() => {
              addProject(newProject as Project);
              setOpenDialog(false);
            }}
            disabled={!newProject.name || !newProject.budget || !newProject.workGroup || !newProject.responsiblePerson || !newProject.startDate || !newProject.endDate || !newProject.status || !newProject.description}
          >
            เพิ่ม
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default BudgetControl; 