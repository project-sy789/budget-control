import React, { useState } from 'react';
import {
  Box,
  Typography,
  Grid,
  Card,
  CardContent,
  MenuItem,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TablePagination,
  Chip,
  Select,
  FormControl,
  InputLabel,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Paper,
  IconButton,
  Tooltip,
  CircularProgress
} from '@mui/material';
import { BudgetCategory, BudgetCategoryType, Project } from '../types';
import { useBudget } from '../contexts/BudgetContext';
import { format } from 'date-fns';
import { th } from 'date-fns/locale';
import EditIcon from '@mui/icons-material/Edit';
import InfoIcon from '@mui/icons-material/Info';
import CloseIcon from '@mui/icons-material/Close';

const workGroupTranslations: Record<string, string> = {
  academic: 'กลุ่มงานบริหารวิชาการ',
  budget: 'กลุ่มงานงบประมาณ',
  hr: 'กลุ่มงานบริหารงานบุคคล',
  general: 'กลุ่มงานบริหารทั่วไป',
  other: 'อื่น ๆ'
};

const statusTranslations: Record<string, string> = {
  active: 'ดำเนินการ',
  completed: 'เสร็จสิ้น'
};

// กำหนดสีใหม่ให้กับแต่ละกลุ่มงาน
interface CustomChipColorProps {
  color: string;
  backgroundColor: string;
  borderColor: string;
}

// ใช้แนวทางแบบ custom chip พร้อมสีที่กำหนดเอง
const workGroupCustomColors: Record<string, CustomChipColorProps> = {
  academic: { color: '#ffffff', backgroundColor: '#1976d2', borderColor: '#1976d2' }, // สีน้ำเงิน
  budget: { color: '#ffffff', backgroundColor: '#9c27b0', borderColor: '#9c27b0' }, // สีม่วง
  hr: { color: '#ffffff', backgroundColor: '#ff9800', borderColor: '#ff9800' }, // สีส้ม
  general: { color: '#ffffff', backgroundColor: '#2e7d32', borderColor: '#2e7d32' }, // สีเขียวเข้ม
  other: { color: '#ffffff', backgroundColor: '#424242', borderColor: '#424242' } // สีเทาเข้ม
};

const statusColors: Record<string, 'success' | 'default'> = {
  active: 'success', // Green
  completed: 'default'
};

