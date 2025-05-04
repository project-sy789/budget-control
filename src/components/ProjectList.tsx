import React, { useState } from 'react';
import {
  Box,
  Button,
  Typography,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  IconButton,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  MenuItem,
  Grid,
  Chip,
} from '@mui/material';
import { Edit as EditIcon, Delete as DeleteIcon } from '@mui/icons-material';
import { useNavigate } from 'react-router-dom';
import { Project } from '../types';

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

interface ProjectListProps {
  projects: Project[];
  onDelete: (id: string) => void;
}

const ProjectList: React.FC<ProjectListProps> = ({ projects, onDelete }) => {
  const navigate = useNavigate();
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [projectToDelete, setProjectToDelete] = useState<string | null>(null);
  const [selectedWorkGroup, setSelectedWorkGroup] = useState<string>('all');

  const handleDeleteClick = (projectId: string) => {
    setProjectToDelete(projectId);
    setDeleteDialogOpen(true);
  };

  const handleDeleteConfirm = () => {
    if (projectToDelete) {
      onDelete(projectToDelete);
      setDeleteDialogOpen(false);
      setProjectToDelete(null);
    }
  };

  const handleDeleteCancel = () => {
    setDeleteDialogOpen(false);
    setProjectToDelete(null);
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

  const filteredProjects = selectedWorkGroup === 'all'
    ? projects
    : projects.filter(project => project.workGroup === selectedWorkGroup);

  return (
    <Box>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 3 }}>
        <Typography variant="h5">รายการโครงการ</Typography>
        <Button
          variant="contained"
          color="primary"
          onClick={() => navigate('/add-project')}
        >
          เพิ่มโครงการ
        </Button>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid item xs={12} md={4}>
          <TextField
            fullWidth
            select
            label="กรองตามกลุ่มงาน"
            value={selectedWorkGroup}
            onChange={(e) => setSelectedWorkGroup(e.target.value)}
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
        </Grid>
      </Grid>

      <TableContainer component={Paper}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>ชื่อโครงการ</TableCell>
              <TableCell>ผู้รับผิดชอบ</TableCell>
              <TableCell align="right">งบประมาณ</TableCell>
              <TableCell>หมวดงบประมาณ</TableCell>
              <TableCell>กลุ่มงาน</TableCell>
              <TableCell>วันที่เริ่มต้น</TableCell>
              <TableCell>วันที่สิ้นสุด</TableCell>
              <TableCell align="center">จัดการ</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {filteredProjects.map((project) => (
              <TableRow key={project.id}>
                <TableCell>{project.name}</TableCell>
                <TableCell>{project.responsiblePerson}</TableCell>
                <TableCell align="right">
                  {project.budget.toLocaleString()} บาท
                </TableCell>
                <TableCell>
                  {project.budgetCategories.map((cat, index) => (
                    <div key={index}>
                      {cat.category}: {cat.amount.toLocaleString()} บาท
                    </div>
                  ))}
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
                <TableCell>
                  {new Date(project.startDate).toLocaleDateString('th-TH')}
                </TableCell>
                <TableCell>
                  {new Date(project.endDate).toLocaleDateString('th-TH')}
                </TableCell>
                <TableCell align="center">
                  <IconButton
                    color="primary"
                    onClick={() => navigate(`/edit-project/${project.id}`)}
                  >
                    <EditIcon />
                  </IconButton>
                  <IconButton
                    color="error"
                    onClick={() => handleDeleteClick(project.id)}
                  >
                    <DeleteIcon />
                  </IconButton>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <Dialog open={deleteDialogOpen} onClose={handleDeleteCancel}>
        <DialogTitle>ยืนยันการลบ</DialogTitle>
        <DialogContent>
          <Typography>
            คุณแน่ใจหรือไม่ที่จะลบโครงการนี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleDeleteCancel}>ยกเลิก</Button>
          <Button onClick={handleDeleteConfirm} color="error">
            ลบ
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};

export default ProjectList; 