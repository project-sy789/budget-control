// Component for displaying budget summary across all projects

// Thai: คอมโพเนนต์สำหรับแสดงสรุปงบประมาณรวมทุกโครงการ
// - แสดงภาพรวมรายรับ รายจ่าย และคงเหลือ ของทุกโครงการ
// - อาจมีกราฟหรือตารางสรุปเพิ่มเติม
// - ใช้ข้อมูลจาก BudgetContext

import React, { useEffect, useMemo } from 'react';
import {
    Box,
    Typography,
    Paper,
    Grid,
    CircularProgress,
    Alert,
    Card,
    CardContent
} from '@mui/material';
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

const BudgetSummary: React.FC = () => {
    const { budgetState, fetchProjects } = useBudget();

    // Thai: Fetch โครงการเมื่อคอมโพเนนต์โหลด (ถ้ายังไม่มี)
    useEffect(() => {
        if (budgetState.projects.length === 0) {
            fetchProjects();
        }
    }, [fetchProjects, budgetState.projects.length]);

    // Thai: คำนวณค่าสรุปรวมจากทุกโครงการ
    const overallSummary = useMemo(() => {
        return budgetState.projects.reduce((summary, project) => {
            summary.totalInitialBudget += Number(project.initial_budget || 0);
            summary.totalIncome += Number(project.total_income || 0);
            summary.totalExpense += Number(project.total_expense || 0);
            summary.totalCurrentBalance += Number(project.current_balance || 0);
            return summary;
        }, {
            totalInitialBudget: 0,
            totalIncome: 0,
            totalExpense: 0,
            totalCurrentBalance: 0,
        });
    }, [budgetState.projects]);

    return (
        <Box sx={{ p: 2 }}>
            <Typography variant="h4" gutterBottom>สรุปงบประมาณรวม</Typography>

            {/* Thai: แสดง Loading หรือ Error */} 
            {budgetState.loadingProjects && <CircularProgress sx={{ display: 'block', margin: 'auto', mb: 2 }} />}
            {budgetState.error && <Alert severity="error" sx={{ mb: 2 }}>{budgetState.error}</Alert>}

            {!budgetState.loadingProjects && budgetState.projects.length === 0 && (
                <Typography>ยังไม่มีโครงการให้สรุปข้อมูล</Typography>
            )}

            {/* Thai: แสดงการ์ดสรุปข้อมูลรวม */} 
            {!budgetState.loadingProjects && budgetState.projects.length > 0 && (
                <Grid container spacing={3} sx={{ mb: 3 }}>
                    <Grid item xs={12} sm={6} md={3}>
                        <Card>
                            <CardContent>
                                <Typography color="text.secondary" gutterBottom>
                                    งบประมาณเริ่มต้นรวม
                                </Typography>
                                <Typography variant="h5" component="div">
                                    {formatCurrency(overallSummary.totalInitialBudget)}
                                </Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                    <Grid item xs={12} sm={6} md={3}>
                        <Card>
                            <CardContent>
                                <Typography color="text.secondary" gutterBottom>
                                    รายรับรวม
                                </Typography>
                                <Typography variant="h5" component="div" sx={{ color: 'success.main' }}>
                                    {formatCurrency(overallSummary.totalIncome)}
                                </Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                    <Grid item xs={12} sm={6} md={3}>
                        <Card>
                            <CardContent>
                                <Typography color="text.secondary" gutterBottom>
                                    รายจ่ายรวม
                                </Typography>
                                <Typography variant="h5" component="div" sx={{ color: 'error.main' }}>
                                    {formatCurrency(overallSummary.totalExpense)}
                                </Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                    <Grid item xs={12} sm={6} md={3}>
                        <Card>
                            <CardContent>
                                <Typography color="text.secondary" gutterBottom>
                                    ยอดคงเหลือรวม
                                </Typography>
                                <Typography variant="h5" component="div">
                                    {formatCurrency(overallSummary.totalCurrentBalance)}
                                </Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                </Grid>
            )}

            {/* Thai: แสดงตารางสรุปแยกตามโครงการ */} 
            {!budgetState.loadingProjects && budgetState.projects.length > 0 && (
                <Paper sx={{ p: 2 }}>
                    <Typography variant="h6" gutterBottom>สรุปแยกตามโครงการ</Typography>
                    <Grid container spacing={1} sx={{ borderBottom: '1px solid lightgrey', pb: 1, mb: 1, fontWeight: 'bold' }}>
                        <Grid item xs={12} md={3}>ชื่อโครงการ</Grid>
                        <Grid item xs={6} md={2} sx={{ textAlign: 'right' }}>งบเริ่มต้น</Grid>
                        <Grid item xs={6} md={2} sx={{ textAlign: 'right' }}>รายรับ</Grid>
                        <Grid item xs={6} md={2} sx={{ textAlign: 'right' }}>รายจ่าย</Grid>
                        <Grid item xs={6} md={3} sx={{ textAlign: 'right' }}>คงเหลือ</Grid>
                    </Grid>
                    {budgetState.projects.map((project) => (
                        <Grid container spacing={1} key={project.id} sx={{ borderBottom: '1px solid #eee', py: 1 }}>
                            <Grid item xs={12} md={3}>{project.name}</Grid>
                            <Grid item xs={6} md={2} sx={{ textAlign: 'right' }}>{formatCurrency(project.initial_budget)}</Grid>
                            <Grid item xs={6} md={2} sx={{ textAlign: 'right', color: 'success.main' }}>{formatCurrency(project.total_income)}</Grid>
                            <Grid item xs={6} md={2} sx={{ textAlign: 'right', color: 'error.main' }}>{formatCurrency(project.total_expense)}</Grid>
                            <Grid item xs={6} md={3} sx={{ textAlign: 'right' }}>{formatCurrency(project.current_balance)}</Grid>
                        </Grid>
                    ))}
                </Paper>
            )}

            {/* Potential for adding charts here using Recharts or similar */}
            {/*
            <Box sx={{ mt: 4 }}>
                <Typography variant="h6" gutterBottom>กราฟสรุป (ตัวอย่าง)</Typography>
                <Paper sx={{ p: 2 }}>
                    <Typography color="text.secondary">กราฟแสดงสัดส่วนรายจ่าย หรือแนวโน้มงบประมาณ...</Typography>
                    // Chart component would go here
                </Paper>
            </Box>
            */}
        </Box>
    );
};

export default BudgetSummary;