const BudgetSummary: React.FC = () => {
  const { projects, transactions, updateProject } = useBudget();
  const [selectedWorkGroup, setSelectedWorkGroup] = useState<string>('all');
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(10);
  const [showProjectDetails, setShowProjectDetails] = useState(false);
  const [selectedProject, setSelectedProject] = useState<string>('');
  const [isLoading, setIsLoading] = useState(false);

  const filteredProjects = projects.filter(project => {
    if (selectedWorkGroup !== 'all' && project.workGroup !== selectedWorkGroup) {
      return false;
    }
    if (selectedStatus !== 'all' && project.status !== selectedStatus) {
      return false;
    }
    return true;
  });

  const filteredTransactions = transactions.filter(transaction => {
    const project = projects.find(p => p.id === transaction.projectId);
    return project && filteredProjects.includes(project);
  });

  const calculateBudgetSummary = () => {
    const summary = {
      total: 0,
      spent: 0,
      remaining: 0,
      percentage: 0,
      byCategory: {} as Record<BudgetCategoryType, { budget: number; spent: number; remaining: number }>,
      transferInTotal: 0,
      transferOutTotal: 0
    };

    // Initialize categories with all possible budget categories
    Object.keys(BudgetCategory).forEach(key => {
      const categoryKey = key as BudgetCategoryType;
      summary.byCategory[categoryKey] = {
        budget: 0,
        spent: 0,
        remaining: 0
      };
    });

    // Calculate total budget and spent by category
    filteredProjects.forEach(project => {
      if (Array.isArray(project.budgetCategories)) {
        project.budgetCategories.forEach(category => {
          const categoryKey = category.category as BudgetCategoryType;
          if (categoryKey in summary.byCategory) {
            summary.byCategory[categoryKey].budget += category.amount;
            summary.total += category.amount;
          }
        });
      }
    });

    // Calculate transfers and regular transactions
    const transferIn = filteredTransactions.filter(t => t.isTransfer && t.isTransferIn);
    const transferOut = filteredTransactions.filter(t => t.isTransfer && !t.isTransferIn);
    const regularTransactions = filteredTransactions.filter(t => !t.isTransfer);
    
    // Calculate transfer totals
    summary.transferInTotal = transferIn.reduce((sum, t) => sum + t.amount, 0);
    summary.transferOutTotal = transferOut.reduce((sum, t) => sum + Math.abs(t.amount), 0);
    
    // Adjust category budgets based on transfers
    transferIn.forEach(transaction => {
      const category = transaction.budgetCategory;
      if (category in summary.byCategory) {
        summary.byCategory[category].budget += transaction.amount;
        summary.total += transaction.amount;
      }
    });
    
    transferOut.forEach(transaction => {
      const category = transaction.budgetCategory;
      if (category in summary.byCategory) {
        summary.byCategory[category].budget -= Math.abs(transaction.amount);
        summary.total -= Math.abs(transaction.amount);
      }
    });

    // Calculate spent by category (only regular transactions)
    regularTransactions.forEach(transaction => {
      const category = transaction.budgetCategory;
      if (category in summary.byCategory) {
        summary.byCategory[category].spent += transaction.amount;
        summary.spent += transaction.amount;
      }
    });

    // Calculate remaining and percentage
    summary.remaining = summary.total - summary.spent;
    summary.percentage = summary.total > 0 ? (summary.spent / summary.total) * 100 : 0;

    // Calculate remaining by category
    Object.keys(summary.byCategory).forEach(key => {
      const category = key as BudgetCategoryType;
      summary.byCategory[category].remaining = 
        summary.byCategory[category].budget - summary.byCategory[category].spent;
    });

    return summary;
  };

  const calculateProjectSummary = (project: Project) => {
    const summary = {
      totalBudget: 0,
      totalSpent: 0,
      remaining: 0,
      percentage: 0
    };

    // Calculate initial budget from project's budget categories
    if (project.budgetCategories) {
      project.budgetCategories.forEach(category => {
        if (category.category in BudgetCategory) {
          summary.totalBudget += Number(category.amount) || 0;
        }
      });
    }

      // Get all transactions for this project
      const projectTransactions = transactions.filter(t => t.projectId === project.id);
      
    // Separate regular transactions from transfers
    const regularTransactions = projectTransactions.filter(t => !t.isTransfer);
    const transferInTransactions = projectTransactions.filter(t => t.isTransfer && t.isTransferIn);
    const transferOutTransactions = projectTransactions.filter(t => t.isTransfer && !t.isTransferIn);
    
    // Add transfer-in amount to total budget
    const transferInTotal = transferInTransactions.reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
    
    // Subtract transfer-out amount from total budget
    const transferOutTotal = transferOutTransactions.reduce((sum, t) => sum + Math.abs(Number(t.amount) || 0), 0);
    
    // Adjust total budget with transfers
    summary.totalBudget = summary.totalBudget + transferInTotal - transferOutTotal;
    
    // Calculate total spent from regular transactions only (excluding transfers)
    if (regularTransactions && regularTransactions.length > 0) {
      regularTransactions.forEach(transaction => {
        if (transaction.budgetCategory in BudgetCategory) {
          summary.totalSpent += Number(transaction.amount) || 0;
        }
      });
    }

    summary.remaining = summary.totalBudget - summary.totalSpent;
    summary.percentage = summary.totalBudget > 0 ? (summary.totalSpent / summary.totalBudget) * 100 : 0;

    return summary;
  };

  // Get the main budget summary
  const budgetSummary = calculateBudgetSummary();

  const handleChangePage = (event: unknown, newPage: number) => {
    setPage(newPage);
  };

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement>) => {
    setRowsPerPage(parseInt(event.target.value, 10));
    setPage(0);
  };

  const handleViewProject = (projectId: string) => {
    setIsLoading(true);
    setSelectedProject(projectId);
    setShowProjectDetails(true);
    setTimeout(() => {
      setIsLoading(false);
    }, 500);
  };

  const handleCloseDialog = () => {
    setShowProjectDetails(false);
    setSelectedProject('');
  };

  return (
    <Box sx={{ padding: 3 }}>
      <Grid container spacing={3}>
        <Grid item xs={12}>
          <Card>
            <CardContent>
              <Grid container spacing={2} alignItems="center">
                <Grid item xs={12} md={4}>
                  <FormControl fullWidth>
                    <InputLabel>กลุ่มงาน</InputLabel>
                    <Select
                      value={selectedWorkGroup}
                      onChange={(e) => setSelectedWorkGroup(e.target.value)}
                      label="กลุ่มงาน"
                    >
                      <MenuItem value="all">ทั้งหมด</MenuItem>
                      {Object.entries(workGroupTranslations).map(([key, label]) => (
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
                              bgcolor: workGroupCustomColors[key]?.backgroundColor
                            }} 
                          />
                          {label}
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                </Grid>
                <Grid item xs={12} md={4}>
                  <FormControl fullWidth>
                    <InputLabel>สถานะ</InputLabel>
                    <Select
                      value={selectedStatus}
                      onChange={(e) => setSelectedStatus(e.target.value)}
                      label="สถานะ"
                    >
                      <MenuItem value="all">ทั้งหมด</MenuItem>
                      <MenuItem value="active">ดำเนินการ</MenuItem>
                      <MenuItem value="completed">เสร็จสิ้น</MenuItem>
                    </Select>
                  </FormControl>
                </Grid>
              </Grid>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12}>
          <Card>
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
                  <Card sx={{ 
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
                    <Typography variant="subtitle2" sx={{ opacity: 0.9 }}>งบประมาณรวม</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 'bold', mt: 1 }}>{budgetSummary.total.toLocaleString()} บาท</Typography>
                  </Card>
                </Grid>
                <Grid item xs={4}>
                  <Card sx={{ 
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
                    <Typography variant="subtitle2" sx={{ opacity: 0.9 }}>ใช้ไปแล้ว</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 'bold', mt: 1 }}>{budgetSummary.spent.toLocaleString()} บาท</Typography>
                  </Card>
                </Grid>
                <Grid item xs={4}>
                  <Card sx={{ 
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
                  </Card>
                </Grid>
              </Grid>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                รายละเอียดตามหมวดงบประมาณ
              </Typography>
              <TableContainer>
                <Table>
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
                      if (data.budget > 0) { // Only show categories with budget
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
                        <TableRow key={category}>
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
                      }
                      return null;
                    })}
                  </TableBody>
                </Table>
              </TableContainer>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                สรุปโครงการ
              </Typography>
              <TableContainer>
                <Table>
                  <TableHead>
                    <TableRow>
                      <TableCell>ชื่อโครงการ</TableCell>
                      <TableCell>กลุ่มงาน</TableCell>
                      <TableCell>สถานะ</TableCell>
                      <TableCell align="right">งบประมาณ</TableCell>
                      <TableCell align="right">ใช้ไปแล้ว</TableCell>
                      <TableCell align="right">คงเหลือ</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {filteredProjects
                      .slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage)
                      .map((project) => {
                        const summary = calculateProjectSummary(project);
                        
                      return (
                        <TableRow 
                          key={project.id}
                            hover 
                            onClick={() => handleViewProject(project.id)}
                            sx={{ 
                              cursor: 'pointer',
                              '&:hover': {
                                backgroundColor: 'rgba(0, 0, 0, 0.04)',
                                '& .view-details-icon': {
                                  opacity: 1,
                                }
                              }
                            }}
                          >
                            <TableCell>
                              <Box sx={{ display: 'flex', alignItems: 'center' }}>
                                {project.name}
                                <Tooltip title="คลิกเพื่อดูรายละเอียด">
                                  <IconButton 
                                    size="small" 
                                    className="view-details-icon"
                                    sx={{ 
                                      ml: 1, 
                                      opacity: 0.3,
                                      transition: 'opacity 0.2s'
                                    }}
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      handleViewProject(project.id);
                                    }}
                                  >
                                    <InfoIcon fontSize="small" />
                                  </IconButton>
                                </Tooltip>
                              </Box>
                            </TableCell>
                          <TableCell>
                              <Chip 
                                label={workGroupTranslations[project.workGroup] || project.workGroup} 
                                sx={{ 
                                  color: workGroupCustomColors[project.workGroup]?.color,
                                  bgcolor: workGroupCustomColors[project.workGroup]?.backgroundColor,
                                  borderColor: workGroupCustomColors[project.workGroup]?.borderColor,
                                }}
                                size="small" 
                                variant="filled"
                              />
                          </TableCell>
                          <TableCell>
                              <Chip 
                                label={statusTranslations[project.status] || project.status} 
                                color={statusColors[project.status] || 'default'} 
                                size="small"
                              />
                          </TableCell>
                            <TableCell align="right">{summary.totalBudget.toLocaleString()} บาท</TableCell>
                            <TableCell align="right">{summary.totalSpent.toLocaleString()} บาท</TableCell>
                            <TableCell align="right">{summary.remaining.toLocaleString()} บาท</TableCell>
                        </TableRow>
                      );
                    })}
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
        </Grid>
      </Grid>

      <Dialog 
        open={showProjectDetails} 
        onClose={handleCloseDialog}
        maxWidth="lg" 
        fullWidth
      >
        <DialogTitle>
          รายละเอียดโครงการ
          <IconButton
            aria-label="close"
            onClick={handleCloseDialog}
            sx={{
              position: 'absolute',
              right: 8,
              top: 8,
              color: (theme) => theme.palette.grey[500]
            }}
          >
            <CloseIcon />
          </IconButton>
        </DialogTitle>
        <DialogContent>
          {isLoading ? (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '300px' }}>
              <CircularProgress />
            </Box>
          ) : selectedProject ? (
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
                            {(() => {
                              const workGroup = projects.find(p => p.id === selectedProject)?.workGroup || '';
                              return (
                                <Chip 
                                  label={workGroupTranslations[workGroup] || workGroup} 
                                  sx={{ 
                                    color: workGroupCustomColors[workGroup]?.color,
                                    bgcolor: workGroupCustomColors[workGroup]?.backgroundColor,
                                    borderColor: workGroupCustomColors[workGroup]?.borderColor,
                                  }}
                                  size="small" 
                                  variant="filled"
                                />
                              );
                            })()}
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
                                onClick={(e) => {
                                  e.stopPropagation();
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
                        {(() => {
                          const summary = calculateProjectSummary(projects.find(p => p.id === selectedProject) as Project);
                          return (
                            <>
                        <TableRow>
                          <TableCell component="th" scope="row">งบประมาณรวม</TableCell>
                                <TableCell align="right">{summary.totalBudget.toLocaleString()} บาท</TableCell>
                        </TableRow>
                        <TableRow>
                                <TableCell component="th" scope="row">ใช้จ่ายแล้ว</TableCell>
                                <TableCell align="right">{summary.totalSpent.toLocaleString()} บาท</TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell component="th" scope="row">คงเหลือ</TableCell>
                                <TableCell align="right">{summary.remaining.toLocaleString()} บาท</TableCell>
                              </TableRow>
                              <TableRow>
                                <TableCell component="th" scope="row">การใช้งบประมาณ</TableCell>
                                <TableCell align="right">{summary.percentage.toFixed(1)}%</TableCell>
                              </TableRow>
                            </>
                          );
                        })()}
                        
                        {/* รายการโอนเงิน */}
                            {(() => {
                              const projectTransactions = transactions.filter(t => t.projectId === selectedProject);
                          const transferIn = projectTransactions.filter(t => t.isTransfer && t.isTransferIn);
                          const transferOut = projectTransactions.filter(t => t.isTransfer && !t.isTransferIn);
                          const transferInTotal = transferIn.reduce((sum, t) => sum + t.amount, 0);
                          const transferOutTotal = transferOut.reduce((sum, t) => sum + Math.abs(t.amount), 0);
                          
                          return (
                            <>
                              {(transferInTotal > 0 || transferOutTotal > 0) && (
                                <TableRow key="transfer-header">
                                  <TableCell colSpan={2} sx={{ fontWeight: 'bold', bgcolor: 'background.paper' }}>
                                    รายการโอนงบประมาณ
                                  </TableCell>
                                </TableRow>
                              )}
                              
                              {transferInTotal > 0 && (
                                <TableRow key="transfer-in">
                                  <TableCell component="th" scope="row" sx={{ color: 'success.main' }}>รับโอนงบประมาณ</TableCell>
                                  <TableCell align="right" sx={{ color: 'success.main' }}>+{transferInTotal.toLocaleString()} บาท</TableCell>
                                </TableRow>
                              )}
                              
                              {transferOutTotal > 0 && (
                                <TableRow key="transfer-out">
                                  <TableCell component="th" scope="row" sx={{ color: 'error.main' }}>โอนงบประมาณออก</TableCell>
                                  <TableCell align="right" sx={{ color: 'error.main' }}>-{transferOutTotal.toLocaleString()} บาท</TableCell>
                        </TableRow>
                              )}
                            </>
                          );
                        })()}
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
          <Button onClick={handleCloseDialog}>ปิด</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default BudgetSummary; 