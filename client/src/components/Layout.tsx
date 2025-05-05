// Main layout component including AppBar and Drawer

// Thai: คอมโพเนนต์โครงสร้างหลักของหน้า (Layout)
// - แสดง AppBar ด้านบน พร้อมชื่อผู้ใช้และปุ่ม Logout
// - แสดง Drawer ด้านข้างสำหรับ Navigation
// - แสดงเนื้อหาหลักของแต่ละหน้า (children)

import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
  AppBar,
  Toolbar,
  Typography,
  IconButton,
  Drawer,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  CssBaseline,
  Box,
  Divider,
  Avatar,
  Menu,
  MenuItem,
  Tooltip
} from '@mui/material';
import {
  Menu as MenuIcon,
  ChevronLeft as ChevronLeftIcon,
  AccountCircle,
  Logout as LogoutIcon,
  Dashboard as DashboardIcon, // Example icon for Projects
  AccountBalanceWallet as BudgetIcon, // Example icon for Budget Control
  Assessment as SummaryIcon, // Example icon for Summary
  People as PeopleIcon // Example icon for User Management
} from '@mui/icons-material';
import { styled, useTheme } from '@mui/material/styles';
import { useAuth } from '../contexts/AuthContext'; // Import useAuth hook

const drawerWidth = 240;

// Styled components for drawer persistence (from MUI docs example)
const Main = styled('main', { shouldForwardProp: (prop) => prop !== 'open' })<{ open?: boolean; }>(({ theme, open }) => ({
  flexGrow: 1,
  padding: theme.spacing(3),
  transition: theme.transitions.create('margin', {
    easing: theme.transitions.easing.sharp,
    duration: theme.transitions.duration.leavingScreen,
  }),
  marginLeft: `-${drawerWidth}px`,
  ...(open && {
    transition: theme.transitions.create('margin', {
      easing: theme.transitions.easing.easeOut,
      duration: theme.transitions.duration.enteringScreen,
    }),
    marginLeft: 0,
  }),
}));

const AppBarStyled = styled(AppBar, { shouldForwardProp: (prop) => prop !== 'open' })<{ open?: boolean; }>(({ theme, open }) => ({
  transition: theme.transitions.create(['margin', 'width'], {
    easing: theme.transitions.easing.sharp,
    duration: theme.transitions.duration.leavingScreen,
  }),
  ...(open && {
    width: `calc(100% - ${drawerWidth}px)`,
    marginLeft: `${drawerWidth}px`,
    transition: theme.transitions.create(['margin', 'width'], {
      easing: theme.transitions.easing.easeOut,
      duration: theme.transitions.duration.enteringScreen,
    }),
  }),
}));

const DrawerHeader = styled('div')(({ theme }) => ({
  display: 'flex',
  alignItems: 'center',
  padding: theme.spacing(0, 1),
  ...theme.mixins.toolbar,
  justifyContent: 'flex-end',
}));

interface LayoutProps {
  children: React.ReactNode;
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
  const theme = useTheme();
  const navigate = useNavigate();
  const location = useLocation();
  const { authState, logout } = useAuth(); // Get user state and logout function
  const [open, setOpen] = useState(true); // Drawer open state
  const [anchorElUser, setAnchorElUser] = useState<null | HTMLElement>(null);

  // Thai: ฟังก์ชันเปิด/ปิด Drawer
  const handleDrawerOpen = () => setOpen(true);
  const handleDrawerClose = () => setOpen(false);

  // Thai: ฟังก์ชันเปิด/ปิดเมนูผู้ใช้บน AppBar
  const handleOpenUserMenu = (event: React.MouseEvent<HTMLElement>) => setAnchorElUser(event.currentTarget);
  const handleCloseUserMenu = () => setAnchorElUser(null);

  // Thai: ฟังก์ชันจัดการการ Logout
  const handleLogout = async () => {
    handleCloseUserMenu();
    await logout();
    navigate('/login'); // Redirect to login page after logout
  };

