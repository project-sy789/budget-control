import React, { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Button,
  Chip,
  IconButton,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  FormControl,
  Select,
  MenuItem,
  Avatar,
  Grid,
  Alert,
  Tooltip,
  Divider,
  Container,
  SelectChangeEvent,
  DialogContentText
} from '@mui/material';
import {
  Check as CheckIcon,
  Close as CloseIcon,
  Info as InfoIcon,
  Delete as DeleteIcon
} from '@mui/icons-material';
import { format } from 'date-fns';
import { th } from 'date-fns/locale';
import { useAuth } from '../contexts/AuthContext';
import { User, UserRole } from '../types';

// รายการบทบาทผู้ใช้ที่แสดงในภาษาไทย
const roleLabels: Record<UserRole, string> = {
  admin: 'ผู้ดูแลระบบ',
  user: 'ผู้ใช้งาน',
  pending: 'รอการอนุมัติ'
};

// รายการสีของบทบาทผู้ใช้
const roleColors: Record<UserRole, string> = {
  admin: 'error',
  user: 'primary',
  pending: 'warning'
};

const UserManagement: React.FC = () => {
  const { authState, fetchAllUsers, updateUserRole, approveUser, deleteUser } = useAuth();
  const { allUsers } = authState; // Access allUsers from authState
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [userDetailsOpen, setUserDetailsOpen] = useState(false);
  const [openDeleteDialog, setOpenDeleteDialog] = useState(false);

  // Fetch all users on component mount
  useEffect(() => {
    fetchAllUsers();
  }, [fetchAllUsers]);

  // Check if current user is admin
  const isAdmin = authState.user?.role === 'admin';



  // Handle approve user
  const handleApproveUser = async (userId: string) => {
    await approveUser(userId);
  };

  // Handle view user details
  const handleViewUserDetails = (user: User) => {
    setSelectedUser(user);
    setUserDetailsOpen(true);
  };

  // Handle close user details
  const handleCloseUserDetails = () => {
    setUserDetailsOpen(false);
    setSelectedUser(null);
  };

  // Handle delete user
  const handleDeleteUser = (user: User) => {
    setSelectedUser(user);
    setOpenDeleteDialog(true);
  };

  // Handle confirm delete user
  const confirmDeleteUser = async () => {
    if (selectedUser) {
      await deleteUser(selectedUser.uid);
      setOpenDeleteDialog(false);
      setSelectedUser(null);
    }
  };

  // Handle close delete dialog
  const handleCloseDeleteDialog = () => {
    setOpenDeleteDialog(false);
    setSelectedUser(null);
  };

  // ตรวจสอบว่าเป็นผู้ใช้ปัจจุบันหรือไม่
  const isCurrentUser = (userId: string) => {
    return authState.user?.uid === userId;
  };

  // If user is not admin, redirect or show unauthorized message
  if (!isAdmin) {
    return (
      <Box sx={{ p: 3 }}>
        <Alert severity="error">
          <Typography variant="body1">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</Typography>
        </Alert>
      </Box>
    );
  }

  // Format date
  const formatDate = (dateString?: string) => {
    if (!dateString) return 'ไม่มีข้อมูล';
    try {
      return format(new Date(dateString), 'dd MMM yyyy HH:mm', { locale: th });
    } catch (error) {
      return 'วันที่ไม่ถูกต้อง';
    }
  };

  // ใช้ roleLabels และ roleColors แทน getRoleInfo

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        จัดการผู้ใช้งาน
      </Typography>
      
      <Paper sx={{ p: 2, mt: 3 }}>
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>ผู้ใช้งาน</TableCell>
                <TableCell>อีเมล</TableCell>
                <TableCell>บทบาท</TableCell>
                <TableCell>สถานะ</TableCell>
                <TableCell>วันที่สร้าง</TableCell>
                <TableCell>เข้าสู่ระบบล่าสุด</TableCell>
                <TableCell align="center">การจัดการ</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {allUsers.map((user) => (
                <TableRow key={user.uid}>
                  <TableCell>
                    <Box sx={{ display: 'flex', alignItems: 'center' }}>
                      {user.photoURL ? (
                        <Avatar src={user.photoURL} alt={user.displayName} sx={{ mr: 2 }} />
                      ) : (
                        <Avatar sx={{ mr: 2 }}>{user.displayName?.charAt(0)}</Avatar>
                      )}
                      <Typography variant="body1">{user.displayName}</Typography>
                      {isCurrentUser(user.uid) && (
                        <Chip size="small" label="ผู้ใช้ปัจจุบัน" color="success" sx={{ ml: 1 }} />
                      )}
                    </Box>
                  </TableCell>
                  <TableCell>{user.email}</TableCell>
                  <TableCell>
                    <FormControl size="small" fullWidth>
                      <Select
                        value={user.role}
                        onChange={(e: SelectChangeEvent) => 
                          updateUserRole(user.uid, e.target.value as UserRole)                       }
                        disabled={isCurrentUser(user.uid)}
                      >
                        <MenuItem value="admin">{roleLabels.admin}</MenuItem>
                        <MenuItem value="user">{roleLabels.user}</MenuItem>
                      </Select>
                    </FormControl>
                  </TableCell>
                  <TableCell>
                    <Chip 
                      label={user.approved ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'} 
                      color={user.approved ? 'success' : 'warning'} 
                      size="small"
                    />
                  </TableCell>
                  <TableCell>-</TableCell>
                  <TableCell>-</TableCell>
                  <TableCell align="center">
                    <Box sx={{ display: 'flex', justifyContent: 'center' }}>
                      <Tooltip title="ดูข้อมูล">
                        <IconButton 
                          color="info" 
                          onClick={() => handleViewUserDetails(user)}
                        >
                          <InfoIcon />
                        </IconButton>
                      </Tooltip>
                      
                      {!user.approved && (
                        <Tooltip title="อนุมัติผู้ใช้">
                          <IconButton 
                            color="success" 
                            onClick={() => handleApproveUser(user.uid)}
                          >
                            <CheckIcon />
                          </IconButton>
                        </Tooltip>
                      )}
                      
                      {!isCurrentUser(user.uid) && user.email !== 'nutrawee@subyaischool.ac.th' && (
                        <Tooltip title="ลบผู้ใช้">
                          <IconButton 
                            color="error" 
                            onClick={() => handleDeleteUser(user)}
                          >
                            <DeleteIcon />
                          </IconButton>
                        </Tooltip>
                      )}
                    </Box>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      </Paper>
      
      {/* ไดอะล็อกยืนยันการลบผู้ใช้ */}
      <Dialog
        open={openDeleteDialog}
        onClose={handleCloseDeleteDialog}
      >
        <DialogTitle>ยืนยันการลบผู้ใช้</DialogTitle>
        <DialogContent>
          <DialogContentText>
            คุณต้องการลบผู้ใช้ "{selectedUser?.displayName}" ({selectedUser?.email}) ใช่หรือไม่?
            <br />
            การดำเนินการนี้ไม่สามารถย้อนกลับได้
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={handleCloseDeleteDialog} color="primary">
            ยกเลิก
          </Button>
          <Button onClick={confirmDeleteUser} color="error" variant="contained">
            ยืนยันการลบ
          </Button>
        </DialogActions>
      </Dialog>

      {/* ไดอะล็อกแสดงข้อมูลผู้ใช้ */}
      <Dialog 
        open={userDetailsOpen} 
        onClose={handleCloseUserDetails} 
        maxWidth="sm" 
        fullWidth
      >
        <DialogTitle sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          ข้อมูลผู้ใช้
          <IconButton size="small" onClick={handleCloseUserDetails}>
            <CloseIcon />
          </IconButton>
        </DialogTitle>
        
        <DialogContent>
          {selectedUser && (
            <Box>
              <Box sx={{ display: 'flex', alignItems: 'center', mb: 3 }}>
                {selectedUser.photoURL ? (
                  <Avatar 
                    src={selectedUser.photoURL} 
                    sx={{ width: 64, height: 64, mr: 2 }}
                  />
                ) : (
                  <Avatar sx={{ width: 64, height: 64, mr: 2 }}>
                    {selectedUser.displayName?.charAt(0)}
                  </Avatar>
                )}
                <Box>
                  <Typography variant="h6">{selectedUser.displayName}</Typography>
                  <Typography variant="body1">{selectedUser.email}</Typography>
                  <Chip 
                    label={roleLabels[selectedUser.role]}
                    color={roleColors[selectedUser.role] as any}
                    size="small"
                    sx={{ mt: 1 }}
                  />
                </Box>
              </Box>

              <Divider sx={{ my: 2 }} />

              <Grid container spacing={2}>
                <Grid item xs={6}>
                  <Typography variant="subtitle2" color="text.secondary">ID</Typography>
                  <Typography variant="body2">{selectedUser.uid}</Typography>
                </Grid>
                <Grid item xs={6}>
                  <Typography variant="subtitle2" color="text.secondary">สถานะ</Typography>
                  <Typography variant="body2">
                    {selectedUser.approved ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'}
                  </Typography>
                </Grid>
              </Grid>
            </Box>
          )}
        </DialogContent>
        
        <DialogActions>
          {selectedUser && !selectedUser.approved && (
            <Button 
              onClick={() => {
                handleApproveUser(selectedUser.uid);
                handleCloseUserDetails();
              }} 
              color="success" 
              variant="contained"
              startIcon={<CheckIcon />}
            >
              อนุมัติผู้ใช้
            </Button>
          )}
          {selectedUser && !isCurrentUser(selectedUser.uid) && selectedUser.email !== 'nutrawee@subyaischool.ac.th' && (
            <Button 
              onClick={() => {
                handleCloseUserDetails();
                handleDeleteUser(selectedUser);
              }} 
              color="error"
              startIcon={<DeleteIcon />}
            >
              ลบผู้ใช้
            </Button>
          )}
          <Button onClick={handleCloseUserDetails}>ปิด</Button>
        </DialogActions>
      </Dialog>
    </Container>
  );
};

export default UserManagement;