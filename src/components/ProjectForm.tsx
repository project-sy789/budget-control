import React, { useState, useEffect } from 'react';
import {
  Box,
  Button,
  TextField,
  MenuItem,
  Typography,
  Paper,
  Grid,
  IconButton,
  List,
  ListItem,
  ListItemText,
  ListItemSecondaryAction,
  InputAdornment,
} from '@mui/material';
import { DatePicker } from '@mui/x-date-pickers';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import { Project, BudgetCategory, BudgetCategoryType } from '../types';

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

interface ProjectFormProps {
  initialData?: Project | null;
  onSubmit: (project: Omit<Project, 'id'>) => void;
  onCancel: () => void;
}

const ProjectForm: React.FC<ProjectFormProps> = ({ initialData, onSubmit, onCancel }) => {
  const [formData, setFormData] = useState<Omit<Project, 'id'>>({
    name: '',
    budget: 0,
    workGroup: 'academic',
    responsiblePerson: '',
    description: '',
    startDate: new Date().toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0],
    budgetCategories: [],
    status: 'active'
  });

  const [newCategory, setNewCategory] = useState<{
    category: BudgetCategoryType;
    amount: number;
    description?: string;
  }>({
    category: 'SUBSIDY',
    amount: 0,
    description: '',
  });

  useEffect(() => {
    if (initialData) {
      const { id, ...rest } = initialData;
      const budgetCategories = Array.isArray(rest.budgetCategories) ? rest.budgetCategories : [];
      setFormData({
        ...rest,
        budgetCategories
      });
    }
  }, [initialData]);

  const handleInputChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = event.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handleDateChange = (field: 'startDate' | 'endDate') => (date: Date | null) => {
    if (date) {
      setFormData(prev => ({
        ...prev,
        [field]: date.toISOString().split('T')[0]
      }));
    }
  };

  const handleAddCategory = () => {
    if (newCategory.amount <= 0) {
      alert('กรุณากรอกจำนวนเงินให้ถูกต้อง');
      return;
    }

    setFormData((prev) => {
      const updatedCategories = [...prev.budgetCategories, { 
        ...newCategory,
        description: newCategory.description || ''
      }];
      const totalAmount = updatedCategories.reduce((sum, item) => sum + item.amount, 0);
      
      return {
        ...prev,
        budgetCategories: updatedCategories,
        budget: totalAmount
      };
    });

    setNewCategory({
      category: 'SUBSIDY',
      amount: 0,
      description: ''
    });
  };

  const handleRemoveBudgetCategory = (index: number) => {
    setFormData((prev) => {
      const updatedCategories = prev.budgetCategories.filter((_, i) => i !== index);
      const totalAmount = updatedCategories.reduce((sum, item) => sum + item.amount, 0);
      
      return {
        ...prev,
        budgetCategories: updatedCategories,
        budget: totalAmount
      };
    });
  };

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    if (!formData.name || !formData.responsiblePerson || !formData.budget) {
      alert('กรุณากรอกข้อมูลให้ครบถ้วน');
      return;
    }

    const totalAmount = formData.budgetCategories.reduce((sum, item) => sum + item.amount, 0);
    if (totalAmount !== formData.budget) {
      alert('จำนวนเงินรวมของประเภทงบประมาณต้องเท่ากับงบประมาณที่กำหนด');
      return;
    }

    onSubmit(formData);
  };

  return (
    <Box component="form" onSubmit={handleSubmit}>
      <Paper sx={{ p: 3 }}>
        <Grid container spacing={3}>
          <Grid item xs={12} md={6}>
            <TextField
              fullWidth
              required
              name="name"
              label="ชื่อโครงการ"
              value={formData.name}
              onChange={handleInputChange}
            />
          </Grid>
          <Grid item xs={12} md={6}>
            <TextField
              fullWidth
              required
              name="responsiblePerson"
              label="ผู้รับผิดชอบ"
              value={formData.responsiblePerson}
              onChange={handleInputChange}
            />
          </Grid>
          <Grid item xs={12} md={6}>
            <TextField
              fullWidth
              required
              name="budget"
              label="งบประมาณรวม"
              type="number"
              value={formData.budget}
              InputProps={{
                readOnly: true,
              }}
              helperText="งบประมาณรวมจะคำนวณอัตโนมัติจากประเภทงบประมาณ"
            />
          </Grid>
          <Grid item xs={12} md={6}>
            <TextField
              fullWidth
              select
              name="workGroup"
              label="กลุ่มงาน"
              value={formData.workGroup}
              onChange={handleInputChange}
              required
            >
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
                  {key === 'academic' ? 'กลุ่มงานบริหารวิชาการ' :
                   key === 'budget' ? 'กลุ่มงานงบประมาณ' :
                   key === 'hr' ? 'กลุ่มงานบริหารงานบุคคล' :
                   key === 'general' ? 'กลุ่มงานบริหารทั่วไป' : 'อื่น ๆ'}
                </MenuItem>
              ))}
            </TextField>
          </Grid>
          <Grid item xs={12} md={6}>
            <DatePicker
              label="วันที่เริ่มต้น"
              value={formData.startDate ? new Date(formData.startDate) : null}
              onChange={handleDateChange('startDate')}
            />
          </Grid>
          <Grid item xs={12} md={6}>
            <DatePicker
              label="วันที่สิ้นสุด"
              value={formData.endDate ? new Date(formData.endDate) : null}
              onChange={handleDateChange('endDate')}
            />
          </Grid>
          <Grid item xs={12}>
            <TextField
              fullWidth
              multiline
              rows={2}
              name="description"
              label="รายละเอียดโครงการ"
              value={formData.description}
              onChange={handleInputChange}
            />
          </Grid>
          <Grid item xs={12}>
            <TextField
              fullWidth
              select
              name="status"
              label="สถานะ"
              value={formData.status}
              onChange={handleInputChange}
              required
            >
              <MenuItem value="active">ดำเนินการ</MenuItem>
              <MenuItem value="completed">เสร็จสิ้น</MenuItem>
            </TextField>
          </Grid>

          <Grid item xs={12}>
            <Typography variant="h6" gutterBottom>
              ประเภทงบประมาณ
            </Typography>
            <Grid container spacing={2}>
              <Grid item xs={12} md={4}>
                <TextField
                  fullWidth
                  select
                  label="ประเภท"
                  value={newCategory.category}
                  onChange={(e) => setNewCategory(prev => ({ ...prev, category: e.target.value as BudgetCategoryType }))}
                >
                  {Object.keys(BudgetCategory).map((key) => (
                    <MenuItem key={key} value={key}>
                      {BudgetCategory[key as keyof typeof BudgetCategory]}
                    </MenuItem>
                  ))}
                </TextField>
              </Grid>
              <Grid item xs={12} md={4}>
                <TextField
                  fullWidth
                  type="text"
                  label="จำนวนเงิน"
                  value={newCategory.amount === 0 ? '' : String(newCategory.amount)}
                  onChange={(e) => {
                    let rawValue = e.target.value.replace(/[^0-9]/g, '');
                    
                    if (rawValue.length > 1 && rawValue.charAt(0) === '0') {
                      rawValue = rawValue.replace(/^0+/, '');
                    }
                    
                    const numValue = rawValue === '' ? 0 : parseInt(rawValue, 10);
                    
                    setNewCategory(prev => ({ ...prev, amount: numValue }));
                  }}
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
              <Grid item xs={12} md={4}>
                <TextField
                  fullWidth
                  label="รายละเอียด"
                  value={newCategory.description}
                  onChange={(e) => setNewCategory(prev => ({ ...prev, description: e.target.value }))}
                />
              </Grid>
            </Grid>
            <Box sx={{ mt: 2 }}>
              <Button
                variant="contained"
                startIcon={<AddIcon />}
                onClick={handleAddCategory}
              >
                เพิ่มประเภทงบประมาณ
              </Button>
            </Box>
          </Grid>
          <Grid item xs={12}>
            <List>
              {formData.budgetCategories.map((category, index) => (
                <ListItem key={index}>
                  <ListItemText
                    primary={BudgetCategory[category.category]}
                    secondary={`${category.amount.toLocaleString()} บาท${category.description ? ` - ${category.description}` : ''}`}
                  />
                  <ListItemSecondaryAction>
                    <IconButton
                      edge="end"
                      aria-label="delete"
                      onClick={() => handleRemoveBudgetCategory(index)}
                    >
                      <DeleteIcon />
                    </IconButton>
                  </ListItemSecondaryAction>
                </ListItem>
              ))}
            </List>
          </Grid>
        </Grid>
      </Paper>

      <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
        <Button variant="outlined" onClick={onCancel}>
          ยกเลิก
        </Button>
        <Button variant="contained" type="submit">
          {initialData ? 'บันทึกการแก้ไข' : 'เพิ่มโครงการ'}
        </Button>
      </Box>
    </Box>
  );
};

export default ProjectForm; 