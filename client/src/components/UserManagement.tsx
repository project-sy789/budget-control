// Component for managing users (Admin only)

// Thai: คอมโพเนนต์สำหรับจัดการผู้ใช้ (สำหรับ Admin เท่านั้น)
// - แสดงรายชื่อผู้ใช้ทั้งหมด
// - มีฟังก์ชันสำหรับ อัปเดตบทบาท (Role), อนุมัติ (Approve), ลบผู้ใช้
// - ใช้ข้อมูลและฟังก์ชันจาก AuthContext

import React, { useEffect, useState } from "react";
import {
  Box,
  Typography,
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
  Switch,
  Tooltip,
  SelectChangeEvent,
  Chip,
} from "@mui/material";
import { Delete as DeleteIcon, AdminPanelSettings, Person } from "@mui/icons-material";
import { useAuth } from "../contexts/AuthContext";
import { User, UserRole } from "../types";

const UserManagement: React.FC = () => {
  const { authState, fetchAllUsers, updateUserRole, approveUser, deleteUser } =
    useAuth();
  const [localLoading, setLocalLoading] = useState<Record<string, boolean>>({}); // Track loading state per user action
  const [localError, setLocalError] = useState<Record<string, string | null>>({}); // Track error state per user action

  // Thai: Fetch ผู้ใช้ทั้งหมดเมื่อคอมโพเนนต์โหลด (ถ้ายังไม่มี หรือถ้าผู้ใช้ปัจจุบันเป็น admin)
  useEffect(() => {
    if (authState.user?.role === "admin" && authState.allUsers.length === 0) {
      fetchAllUsers();
    }
  }, [fetchAllUsers, authState.user?.role, authState.allUsers.length]);

  // Helper to manage local loading/error states
  const setActionState = (
    uid: string,
    loading: boolean,
    error: string | null
  ) => {
    setLocalLoading((prev) => ({ ...prev, [uid]: loading }));
    setLocalError((prev) => ({ ...prev, [uid]: error }));
  };

  // Thai: จัดการการเปลี่ยน Role
  const handleRoleChange = async (
    uid: string,
    event: SelectChangeEvent<UserRole>
  ) => {
    const newRole = event.target.value as UserRole;
    setActionState(uid, true, null);
    try {
      await updateUserRole(uid, newRole);
    } catch (error: any) {
      console.error(`Error updating role for user ${uid}:`, error);
      setActionState(
        uid,
        false,
        error.message || "เกิดข้อผิดพลาดในการเปลี่ยนบทบาท"
      );
    } finally {
      // Loading state is managed by AuthContext globally, but we can clear local state
      setActionState(uid, false, localError[uid]); // Keep error if it was set
    }
  };

  // Thai: จัดการการเปลี่ยนสถานะ Approve
  const handleApproveChange = async (
    uid: string,
    event: React.ChangeEvent<HTMLInputElement>
  ) => {
    const newApprovedStatus = event.target.checked;
    setActionState(uid, true, null);
    try {
      await approveUser(uid, newApprovedStatus);
    } catch (error: any) {
      console.error(`Error updating approval for user ${uid}:`, error);
      setActionState(
        uid,
        false,
        error.message || "เกิดข้อผิดพลาดในการอนุมัติ"
      );
    } finally {
      setActionState(uid, false, localError[uid]);
    }
  };

  // Thai: จัดการการลบผู้ใช้
  const handleDeleteUser = async (uid: string) => {
    // Optional: Add a confirmation dialog here
    if (!window.confirm("คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้นี้?")) {
      return;
    }
    setActionState(uid, true, null);
    try {
      await deleteUser(uid);
      // No need to update local state, AuthContext handles removal from allUsers
    } catch (error: any) {
      console.error(`Error deleting user ${uid}:`, error);
      setActionState(uid, false, error.message || "เกิดข้อผิดพลาดในการลบผู้ใช้");
    } finally {
      // Loading state is managed globally, clear local state
      // We might want to keep the error displayed briefly?
      // setActionState(uid, false, localError[uid]);
      // For now, just clear loading
      setLocalLoading((prev) => ({ ...prev, [uid]: false }));
    }
  };

  return (
    <Box sx={{ p: 2 }}>
      <Typography variant="h4" gutterBottom>
        จัดการผู้ใช้
      </Typography>

      {/* Thai: แสดง Loading หรือ Error ทั่วไป */} 
      {authState.loading && !Object.values(localLoading).some(Boolean) && (
        <CircularProgress sx={{ display: "block", margin: "auto", mb: 2 }} />
      )}
      {authState.error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {authState.error}
        </Alert>
      )}

      {/* Thai: ตารางแสดงรายชื่อผู้ใช้ */} 
      <TableContainer component={Paper}>
        <Table stickyHeader size="small">
          <TableHead>
            <TableRow>
              <TableCell>Email</TableCell>
              <TableCell>ชื่อ (ถ้ามี)</TableCell>
              <TableCell>Role</TableCell>
              <TableCell align="center">Approved</TableCell>
              <TableCell align="center">จัดการ</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {authState.loading && authState.allUsers.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} align="center">
                  <CircularProgress />
                </TableCell>
              </TableRow>
            )}
            {!authState.loading && authState.allUsers.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} align="center">
                  ไม่พบข้อมูลผู้ใช้
                </TableCell>
              </TableRow>
            )}
            {authState.allUsers.map((user) => (
              <TableRow key={user.uid}>
                <TableCell>{user.email}</TableCell>
                <TableCell>{user.displayName || "-"}</TableCell>
                <TableCell>
                  <Select
                    value={user.role}
                    onChange={(e) => handleRoleChange(user.uid, e)}
                    disabled={localLoading[user.uid] || authState.loading || user.uid === authState.user?.uid} // Disable self-role change
                    size="small"
                    variant="standard"
                    sx={{ minWidth: 100 }}
                  >
                    <MenuItem value="user">User</MenuItem>
                    <MenuItem value="admin">Admin</MenuItem>
                  </Select>
                </TableCell>
                <TableCell align="center">
                  <Switch
                    checked={user.approved}
                    onChange={(e) => handleApproveChange(user.uid, e)}
                    disabled={localLoading[user.uid] || authState.loading || user.uid === authState.user?.uid} // Disable self-approve change
                    color="success"
                  />
                </TableCell>
                <TableCell align="center">
                  {localLoading[user.uid] ? (
                    <CircularProgress size={20} />
                  ) : (
                    <Tooltip title="ลบผู้ใช้">
                      {/* Disable delete button for the currently logged-in admin */} 
                      <span> {/* Span needed for tooltip on disabled button */} 
                        <IconButton
                          size="small"
                          onClick={() => handleDeleteUser(user.uid)}
                          disabled={user.uid === authState.user?.uid || authState.loading}
                          color="error"
                        >
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </span>
                    </Tooltip>
                  )}
                  {/* Display local error for this user */} 
                  {localError[user.uid] && (
                    <Tooltip title={localError[user.uid]!}>
                        <Chip label="Error" color="error" size="small" sx={{ml: 1}} />
                    </Tooltip>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Box>
  );
};

export default UserManagement;

