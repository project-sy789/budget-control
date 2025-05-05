// Component for managing transactions (CRUD, pagination, export)

// Thai: คอมโพเนนต์สำหรับควบคุมงบประมาณ (จัดการธุรกรรม)
// - แสดงรายการธุรกรรมของโครงการที่เลือก (พร้อม Pagination)
// - มีฟังก์ชันสำหรับ เพิ่ม, แก้ไข, ลบ ธุรกรรม
// - มีปุ่มสำหรับ Export ข้อมูลธุรกรรมของโครงการปัจจุบันเป็น Excel
// - ใช้ข้อมูลและฟังก์ชันจาก BudgetContext และ AuthContext

import React, { useEffect, useState, useCallback } from "react";
import {
  Box,
  Typography,
  Button,
  CircularProgress,
  Alert,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  IconButton,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Grid,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  TextField,
  Tooltip,
  TablePagination,
  SelectChangeEvent,
} from "@mui/material";
import {
  Edit as EditIcon,
  Delete as DeleteIcon,
  Add as AddIcon,
  Download as DownloadIcon,
} from "@mui/icons-material";
import { AdapterDateFns } from "@mui/x-date-pickers/AdapterDateFns";
import {
  LocalizationProvider,
  DatePicker,
  DateTimePicker,
} from "@mui/x-date-pickers";
import thLocale from "date-fns/locale/th";
import { format } from "date-fns";

import { useBudget } from "../contexts/BudgetContext";
import { useAuth } from "../contexts/AuthContext"; // To check user role for add/edit/delete
import { Project, Transaction, TransactionType } from "../types";

// Helper to format currency
const formatCurrency = (amount: number | string | undefined | null) => {
  const num = Number(amount);
  if (isNaN(num)) {
    return "N/A";
  }
  return new Intl.NumberFormat("th-TH", { style: "currency", currency: "THB" }).format(
    num
  );
};

// Initial state for the transaction form
const initialTransactionFormState: Omit<
  Transaction,
  "id" | "created_at" | "updated_at"
> = {
  project_id: "",
  description: "",
  amount: 0,
  type: "expense", // Default to expense
  transaction_date: new Date(),
};

