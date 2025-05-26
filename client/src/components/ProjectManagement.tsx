// Component for managing projects (CRUD operations)

// Thai: คอมโพเนนต์สำหรับจัดการโครงการ
// - แสดงรายการโครงการทั้งหมด พร้อมข้อมูลสรุปงบประมาณเบื้องต้น
// - มีฟังก์ชันสำหรับ เพิ่ม, แก้ไข, ลบ โครงการ
// - ใช้ข้อมูลและฟังก์ชันจาก BudgetContext

import React, { useEffect, useState } from 'react';
import {
    Box,
    Typography,
    Button,
    CircularProgress,
    Alert,
    List,
    ListItem,
    ListItemText,
    IconButton,
    Paper,
    Grid,
    Dialog,
    DialogActions,
    DialogContent,
    DialogContentText,
    DialogTitle,
    TextField,
    Tooltip
} from '@mui/material';
import { Edit as EditIcon, Delete as DeleteIcon, Add as AddIcon } from '@mui/icons-material';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import { LocalizationProvider, DatePicker } from '@mui/x-date-pickers';
import thLocale from 'date-fns/locale/th';
import { format } from 'date-fns';

import { useBudget } from '../contexts/BudgetContext';
import { Project } from '../types';

// Helper to format currency
// Thai: ฟังก์ชันช่วยสำหรับจัดรูปแบบตัวเลขเป็นสกุลเงินบาท
const formatCurrency = (amount: number | string | undefined | null) => {
    const num = Number(amount);
    if (isNaN(num)) {
        return 'N/A';
    }
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(num);
};

// Initial state for the project form
// Thai: ค่าเริ่มต้นสำหรับฟอร์มข้อมูลโครงการ
const initialProjectFormState: Omit<Project, "id" | "created_at" | "updated_at" | "total_income" | "total_expense" | "current_balance"> = {
    name: '',
    startDate: new Date(),
    endDate: new Date(),
    initial_budget: 0,
};