  // Thai: รายการเมนูใน Drawer
  const menuItems = [
    { text: 'จัดการโครงการ', icon: <DashboardIcon />, path: '/projects', role: 'any' },
    { text: 'ควบคุมงบประมาณ', icon: <BudgetIcon />, path: '/budget-control', role: 'any' },
    { text: 'สรุปงบประมาณ', icon: <SummaryIcon />, path: '/budget-summary', role: 'any' },
    { text: 'จัดการผู้ใช้', icon: <PeopleIcon />, path: '/user-management', role: 'admin' }, // Admin only
  ];

  // Thai: Filter เมนูตาม Role ของผู้ใช้
  const availableMenuItems = menuItems.filter(item => 
      item.role === 'any' || (item.role === 'admin' && authState.user?.role === 'admin')
  );

  return (
    <Box sx={{ display: 'flex' }}>
      <CssBaseline />
      {/* Thai: AppBar ด้านบน */} 
      <AppBarStyled position="fixed" open={open}>
        <Toolbar>
          <IconButton
            color="inherit"
            aria-label="open drawer"
            onClick={handleDrawerOpen}
            edge="start"
            sx={{ mr: 2, ...(open && { display: 'none' }) }}
          >
            <MenuIcon />
          </IconButton>
          <Typography variant="h6" noWrap component="div" sx={{ flexGrow: 1 }}>
            ระบบควบคุมงบประมาณ
          </Typography>
          {/* Thai: แสดงข้อมูลผู้ใช้และเมนู Logout */} 
          {authState.user && (
            <Box sx={{ flexGrow: 0 }}>
              <Tooltip title={authState.user.email || "เมนูผู้ใช้"}>
                <IconButton onClick={handleOpenUserMenu} sx={{ p: 0 }}>
                  <Avatar alt={authState.user.displayName || 'User'} src={authState.user.photoURL || undefined} />
                </IconButton>
              </Tooltip>
              <Menu
                sx={{ mt: '45px' }}
                id="menu-appbar"
                anchorEl={anchorElUser}
                anchorOrigin={{
                  vertical: 'top',
                  horizontal: 'right',
                }}
                keepMounted
                transformOrigin={{
                  vertical: 'top',
                  horizontal: 'right',
                }}
                open={Boolean(anchorElUser)}
                onClose={handleCloseUserMenu}
              >
                <MenuItem disabled sx={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start' }}>
                    <Typography variant="body1" fontWeight="bold">{authState.user.displayName}</Typography>
                    <Typography variant="caption">{authState.user.email}</Typography>
                    <Typography variant="caption" color="text.secondary">Role: {authState.user.role}</Typography>
                </MenuItem>
                <Divider />
                <MenuItem onClick={handleLogout}>
                  <ListItemIcon>
                      <LogoutIcon fontSize="small" />
                  </ListItemIcon>
                  <ListItemText>ออกจากระบบ</ListItemText>
                </MenuItem>
              </Menu>
            </Box>
          )}
        </Toolbar>
      </AppBarStyled>
      
      {/* Thai: Drawer ด้านข้าง */} 
      <Drawer
        sx={{
          width: drawerWidth,
          flexShrink: 0,
          '& .MuiDrawer-paper': {
            width: drawerWidth,
            boxSizing: 'border-box',
          },
        }}
        variant="persistent"
        anchor="left"
        open={open}
      >
        <DrawerHeader>
          <IconButton onClick={handleDrawerClose}>
            <ChevronLeftIcon />
          </IconButton>
        </DrawerHeader>
        <Divider />
        <List>
          {availableMenuItems.map((item) => (
            <ListItem 
              button 
              key={item.text} 
              onClick={() => navigate(item.path)}
              selected={location.pathname === item.path || (location.pathname === '/' && item.path === '/projects')} // Highlight selected item
            >
              <ListItemIcon>{item.icon}</ListItemIcon>
              <ListItemText primary={item.text} />
            </ListItem>
          ))}
        </List>
      </Drawer>
      
      {/* Thai: ส่วนแสดงเนื้อหาหลัก */} 
      <Main open={open}>
        <DrawerHeader /> {/* Spacer for content below AppBar */} 
        {children}
      </Main>
    </Box>
  );
};

export default Layout;