const BudgetControl: React.FC = () => {
  const { authState } = useAuth(); // Get user info
  const {
    budgetState,
    fetchProjects,
    fetchTransactions,
    addTransaction,
    updateTransaction,
    deleteTransaction,
    setCurrentProject,
    exportProjectTransactions,
  } = useBudget();

  const [openFormDialog, setOpenFormDialog] = useState(false);
  const [openConfirmDialog, setOpenConfirmDialog] = useState(false);
  const [selectedTransaction, setSelectedTransaction] =
    useState<Transaction | null>(null);
  const [transactionToDelete, setTransactionToDelete] =
    useState<Transaction | null>(null);
  const [formData, setFormData] = useState(initialTransactionFormState);
  const [formError, setFormError] = useState<string | null>(null);
  const [isEditMode, setIsEditMode] = useState(false);
  const [exporting, setExporting] = useState(false);

  // Thai: Fetch โครงการเมื่อคอมโพเนนต์โหลด (ถ้ายังไม่มี)
  useEffect(() => {
    if (budgetState.projects.length === 0) {
      fetchProjects();
    }
  }, [fetchProjects, budgetState.projects.length]);

  // Thai: Fetch ธุรกรรมเมื่อมีการเปลี่ยนโครงการที่เลือก หรือเปลี่ยนหน้า Pagination
  useEffect(() => {
    if (budgetState.currentProject) {
      fetchTransactions(
        budgetState.currentProject.id,
        budgetState.transactionPagination.currentPage,
        budgetState.transactionPagination.limit
      );
    }
    // Reset form data if project changes
    setFormData((prev) => ({
      ...initialTransactionFormState,
      project_id: budgetState.currentProject?.id || "",
    }));
  }, [
    budgetState.currentProject,
    fetchTransactions,
    budgetState.transactionPagination.currentPage,
    budgetState.transactionPagination.limit,
  ]);

  // Thai: จัดการการเลือกโครงการจาก Dropdown
  const handleProjectChange = (event: SelectChangeEvent<string>) => {
    const projectId = event.target.value;
    const project = budgetState.projects.find((p) => p.id === projectId) || null;
    setCurrentProject(project);
  };

  // Thai: เปิด Dialog สำหรับเพิ่มธุรกรรม
  const handleAddTransaction = () => {
    if (!budgetState.currentProject) {
      setBudgetState((prev) => ({
        ...prev,
        error: "กรุณาเลือกโครงการก่อนเพิ่มธุรกรรม",
      }));
      return;
    }
    setIsEditMode(false);
    setSelectedTransaction(null);
    setFormData({
      ...initialTransactionFormState,
      project_id: budgetState.currentProject.id,
    });
    setFormError(null);
    setOpenFormDialog(true);
  };

  // Thai: เปิด Dialog สำหรับแก้ไขธุรกรรม
  const handleEditTransaction = (transaction: Transaction) => {
    setIsEditMode(true);
    setSelectedTransaction(transaction);
    setFormData({
      project_id: transaction.project_id,
      description: transaction.description,
      amount: Number(transaction.amount),
      type: transaction.type,
      transaction_date: new Date(transaction.transaction_date),
    });
    setFormError(null);
    setOpenFormDialog(true);
  };

  // Thai: เปิด Dialog ยืนยันการลบธุรกรรม
  const handleDeleteTransaction = (transaction: Transaction) => {
    setTransactionToDelete(transaction);
    setOpenConfirmDialog(true);
  };

  // Thai: ปิด Dialog ทั้งหมด
  const handleCloseDialogs = () => {
    setOpenFormDialog(false);
    setOpenConfirmDialog(false);
    setTransactionToDelete(null);
    setFormError(null);
  };

  // Thai: จัดการการเปลี่ยนแปลงในฟอร์ม
  const handleFormChange = (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement> | SelectChangeEvent<TransactionType>) => {
    const { name, value } = event.target;
    setFormData((prev) => ({
      ...prev,
      [name]: name === "amount" ? Number(value) : value,
    }));
  };

  // Thai: จัดการการเปลี่ยนแปลงวันที่และเวลา
  const handleDateTimeChange = (date: Date | null) => {
    if (date) {
      setFormData((prev) => ({ ...prev, transaction_date: date }));
    }
  };

  // Thai: จัดการการ Submit ฟอร์ม (เพิ่ม/แก้ไข)
  const handleFormSubmit = async () => {
    setFormError(null);
    if (
      !formData.project_id ||
      !formData.description ||
      formData.amount <= 0 ||
      !formData.transaction_date
    ) {
      setFormError("กรุณากรอกข้อมูลให้ครบถ้วน และจำนวนเงินต้องมากกว่า 0");
      return;
    }

    try {
      if (isEditMode && selectedTransaction) {
        await updateTransaction(selectedTransaction.id, formData);
      } else {
        await addTransaction(formData);
      }
      handleCloseDialogs();
    } catch (error: any) {
      console.error("Error submitting transaction form:", error);
      setFormError(error.message || "เกิดข้อผิดพลาดในการบันทึกข้อมูล");
    }
  };

  // Thai: จัดการการยืนยันลบธุรกรรม
  const handleConfirmDelete = async () => {
    if (transactionToDelete) {
      try {
        await deleteTransaction(transactionToDelete.id);
        handleCloseDialogs();
      } catch (error: any) {
        console.error("Error deleting transaction:", error);
        setBudgetState((prev) => ({
          ...prev,
          error: error.message || "เกิดข้อผิดพลาดในการลบธุรกรรม",
        }));
        handleCloseDialogs();
      }
    }
  };

  // Thai: จัดการการเปลี่ยนหน้า Pagination
  const handleChangePage = (
    event: React.MouseEvent<HTMLButtonElement> | null,
    newPage: number
  ) => {
    // MUI TablePagination is 0-based, API is 1-based
    fetchTransactions(
      budgetState.currentProject?.id,
      newPage + 1,
      budgetState.transactionPagination.limit
    );
  };

  // Thai: จัดการการเปลี่ยนจำนวนรายการต่อหน้า
  const handleChangeRowsPerPage = (
    event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
  ) => {
    const newLimit = parseInt(event.target.value, 10);
    fetchTransactions(budgetState.currentProject?.id, 1, newLimit); // Go back to page 1
  };

  // Thai: จัดการการ Export ข้อมูล
  const handleExport = async () => {
    if (!budgetState.currentProject) {
      setBudgetState((prev) => ({
        ...prev,
        error: "กรุณาเลือกโครงการก่อนส่งออกข้อมูล",
      }));
      return;
    }
    setExporting(true);
    setBudgetState((prev) => ({ ...prev, error: null })); // Clear previous errors
    try {
      await exportProjectTransactions(budgetState.currentProject.id);
    } catch (error: any) {
      // Error is already set in BudgetContext by exportProjectTransactions
      console.error("Export failed:", error);
    } finally {
      setExporting(false);
    }
  };

  // Placeholder function to update context state (needs proper implementation in context)
  const setBudgetState = (updater: (prevState: typeof budgetState) => typeof budgetState) => {
    // This should ideally call a dispatch or setState exposed by the context
    console.error("Need a way to update BudgetContext state directly");
  };

  return (
    <LocalizationProvider dateAdapter={AdapterDateFns} adapterLocale={thLocale}>
      <Box sx={{ p: 2 }}>
        <Typography variant="h4" gutterBottom>
          ควบคุมงบประมาณ
        </Typography>

        {/* Thai: Dropdown เลือกโครงการ */} 
        <FormControl fullWidth sx={{ mb: 2 }}>
          <InputLabel id="project-select-label">เลือกโครงการ</InputLabel>
          <Select
            labelId="project-select-label"
            id="project-select"
            value={budgetState.currentProject?.id || ""}
            label="เลือกโครงการ"
            onChange={handleProjectChange}
            disabled={budgetState.loadingProjects}
          >
            <MenuItem value="" disabled>
              <em>กรุณาเลือกโครงการ</em>
            </MenuItem>
            {budgetState.projects.map((project) => (
              <MenuItem key={project.id} value={project.id}>
                {project.name}
              </MenuItem>
            ))}
          </Select>
        </FormControl>

        {/* Thai: แสดง Loading หรือ Error ทั่วไป */} 
        {(budgetState.loadingProjects || budgetState.loadingTransactions) && (
          <CircularProgress sx={{ display: "block", margin: "auto", mb: 2 }} />
        )}
        {budgetState.error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {budgetState.error}
          </Alert>
        )}

        {/* Thai: ส่วนจัดการธุรกรรม (แสดงเมื่อเลือกโครงการแล้ว) */} 
        {budgetState.currentProject && (
          <Paper sx={{ p: 2 }}>
            <Grid
              container
              justifyContent="space-between"
              alignItems="center"
              sx={{ mb: 2 }}
            >
              <Grid item>
                <Typography variant="h6">
                  รายการธุรกรรม: {budgetState.currentProject.name}
                </Typography>
              </Grid>
              <Grid item>
                {/* Thai: ปุ่มเพิ่มธุรกรรม */} 
                <Button
                  variant="contained"
                  startIcon={<AddIcon />}
                  onClick={handleAddTransaction}
                  sx={{ mr: 1 }}
                  disabled={!budgetState.currentProject || budgetState.loadingTransactions}
                >
                  เพิ่มธุรกรรม
                </Button>
                {/* Thai: ปุ่ม Export */} 
                <Button
                  variant="outlined"
                  startIcon={exporting ? <CircularProgress size={20} /> : <DownloadIcon />}
                  onClick={handleExport}
                  disabled={!budgetState.currentProject || exporting || budgetState.transactions.length === 0}
                >
                  Export Excel
                </Button>
              </Grid>
            </Grid>

            {/* Thai: ตารางแสดงรายการธุรกรรม */} 
            <TableContainer>
              <Table stickyHeader size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>วันที่</TableCell>
                    <TableCell>รายการ</TableCell>
                    <TableCell align="right">จำนวนเงิน</TableCell>
                    <TableCell>ประเภท</TableCell>
                    <TableCell align="center">จัดการ</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {budgetState.loadingTransactions && (
                    <TableRow>
                      <TableCell colSpan={5} align="center">
                        <CircularProgress />
                      </TableCell>
                    </TableRow>
                  )}
                  {!budgetState.loadingTransactions &&
                    budgetState.transactions.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={5} align="center">
                          ไม่มีธุรกรรมสำหรับโครงการนี้
                        </TableCell>
                      </TableRow>
                    )}
                  {!budgetState.loadingTransactions &&
                    budgetState.transactions.map((transaction) => (
                      <TableRow key={transaction.id}>
                        <TableCell>
                          {format(
                            new Date(transaction.transaction_date),
                            "dd/MM/yy HH:mm",
                            { locale: thLocale }
                          )}
                        </TableCell>
                        <TableCell>{transaction.description}</TableCell>
                        <TableCell
                          align="right"
                          sx={{
                            color: transaction.type === "income" ? "green" : "red",
                          }}
                        >
                          {formatCurrency(transaction.amount)}
                        </TableCell>
                        <TableCell>
                          {transaction.type === "income" ? "รายรับ" : "รายจ่าย"}
                        </TableCell>
                        <TableCell align="center">
                          <Tooltip title="แก้ไข">
                            <IconButton
                              size="small"
                              onClick={() => handleEditTransaction(transaction)}
                            >
                              <EditIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title="ลบ">
                            <IconButton
                              size="small"
                              onClick={() => handleDeleteTransaction(transaction)}
                            >
                              <DeleteIcon fontSize="small" color="error" />
                            </IconButton>
                          </Tooltip>
                        </TableCell>
                      </TableRow>
                    ))}
                </TableBody>
              </Table>
            </TableContainer>

            {/* Thai: Pagination */} 
            <TablePagination
              component="div"
              count={budgetState.transactionPagination.totalItems}
              page={budgetState.transactionPagination.currentPage - 1} // API is 1-based, MUI is 0-based
              rowsPerPage={budgetState.transactionPagination.limit}
              onPageChange={handleChangePage}
              onRowsPerPageChange={handleChangeRowsPerPage}
              rowsPerPageOptions={[5, 10, 25, 50]}
              labelRowsPerPage="รายการต่อหน้า:"
              labelDisplayedRows={({ from, to, count }) => 
                `${from}-${to} จาก ${count !== -1 ? count : `มากกว่า ${to}`}`
              }
            />
          </Paper>
        )}

        {/* Thai: Dialog สำหรับ เพิ่ม/แก้ไข ธุรกรรม */} 
        <Dialog open={openFormDialog} onClose={handleCloseDialogs}>
          <DialogTitle>
            {isEditMode ? "แก้ไขธุรกรรม" : "เพิ่มธุรกรรมใหม่"}
          </DialogTitle>
          <DialogContent>
            <DialogContentText sx={{ mb: 2 }}>
              กรุณากรอกรายละเอียดธุรกรรมสำหรับโครงการ "
              {budgetState.currentProject?.name}"
            </DialogContentText>
            {formError && (
              <Alert severity="error" sx={{ mb: 2 }}>
                {formError}
              </Alert>
            )}
            <TextField
              autoFocus
              margin="dense"
              id="description"
              name="description"
              label="รายละเอียด"
              type="text"
              fullWidth
              variant="outlined"
              value={formData.description}
              onChange={handleFormChange}
              required
            />
            <TextField
              margin="dense"
              id="amount"
              name="amount"
              label="จำนวนเงิน (บาท)"
              type="number"
              fullWidth
              variant="outlined"
              value={formData.amount}
              onChange={handleFormChange}
              required
              inputProps={{ min: 0.01, step: 0.01 }} // Ensure positive amount
            />
            <FormControl fullWidth margin="dense" required>
              <InputLabel id="type-label">ประเภท</InputLabel>
              <Select
                labelId="type-label"
                id="type"
                name="type"
                value={formData.type}
                label="ประเภท"
                onChange={handleFormChange}
              >
                <MenuItem value="expense">รายจ่าย</MenuItem>
                <MenuItem value="income">รายรับ</MenuItem>
              </Select>
            </FormControl>
            <DateTimePicker
              label="วันที่และเวลา"
              value={formData.transaction_date}
              onChange={handleDateTimeChange}
              renderInput={(params) => (
                <TextField {...params} margin="dense" fullWidth required />
              )}
            />
          </DialogContent>
          <DialogActions>
            <Button onClick={handleCloseDialogs}>ยกเลิก</Button>
            <Button
              onClick={handleFormSubmit}
              variant="contained"
              disabled={budgetState.loadingTransactions}
            >
              {budgetState.loadingTransactions ? (
                <CircularProgress size={24} />
              ) : isEditMode ? (
                "บันทึกการแก้ไข"
              ) : (
                "เพิ่มธุรกรรม"
              )}
            </Button>
          </DialogActions>
        </Dialog>

        {/* Thai: Dialog ยืนยันการลบ */} 
        <Dialog open={openConfirmDialog} onClose={handleCloseDialogs}>
          <DialogTitle>ยืนยันการลบธุรกรรม</DialogTitle>
          <DialogContent>
            <DialogContentText>
              คุณแน่ใจหรือไม่ว่าต้องการลบธุรกรรม "
              {transactionToDelete?.description}" จำนวน
              {formatCurrency(transactionToDelete?.amount)}?
              การดำเนินการนี้ไม่สามารถย้อนกลับได้
            </DialogContentText>
          </DialogContent>
          <DialogActions>
            <Button onClick={handleCloseDialogs}>ยกเลิก</Button>
            <Button
              onClick={handleConfirmDelete}
              color="error"
              variant="contained"
              disabled={budgetState.loadingTransactions}
            >
              {budgetState.loadingTransactions ? (
                <CircularProgress size={24} />
              ) : (
                "ยืนยันการลบ"
              )}
            </Button>
          </DialogActions>
        </Dialog>
      </Box>
    </LocalizationProvider>
  );
};

export default BudgetControl;