const ProjectManagement: React.FC = () => {
    const { budgetState, fetchProjects, addProject, updateProject, deleteProject } = useBudget();
    const [openFormDialog, setOpenFormDialog] = useState(false);
    const [openConfirmDialog, setOpenConfirmDialog] = useState(false);
    const [selectedProject, setSelectedProject] = useState<Project | null>(null);
    const [projectToDelete, setProjectToDelete] = useState<Project | null>(null);
    const [formData, setFormData] = useState(initialProjectFormState);
    const [formError, setFormError] = useState<string | null>(null);
    const [isEditMode, setIsEditMode] = useState(false);

    // Thai: Fetch โครงการเมื่อคอมโพเนนต์โหลด
    useEffect(() => {
        fetchProjects();
    }, [fetchProjects]);

    // Thai: เปิด Dialog สำหรับเพิ่มโครงการ
    const handleAddProject = () => {
        setIsEditMode(false);
        setSelectedProject(null);
        setFormData(initialProjectFormState);
        setFormError(null);
        setOpenFormDialog(true);
    };

    // Thai: เปิด Dialog สำหรับแก้ไขโครงการ
    const handleEditProject = (project: Project) => {
        setIsEditMode(true);
        setSelectedProject(project);
        setFormData({
            name: project.name,
            // Ensure dates are Date objects for DatePicker
            startDate: new Date(project.start_date),
            endDate: new Date(project.end_date),
            initial_budget: Number(project.initial_budget),
        });
        setFormError(null);
        setOpenFormDialog(true);
    };

    // Thai: เปิด Dialog ยืนยันการลบโครงการ
    const handleDeleteProject = (project: Project) => {
        setProjectToDelete(project);
        setOpenConfirmDialog(true);
    };

    // Thai: ปิด Dialog ทั้งหมด
    const handleCloseDialogs = () => {
        setOpenFormDialog(false);
        setOpenConfirmDialog(false);
        setProjectToDelete(null);
        setFormError(null);
    };

    // Thai: จัดการการเปลี่ยนแปลงในฟอร์ม
    const handleFormChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = event.target;
        setFormData(prev => ({
            ...prev,
            [name]: name === 'initial_budget' ? Number(value) : value,
        }));
    };

    // Thai: จัดการการเปลี่ยนแปลงวันที่
    const handleDateChange = (name: 'startDate' | 'endDate', date: Date | null) => {
        if (date) {
            setFormData(prev => ({ ...prev, [name]: date }));
        }
    };

    // Thai: จัดการการ Submit ฟอร์ม (เพิ่ม/แก้ไข)
    const handleFormSubmit = async () => {
        setFormError(null);
        // Basic validation
        if (!formData.name || !formData.startDate || !formData.endDate || formData.initial_budget < 0) {
            setFormError("กรุณากรอกข้อมูลให้ครบถ้วน และงบประมาณเริ่มต้นต้องไม่ติดลบ");
            return;
        }
        if (formData.endDate < formData.startDate) {
            setFormError("วันที่สิ้นสุดต้องไม่มาก่อนวันที่เริ่มต้น");
            return;
        }

        try {
            if (isEditMode && selectedProject) {
                // Thai: เรียกฟังก์ชัน updateProject จาก Context
                await updateProject(selectedProject.id, formData);
            } else {
                // Thai: เรียกฟังก์ชัน addProject จาก Context
                await addProject(formData);
            }
            handleCloseDialogs();
        } catch (error: any) {
            console.error("Error submitting project form:", error);
            setFormError(error.message || "เกิดข้อผิดพลาดในการบันทึกข้อมูล");
        }
    };

    // Thai: จัดการการยืนยันลบโครงการ
    const handleConfirmDelete = async () => {
        if (projectToDelete) {
            try {
                // Thai: เรียกฟังก์ชัน deleteProject จาก Context
                await deleteProject(projectToDelete.id);
                handleCloseDialogs();
            } catch (error: any) {
                console.error("Error deleting project:", error);
                // Show error in confirm dialog or main page?
                // The error should be set in budgetState.error by the deleteProject context function.
                // We just need to ensure the dialog is closed.
                console.error("Error deleting project:", error);
                handleCloseDialogs(); // Close dialog even if error occurs
            }
        }
    };

    return (
        <LocalizationProvider dateAdapter={AdapterDateFns} adapterLocale={thLocale}>
            <Box sx={{ p: 2 }}>
                <Typography variant="h4" gutterBottom>จัดการโครงการ</Typography>

                {/* Thai: ปุ่มเพิ่มโครงการใหม่ */} 
                <Button
                    variant="contained"
                    startIcon={<AddIcon />}
                    onClick={handleAddProject}
                    sx={{ mb: 2 }}
                >
                    เพิ่มโครงการใหม่
                </Button>

                {/* Thai: แสดง Loading หรือ Error */} 
                {budgetState.loadingProjects && <CircularProgress sx={{ display: 'block', margin: 'auto' }} />}
                {budgetState.error && <Alert severity="error" sx={{ mb: 2 }}>{budgetState.error}</Alert>}

                {/* Thai: แสดงรายการโครงการ */} 
                {!budgetState.loadingProjects && budgetState.projects.length === 0 && (
                    <Typography>ยังไม่มีโครงการ</Typography>
                )}
                <List component={Paper} elevation={2}>
                    {budgetState.projects.map((project) => (
                        <ListItem
                            key={project.id}
                            secondaryAction={
                                <>
                                    <Tooltip title="แก้ไข">
                                        <IconButton edge="end" aria-label="edit" onClick={() => handleEditProject(project)}>
                                            <EditIcon />
                                        </IconButton>
                                    </Tooltip>
                                    <Tooltip title="ลบ">
                                        <IconButton edge="end" aria-label="delete" onClick={() => handleDeleteProject(project)} sx={{ ml: 1 }}>
                                            <DeleteIcon color="error" />
                                        </IconButton>
                                    </Tooltip>
                                </>
                            }
                            divider
                        >
                            <ListItemText
                                primary={project.name}
                                secondary={
                                    <Grid container spacing={1} sx={{ fontSize: '0.875rem' }}>
                                        <Grid item xs={12} sm={6} md={3}>เริ่มต้น: {format(new Date(project.start_date), 'dd/MM/yyyy', { locale: thLocale })}</Grid>
                                        <Grid item xs={12} sm={6} md={3}>สิ้นสุด: {format(new Date(project.end_date), 'dd/MM/yyyy', { locale: thLocale })}</Grid>
                                        <Grid item xs={12} sm={6} md={3}>งบเริ่มต้น: {formatCurrency(project.initial_budget)}</Grid>
                                        {/* Display calculated balance */} 
                                        <Grid item xs={12} sm={6} md={3}>คงเหลือ: {formatCurrency(project.current_balance)}</Grid>
                                    </Grid>
                                }
                            />
                        </ListItem>
                    ))}
                </List>

                {/* Thai: Dialog สำหรับ เพิ่ม/แก้ไข โครงการ */} 
                <Dialog open={openFormDialog} onClose={handleCloseDialogs}>
                    <DialogTitle>{isEditMode ? 'แก้ไขโครงการ' : 'เพิ่มโครงการใหม่'}</DialogTitle>
                    <DialogContent>
                        <DialogContentText sx={{ mb: 2 }}>
                            กรุณากรอกรายละเอียดโครงการ
                        </DialogContentText>
                        {formError && <Alert severity="error" sx={{ mb: 2 }}>{formError}</Alert>}
                        <TextField
                            autoFocus
                            margin="dense"
                            id="name"
                            name="name"
                            label="ชื่อโครงการ"
                            type="text"
                            fullWidth
                            variant="outlined"
                            value={formData.name}
                            onChange={handleFormChange}
                            required
                        />
                        <DatePicker
                            label="วันที่เริ่มต้น"
                            value={formData.startDate}
                            onChange={(date) => handleDateChange('startDate', date)}
                            renderInput={(params) => <TextField {...params} margin="dense" fullWidth required />}
                        />
                        <DatePicker
                            label="วันที่สิ้นสุด"
                            value={formData.endDate}
                            onChange={(date) => handleDateChange('endDate', date)}
                            renderInput={(params) => <TextField {...params} margin="dense" fullWidth required />}
                            minDate={formData.startDate} // Prevent end date before start date
                        />
                        <TextField
                            margin="dense"
                            id="initial_budget"
                            name="initial_budget"
                            label="งบประมาณเริ่มต้น (บาท)"
                            type="number"
                            fullWidth
                            variant="outlined"
                            value={formData.initial_budget}
                            onChange={handleFormChange}
                            required
                            inputProps={{ min: 0 }} // Prevent negative budget
                        />
                    </DialogContent>
                    <DialogActions>
                        <Button onClick={handleCloseDialogs}>ยกเลิก</Button>
                        <Button onClick={handleFormSubmit} variant="contained" disabled={budgetState.loadingProjects}>
                            {budgetState.loadingProjects ? <CircularProgress size={24} /> : (isEditMode ? 'บันทึกการแก้ไข' : 'เพิ่มโครงการ')}
                        </Button>
                    </DialogActions>
                </Dialog>

                {/* Thai: Dialog ยืนยันการลบ */} 
                <Dialog
                    open={openConfirmDialog}
                    onClose={handleCloseDialogs}
                >
                    <DialogTitle>ยืนยันการลบโครงการ</DialogTitle>
                    <DialogContent>
                        <DialogContentText>
                            คุณแน่ใจหรือไม่ว่าต้องการลบโครงการ "{projectToDelete?.name}"?
                            การดำเนินการนี้จะลบธุรกรรมทั้งหมดที่เกี่ยวข้องกับโครงการนี้ด้วยและไม่สามารถย้อนกลับได้
                        </DialogContentText>
                    </DialogContent>
                    <DialogActions>
                        <Button onClick={handleCloseDialogs}>ยกเลิก</Button>
                        <Button onClick={handleConfirmDelete} color="error" variant="contained" disabled={budgetState.loadingProjects}>
                            {budgetState.loadingProjects ? <CircularProgress size={24} /> : 'ยืนยันการลบ'}
                        </Button>
                    </DialogActions>
                </Dialog>
            </Box>
        </LocalizationProvider>
    );
};

export default ProjectManagement;

