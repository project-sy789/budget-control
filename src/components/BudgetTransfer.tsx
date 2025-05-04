import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Button,
  Grid,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  MenuItem,
  FormControl,
  InputLabel,
  Select,
  Alert,
  Paper,
} from '@mui/material';
import { Project, Transaction, BudgetCategoryType } from '../types';
import { BudgetCategory } from '../types';

interface BudgetTransferProps {
  projects: Project[];
  transactions: Transaction[];
  onAddTransaction: (transaction: Omit<Transaction, 'id'>) => void;
  onClose: () => void;
  open: boolean;
  sourceProjectId: string;
}

interface CategoryBalance {
  budget: number;
  spent: number;
  remaining: number;
}

// Sort transactions by date, newest first
const sortTransactionsByDate = (transactions: Transaction[]) => {
  return [...transactions].sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
};

const BudgetTransfer: React.FC<BudgetTransferProps> = ({
  projects,
  transactions,
  onAddTransaction,
  onClose,
  open,
  sourceProjectId,
}) => {
  const [targetProjectId, setTargetProjectId] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [targetCategory, setTargetCategory] = useState<string>('');
  const [amount, setAmount] = useState<string>('');
  const [description, setDescription] = useState<string>('');
  const [error, setError] = useState<string | null>(null);
  const [sourceProject, setSourceProject] = useState<Project | null>(null);
  const [targetProject, setTargetProject] = useState<Project | null>(null);
  const [sourceCategoryBalance, setSourceCategoryBalance] = useState<CategoryBalance>({ budget: 0, spent: 0, remaining: 0 });
  const [targetCategoryBalances, setTargetCategoryBalances] = useState<Record<string, CategoryBalance>>({});
  const [isDifferentCategory, setIsDifferentCategory] = useState<boolean>(false);
  const [transferComplete, setTransferComplete] = useState<boolean>(false);

  // Reset form when dialog opens
  useEffect(() => {
    if (open) {
      setTargetProjectId('');
      setSelectedCategory('');
      setTargetCategory('');
      setAmount('');
      setDescription('');
      setError(null);
      setTransferComplete(false);
      setTargetCategoryBalances({});
      
      if (sourceProjectId) {
        const project = projects.find(p => p.id === sourceProjectId);
        setSourceProject(project || null);
        
        // Initialize selected category with the first available category
        if (project && project.budgetCategories && Array.isArray(project.budgetCategories) && project.budgetCategories.length > 0) {
          setSelectedCategory(project.budgetCategories[0].category);
        }
      }
    }
  }, [open, sourceProjectId, projects]);

  // Update source project and calculate balances for all categories
  useEffect(() => {
    if (sourceProjectId) {
      const project = projects.find(p => p.id === sourceProjectId);
      setSourceProject(project || null);
      
      if (project) {
        // Calculate balances for all categories in source project
        const balances: Record<string, CategoryBalance> = {};
        
        // Ensure budgetCategories is an array before using forEach
        if (project.budgetCategories && Array.isArray(project.budgetCategories)) {
          project.budgetCategories.forEach(category => {
            const projectTransactions = sortTransactionsByDate(transactions.filter(t => 
              t.projectId === sourceProjectId && 
              t.budgetCategory === category.category
            ));
            const totalSpent = projectTransactions.reduce((sum, t) => sum + t.amount, 0);
            
            balances[category.category] = {
              budget: category.amount,
              spent: totalSpent,
              remaining: category.amount - totalSpent
            };
          });
        }
        
        setSourceCategoryBalance(balances[selectedCategory] || { budget: 0, spent: 0, remaining: 0 });
        
        // Find matching category in source project
        if (project.budgetCategories && Array.isArray(project.budgetCategories)) {
          const matchingCategory = project.budgetCategories.find(c => c.category === selectedCategory);
          if (matchingCategory) {
            // ไม่ต้องเรียก setSelectedCategory ซ้ำถ้าเป็นค่าเดิม
            if (matchingCategory.category !== selectedCategory) {
              setSelectedCategory(matchingCategory.category);
            }
          } else if (project.budgetCategories.length > 0 && !selectedCategory) {
            setSelectedCategory(project.budgetCategories[0].category);
          }
        }
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sourceProjectId, projects, transactions, selectedCategory]);

  // Update target project and calculate balances for all categories
  useEffect(() => {
    if (targetProjectId) {
      const project = projects.find(p => p.id === targetProjectId);
      setTargetProject(project || null);
      
      if (project) {
        // Calculate balances for all categories in target project
        const balances: Record<string, CategoryBalance> = {};
        
        // Ensure budgetCategories is an array before using forEach
        if (project.budgetCategories && Array.isArray(project.budgetCategories)) {
          project.budgetCategories.forEach(category => {
            const projectTransactions = sortTransactionsByDate(transactions.filter(t => 
              t.projectId === targetProjectId && 
              t.budgetCategory === category.category
            ));
            const totalSpent = projectTransactions.reduce((sum, t) => sum + t.amount, 0);
            
            balances[category.category] = {
              budget: category.amount,
              spent: totalSpent,
              remaining: category.amount - totalSpent
            };
          });
        }
        
        setTargetCategoryBalances(balances);
        
        // Find matching category in target project
        if (project.budgetCategories && Array.isArray(project.budgetCategories)) {
          const matchingCategory = project.budgetCategories.find(c => c.category === selectedCategory);
          if (matchingCategory) {
            setTargetCategory(matchingCategory.category);
          } else if (project.budgetCategories.length > 0) {
            setTargetCategory(project.budgetCategories[0].category);
          }
        }
      }
    }
  }, [targetProjectId, projects, selectedCategory, transactions]);

  // Check if categories are different
  useEffect(() => {
    if (selectedCategory && targetCategory) {
      setIsDifferentCategory(selectedCategory !== targetCategory);
    }
  }, [selectedCategory, targetCategory]);

  const handleSubmit = () => {
    if (!targetProjectId) {
      setError('กรุณาเลือกโครงการที่ต้องการโอนงบประมาณไป');
      return;
    }

    if (!selectedCategory) {
      setError('กรุณาเลือกหมวดงบประมาณที่ต้องการโอน');
      return;
    }

    if (!amount || isNaN(Number(amount)) || Number(amount) <= 0) {
      setError('กรุณาระบุจำนวนงบประมาณที่ต้องการโอน');
      return;
    }

    const transferAmount = Number(amount);

    if (transferAmount > sourceCategoryBalance.remaining) {
      setError(`จำนวนงบประมาณที่โอนต้องไม่เกินงบประมาณคงเหลือ (${sourceCategoryBalance.remaining.toLocaleString()} บาท)`);
      return;
    }

    try {
      // Check if target category exists, if not, we'll use the source category
      const finalTargetCategory = targetCategory || selectedCategory;
      
      // Create transaction for source project (negative amount)
      const sourceTransaction: Omit<Transaction, 'id'> = {
        projectId: sourceProjectId,
        date: new Date().toISOString().split('T')[0],
        description: `[โอนงบประมาณ] โอนไปยังโครงการ: ${targetProject?.name || ''} (${BudgetCategory[finalTargetCategory as BudgetCategoryType]}) - ${description}`,
        amount: -transferAmount,
        budgetCategory: selectedCategory as BudgetCategoryType,
        note: `[โอนงบประมาณ] โอนไปยังโครงการ: ${targetProject?.name || ''} (${BudgetCategory[finalTargetCategory as BudgetCategoryType]})`,
        isTransfer: true,
        isTransferIn: false,
        transferToProjectId: targetProjectId,
        transferToCategory: finalTargetCategory as BudgetCategoryType,
        transferFromProjectId: sourceProjectId,
        transferFromCategory: selectedCategory as BudgetCategoryType
      };

      // Create transaction for target project (positive amount)
      const targetTransaction: Omit<Transaction, 'id'> = {
        projectId: targetProjectId,
        date: new Date().toISOString().split('T')[0],
        description: `[โอนงบประมาณ] รับจากโครงการ: ${sourceProject?.name || ''} (${BudgetCategory[selectedCategory as BudgetCategoryType]}) - ${description}`,
        amount: transferAmount,
        budgetCategory: finalTargetCategory as BudgetCategoryType,
        note: `[โอนงบประมาณ] รับจากโครงการ: ${sourceProject?.name || ''} (${BudgetCategory[selectedCategory as BudgetCategoryType]})`,
        isTransfer: true,
        isTransferIn: true,
        transferToProjectId: targetProjectId,
        transferToCategory: finalTargetCategory as BudgetCategoryType,
        transferFromProjectId: sourceProjectId,
        transferFromCategory: selectedCategory as BudgetCategoryType
      };

      // Add both transactions
      console.log('Adding source transaction:', sourceTransaction);
      onAddTransaction(sourceTransaction);
      
      console.log('Adding target transaction:', targetTransaction);
      onAddTransaction(targetTransaction);

      // Set transfer complete flag
      setTransferComplete(true);
      
      // Reset form and close dialog after a short delay
      setTimeout(() => {
        setTargetProjectId('');
        setSelectedCategory('');
        setTargetCategory('');
        setAmount('');
        setDescription('');
        setError(null);
        setTransferComplete(false);
        onClose();
      }, 1500);
    } catch (error) {
      setError('เกิดข้อผิดพลาดในการโอนงบประมาณ กรุณาลองใหม่อีกครั้ง');
      console.error('Transfer error:', error);
    }
  };

  return (
    <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth>
      <DialogTitle>โอนงบประมาณระหว่างโครงการ</DialogTitle>
      <DialogContent>
        <Grid container spacing={3} sx={{ mt: 1 }}>
          {error && (
            <Grid item xs={12}>
              <Alert severity="error">{error}</Alert>
            </Grid>
          )}
          
          {transferComplete && (
            <Grid item xs={12}>
              <Alert severity="success">
                <Typography variant="body1">
                  โอนงบประมาณสำเร็จ! ระบบกำลังอัปเดตข้อมูล...
                </Typography>
              </Alert>
            </Grid>
          )}
          
          {isDifferentCategory && (
            <>
              <Grid item xs={12}>
                <Alert severity="warning">
                  <Typography variant="body2">
                    คุณกำลังโอนงบประมาณระหว่างหมวดงบประมาณที่แตกต่างกัน ({BudgetCategory[selectedCategory as BudgetCategoryType]} → {BudgetCategory[targetCategory as BudgetCategoryType]})
                  </Typography>
                  <Typography variant="body2">
                    งบประมาณที่โอนจะถูกบันทึกแยกตามหมวดงบประมาณต้นทางและปลายทาง ไม่นำมารวมกัน
                  </Typography>
                </Alert>
              </Grid>
            </>
          )}
          
          <Grid item xs={12} md={6}>
            <Paper sx={{ p: 2, bgcolor: 'primary.light', color: 'white' }}>
              <Typography variant="subtitle2">โครงการต้นทาง</Typography>
              <Typography variant="h6">{sourceProject?.name || ''}</Typography>
              <FormControl fullWidth sx={{ mt: 2 }}>
                <InputLabel sx={{ color: 'white' }}>หมวดงบประมาณที่ต้องการโอน</InputLabel>
                <Select
                  value={selectedCategory}
                  onChange={(e) => setSelectedCategory(e.target.value as BudgetCategoryType)}
                  label="หมวดงบประมาณ"
                  sx={{ 
                    color: 'white',
                    '& .MuiOutlinedInput-notchedOutline': { borderColor: 'rgba(255, 255, 255, 0.5)' },
                    '&:hover .MuiOutlinedInput-notchedOutline': { borderColor: 'white' },
                    '& .MuiSvgIcon-root': { color: 'white' },
                    '& .MuiSelect-select': { color: 'white' }
                  }}
                  MenuProps={{
                    PaperProps: {
                      sx: {
                        '& .MuiMenuItem-root': {
                          color: 'text.primary'
                        }
                      }
                    }
                  }}
                >
                  {sourceProject?.budgetCategories && Array.isArray(sourceProject.budgetCategories) && sourceProject.budgetCategories.length > 0 ? (
                    sourceProject.budgetCategories.map((category) => (
                      <MenuItem key={category.category} value={category.category}>
                        {BudgetCategory[category.category as BudgetCategoryType]}
                      </MenuItem>
                    ))
                  ) : (
                    <MenuItem disabled value="">
                      <em>ไม่พบหมวดงบประมาณในโครงการนี้</em>
                    </MenuItem>
                  )}
                </Select>
              </FormControl>
              <Box sx={{ mt: 2 }}>
                {selectedCategory ? (
                  <>
                    <Typography variant="body2">หมวดงบประมาณ: {BudgetCategory[selectedCategory as BudgetCategoryType]}</Typography>
                    <Typography variant="body2">งบประมาณ: {sourceCategoryBalance.budget.toLocaleString()} บาท</Typography>
                    <Typography variant="body2">ใช้ไปแล้ว: {sourceCategoryBalance.spent.toLocaleString()} บาท</Typography>
                    <Typography variant="body2">คงเหลือ: {sourceCategoryBalance.remaining.toLocaleString()} บาท</Typography>
                  </>
                ) : (
                  <Typography variant="body2">กรุณาเลือกหมวดงบประมาณที่ต้องการโอน</Typography>
                )}
              </Box>
            </Paper>
          </Grid>
          
          <Grid item xs={12} md={6}>
            <Paper sx={{ p: 2, bgcolor: 'secondary.light', color: 'white' }}>
              <Typography variant="subtitle2">โครงการปลายทาง</Typography>
              <FormControl fullWidth sx={{ mt: 2 }}>
                <InputLabel sx={{ color: 'white' }}>เลือกโครงการ</InputLabel>
                <Select
                  value={targetProjectId}
                  onChange={(e) => setTargetProjectId(e.target.value)}
                  label="เลือกโครงการ"
                  sx={{ 
                    color: 'white',
                    '& .MuiOutlinedInput-notchedOutline': { borderColor: 'rgba(255, 255, 255, 0.5)' },
                    '&:hover .MuiOutlinedInput-notchedOutline': { borderColor: 'white' },
                  }}
                >
                  {projects
                    .filter(p => p.id !== sourceProjectId)
                    .map((project) => (
                      <MenuItem key={project.id} value={project.id}>
                        {project.name}
                      </MenuItem>
                    ))}
                </Select>
              </FormControl>
              {targetProject && (
                <Box sx={{ mt: 2 }}>
                  <Typography variant="subtitle2">หมวดงบประมาณปลายทาง</Typography>
                  {targetCategory ? (
                    <>
                      <Typography variant="h6">{BudgetCategory[targetCategory as BudgetCategoryType]}</Typography>
                      <Box sx={{ mt: 2 }}>
                        <Typography variant="body2">งบประมาณ: {targetCategoryBalances[targetCategory]?.budget.toLocaleString()} บาท</Typography>
                        <Typography variant="body2">ใช้ไปแล้ว: {targetCategoryBalances[targetCategory]?.spent.toLocaleString()} บาท</Typography>
                        <Typography variant="body2">คงเหลือ: {targetCategoryBalances[targetCategory]?.remaining.toLocaleString()} บาท</Typography>
                      </Box>
                    </>
                  ) : (
                    <Box sx={{ mt: 1 }}>
                      <Typography variant="body2" sx={{ color: 'warning.light' }}>
                        ไม่พบหมวดงบประมาณ "{BudgetCategory[selectedCategory as BudgetCategoryType]}" ในโครงการปลายทาง
                      </Typography>
                      <Typography variant="body2" sx={{ mt: 1 }}>
                        ระบบจะสร้างหมวดงบประมาณ "{BudgetCategory[selectedCategory as BudgetCategoryType]}" ในโครงการปลายทางโดยอัตโนมัติ
                      </Typography>
                      <Typography variant="body2" sx={{ mt: 1, color: 'info.light' }}>
                        หมายเหตุ: งบประมาณที่โอนจะถูกเพิ่มเป็นงบประมาณใหม่ในหมวด "{BudgetCategory[selectedCategory as BudgetCategoryType]}" ของโครงการปลายทาง
                      </Typography>
                    </Box>
                  )}
                  
                  <Typography variant="subtitle2" sx={{ mt: 3 }}>งบประมาณคงเหลือทั้งหมด</Typography>
                  <Box sx={{ mt: 1, maxHeight: '200px', overflowY: 'auto' }}>
                    {Object.entries(targetCategoryBalances)
                      .filter(([category]) => !targetCategory || category !== targetCategory)
                      .map(([category, balance]) => (
                        <Box key={category} sx={{ mb: 1, p: 1, bgcolor: 'rgba(255, 255, 255, 0.1)', borderRadius: 1 }}>
                          <Typography variant="body2" fontWeight="bold">{BudgetCategory[category as BudgetCategoryType]}</Typography>
                          <Typography variant="body2">งบประมาณ: {balance.budget.toLocaleString()} บาท</Typography>
                          <Typography variant="body2">ใช้ไปแล้ว: {balance.spent.toLocaleString()} บาท</Typography>
                          <Typography variant="body2">คงเหลือ: {balance.remaining.toLocaleString()} บาท</Typography>
                        </Box>
                    ))}
                  </Box>
                </Box>
              )}
            </Paper>
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              label="จำนวนงบประมาณที่ต้องการโอน"
              type="text"
              value={amount}
              onChange={(e) => {
                const value = e.target.value.replace(/[^0-9.]/g, '');
                if (value === '' || /^\d*\.?\d*$/.test(value)) {
                  setAmount(value);
                }
              }}
              placeholder="ระบุจำนวนงบประมาณที่ต้องการโอน"
              InputProps={{
                endAdornment: <Typography variant="body2">บาท</Typography>,
                inputProps: { 
                  style: { textAlign: 'right' }
                }
              }}
              helperText={selectedCategory 
                ? `งบประมาณคงเหลือในหมวด ${BudgetCategory[selectedCategory as BudgetCategoryType]}: ${sourceCategoryBalance.remaining.toLocaleString()} บาท`
                : "กรุณาเลือกหมวดงบประมาณก่อน"}
            />
          </Grid>
          
          <Grid item xs={12}>
            <TextField
              fullWidth
              label="รายละเอียด"
              multiline
              rows={2}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="ระบุรายละเอียดการโอนงบประมาณ"
            />
          </Grid>
          
          {isDifferentCategory && (
            <Grid item xs={12}>
              <Paper sx={{ p: 2, bgcolor: 'info.light', color: 'white' }}>
                <Typography variant="subtitle2">หมายเหตุ</Typography>
                <Typography variant="body2">
                  การโอนงบประมาณระหว่างหมวดงบประมาณที่แตกต่างกัน จะมีการบันทึกรายการแยกตามหมวดงบประมาณ:
                </Typography>
                <Box sx={{ mt: 1 }}>
                  <Typography variant="body2">1. โครงการต้นทาง: บันทึกรายการหักงบประมาณจากหมวด "{BudgetCategory[selectedCategory as BudgetCategoryType]}"</Typography>
                  <Typography variant="body2">2. โครงการปลายทาง: บันทึกรายการเพิ่มงบประมาณในหมวด "{BudgetCategory[targetCategory as BudgetCategoryType]}"</Typography>
                </Box>
                <Typography variant="body2" sx={{ mt: 1 }}>
                  งบประมาณที่โอนจะถูกบันทึกแยกตามหมวดงบประมาณต้นทางและปลายทาง ไม่นำมารวมกัน
                </Typography>
              </Paper>
            </Grid>
          )}
        </Grid>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>ยกเลิก</Button>
        <Button 
          variant="contained" 
          onClick={handleSubmit}
          disabled={!targetProjectId || !selectedCategory || !amount || Number(amount) <= 0 || transferComplete}
        >
          {transferComplete ? 'โอนงบประมาณสำเร็จ' : 'โอนงบประมาณ'}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default BudgetTransfer;