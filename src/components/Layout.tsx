import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  AppBar,
  Box,
  CssBaseline,
  Drawer,
  IconButton,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  Toolbar,
  Typography,
  useTheme,
  useMediaQuery,
  Button,
  Avatar,
  Menu,
  MenuItem,
  Divider,
  Tooltip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Grid,
  Chip,
} from '@mui/material';
import {
  Menu as MenuIcon,
  Dashboard as DashboardIcon,
  AccountBalance as AccountBalanceIcon,
  Assessment as AssessmentIcon,
  People as PeopleIcon,
  ExitToApp as LogoutIcon,
  Visibility as VisibilityIcon,
} from '@mui/icons-material';
import { useAuth } from '../contexts/AuthContext';

interface LayoutProps {
  children: React.ReactNode;
}

const drawerWidth = 240;

const Layout: React.FC<LayoutProps> = ({ children }) => {
  const [mobileOpen, setMobileOpen] = useState(false);
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [profileDialogOpen, setProfileDialogOpen] = useState(false);
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));
  const navigate = useNavigate();
  const location = useLocation();
  const { authState, logout } = useAuth();
  const { user } = authState;
  const isAdmin = user?.role === 'admin';

  const handleDrawerToggle = () => {
    setMobileOpen(!mobileOpen);
  };

  const handleProfileMenuOpen = (event: React.MouseEvent<HTMLElement>) => {
    setAnchorEl(event.currentTarget);
  };

  const handleMenuClose = () => {
    setAnchorEl(null);
  };

  const handleLogout = async () => {
    await logout();
    handleMenuClose();
    navigate('/login');
  };

  const handleViewProfile = () => {
    handleMenuClose();
    setProfileDialogOpen(true);
  };

  const menuItems = [
    { text: 'จัดการโครงการ', icon: <DashboardIcon />, path: '/' },
    { text: 'ควบคุมงบประมาณ', icon: <AccountBalanceIcon />, path: '/budget-control' },
    { text: 'สรุปงบประมาณ', icon: <AssessmentIcon />, path: '/budget-summary' },
  ];

  // Add user management menu item for admin
  if (isAdmin) {
    menuItems.push({ 
      text: 'จัดการผู้ใช้', 
      icon: <PeopleIcon />, 
      path: '/user-management' 
    });
  }

  const drawer = (
    <div>
      <Toolbar />
      <List>
        {menuItems.map((item) => (
          <ListItem
            button
            key={item.text}
            onClick={() => {
              navigate(item.path);
              if (isMobile) {
                setMobileOpen(false);
              }
            }}
            selected={location.pathname === item.path}
          >
            <ListItemIcon>{item.icon}</ListItemIcon>
            <ListItemText primary={item.text} />
          </ListItem>
        ))}
      </List>
    </div>
  );

  const isMenuOpen = Boolean(anchorEl);
  const menuId = 'primary-search-account-menu';
  const renderMenu = (
    <Menu
      anchorEl={anchorEl}
      anchorOrigin={{
        vertical: 'bottom',
        horizontal: 'right',
      }}
      id={menuId}
      keepMounted
      transformOrigin={{
        vertical: 'top',
        horizontal: 'right',
      }}
      open={isMenuOpen}
      onClose={handleMenuClose}
    >
      <Box sx={{ px: 2, py: 1 }}>
        <Typography variant="subtitle1">{user?.displayName}</Typography>
        <Typography variant="body2" color="text.secondary">{user?.email}</Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
          {isAdmin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน'}
        </Typography>
      </Box>
      <Divider />
      <MenuItem onClick={handleViewProfile}>
        <ListItemIcon>
          <VisibilityIcon fontSize="small" />
        </ListItemIcon>
        ดูโปรไฟล์
      </MenuItem>
      <MenuItem onClick={handleLogout}>
        <ListItemIcon>
          <LogoutIcon fontSize="small" />
        </ListItemIcon>
        ออกจากระบบ
      </MenuItem>
    </Menu>
  );

  // Format date for profile display
  const formatDate = (date: string | number | Date | undefined) => {
    if (!date) return 'ไม่มีข้อมูล';
    return new Date(date).toLocaleString('th-TH', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // User profile dialog
  const renderProfileDialog = (
    <Dialog
      open={profileDialogOpen}
      onClose={() => setProfileDialogOpen(false)}
      maxWidth="sm"
      fullWidth
    >
      <DialogTitle>โปรไฟล์ผู้ใช้</DialogTitle>
      <DialogContent>
        {user && (
          <Box sx={{ p: 2 }}>
            <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
              <Avatar 
                sx={{ 
                  width: 64, 
                  height: 64, 
                  mr: 2,
                  bgcolor: theme.palette.primary.main
                }}
              >
                {user.displayName?.charAt(0) || 'U'}
              </Avatar>
              <Box>
                <Typography variant="h6">{user.displayName}</Typography>
                <Typography variant="body1">{user.email}</Typography>
                <Chip 
                  label={isAdmin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน'}
                  color={isAdmin ? 'primary' : 'default'}
                  size="small"
                  sx={{ mt: 1 }}
                />
              </Box>
            </Box>

            <Divider sx={{ my: 2 }} />

            <Grid container spacing={2}>
              <Grid item xs={6}>
                <Typography variant="subtitle2" color="text.secondary">ID</Typography>
                <Typography variant="body2">{user.uid}</Typography>
              </Grid>
              <Grid item xs={6}>
                <Typography variant="subtitle2" color="text.secondary">สถานะ</Typography>
                <Typography variant="body2">
                  {user.approved ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'}
                </Typography>
              </Grid>
            </Grid>
          </Box>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={() => setProfileDialogOpen(false)}>ปิด</Button>
      </DialogActions>
    </Dialog>
  );

  return (
    <Box sx={{ display: 'flex' }}>
      <CssBaseline />
      <AppBar
        position="fixed"
        sx={{
          width: { sm: `calc(100% - ${drawerWidth}px)` },
          ml: { sm: `${drawerWidth}px` },
        }}
      >
        <Toolbar sx={{ display: 'flex', justifyContent: 'space-between' }}>
          <Box sx={{ display: 'flex', alignItems: 'center' }}>
            <IconButton
              color="inherit"
              aria-label="open drawer"
              edge="start"
              onClick={handleDrawerToggle}
              sx={{ mr: 2, display: { sm: 'none' } }}
            >
              <MenuIcon />
            </IconButton>
            <Typography variant="h6" noWrap component="div">
              ระบบควบคุมงบประมาณโครงการโรงเรียนซับใหญ่วิทยาคม
            </Typography>
          </Box>
          
          {user ? (
            <Tooltip title="บัญชีผู้ใช้">
              <IconButton
                edge="end"
                aria-label="account"
                aria-controls={menuId}
                aria-haspopup="true"
                onClick={handleProfileMenuOpen}
                color="inherit"
                size="small"
                sx={{ ml: 2 }}
              >
                {user.photoURL ? (
                  <Avatar src={user.photoURL} sx={{ width: 32, height: 32 }} />
                ) : (
                  <Avatar sx={{ width: 32, height: 32, bgcolor: theme.palette.primary.dark }}>
                    {user.displayName?.charAt(0) || 'U'}
                  </Avatar>
                )}
              </IconButton>
            </Tooltip>
          ) : (
            <Button 
              color="inherit" 
              variant="outlined"
              onClick={() => navigate('/login')}
              sx={{ borderColor: 'rgba(255,255,255,0.5)', textTransform: 'none' }}
            >
              เข้าสู่ระบบ
            </Button>
          )}
        </Toolbar>
      </AppBar>
      {renderMenu}
      {renderProfileDialog}
      <Box
        component="nav"
        sx={{ width: { sm: drawerWidth }, flexShrink: { sm: 0 } }}
      >
        <Drawer
          variant={isMobile ? 'temporary' : 'permanent'}
          open={mobileOpen}
          onClose={handleDrawerToggle}
          ModalProps={{
            keepMounted: true, // Better open performance on mobile.
          }}
          sx={{
            '& .MuiDrawer-paper': {
              boxSizing: 'border-box',
              width: drawerWidth,
            },
          }}
        >
          {drawer}
        </Drawer>
      </Box>
      <Box
        component="main"
        sx={{
          flexGrow: 1,
          p: 3,
          width: { sm: `calc(100% - ${drawerWidth}px)` },
          minHeight: '100vh',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <Toolbar />
        <Box sx={{ flexGrow: 1, mb: 6 }}>
          {children}
        </Box>
        <Box
          component="footer"
          sx={{
            py: 3,
            px: 2,
            mt: 'auto',
            backgroundColor: (theme) => theme.palette.grey[100],
            textAlign: 'center',
            borderTop: '1px solid',
            borderColor: 'divider',
          }}
        >
          <Box sx={{ maxWidth: 600, mx: 'auto' }}>
            <Typography 
              variant="body1" 
              color="text.secondary"
              sx={{ mb: 1 }}
            >
              พัฒนาโดย นายณัฐรวี วิเศษสมบัติ
            </Typography>
            <Typography 
              variant="body1" 
              color="text.secondary"
              sx={{ mb: 0.5 }}
            >
              ตำแหน่ง ครูผู้ช่วย โรงเรียนซับใหญ่วิทยาคม
            </Typography>
            <Typography 
              variant="body1" 
              color="text.secondary"
            >
              สังกัดสำนักงานเขตพื้นที่การศึกษาชัยภูมิ เขต 3
            </Typography>
          </Box>
        </Box>
      </Box>
    </Box>
  );
};

export default Layout; 