<?php
/**
 * Budget Control System v2 - Main Entry Point
 * PHP/MySQL with Bootstrap 5
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth/SessionManager.php';
require_once __DIR__ . '/../src/Auth/AuthService.php';
require_once __DIR__ . '/../src/Services/ProjectService.php';
require_once __DIR__ . '/../src/Services/TransactionService.php';
require_once __DIR__ . '/../src/Services/SettingsService.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize services
$sessionManager = new SessionManager();
$authService = new AuthService();
$projectService = new ProjectService();
$transactionService = new TransactionService();
$settingsService = new SettingsService();

// Get site configuration
$siteConfig = $settingsService->getSiteConfig();

// Check if user is logged in
$isLoggedIn = $sessionManager->isLoggedIn();
$currentUser = null;

if ($isLoggedIn) {
    $userId = $sessionManager->getCurrentUserId();
    $currentUser = $authService->getUserById($userId);
}

// Handle AJAX requests before any HTML output
if (isset($_GET['get_categories']) && isset($_GET['project_id']) && isset($_GET['page']) && $_GET['page'] === 'budget-control') {
    $projectId = intval($_GET['project_id']);
    $categories = $projectService->getProjectBudgetCategories($projectId);
    header('Content-Type: application/json');
    echo json_encode($categories);
    exit;
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';

// Handle logout
if ($page === 'logout') {
    $sessionManager->destroySession();
    header('Location: index.php?page=login');
    exit;
}

// Redirect to login if not authenticated (except for login page)
if (!$isLoggedIn && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

// Clean up expired sessions periodically
if (rand(1, 100) === 1) {
    $sessionManager->cleanupExpiredSessions();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    <meta name="msapplication-tap-highlight" content="no">
    <title><?= htmlspecialchars($siteConfig['site_title']) ?></title>
    
    <!-- Favicon -->
    <?php if ($siteConfig['site_icon']): ?>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($siteConfig['site_icon']) ?>">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($siteConfig['site_icon']) ?>">
    <?php else: ?>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <?php endif; ?>
    
    <!-- PWA Manifest -->
    <?php if ($siteConfig['enable_pwa']): ?>
    <link rel="manifest" href="manifest.php">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($siteConfig['site_name']) ?>">
    <?php endif; ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        /* Base mobile fixes */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            html, body {
                -webkit-overflow-scrolling: touch;
                -webkit-text-size-adjust: 100%;
            }
        }
        /* Loading Spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
            opacity: 0;
        }
        
        .loading-overlay.show {
            display: flex;
            opacity: 1;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Mobile content visibility */
        @media (max-width: 768px) {
            .main-content {
                opacity: 1;
                transition: opacity 0.3s ease;
            }
            
            .main-content.loading {
                opacity: 0;
            }
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 0.5rem;
            margin: 0.2rem 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 1rem;
        }
        
        /* Developer Info Styling */
        .developer-info, .position-info, .school-info {
            padding: 0.5rem 1rem;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 0.5rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .developer-info:hover, .position-info:hover, .school-info:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .d-flex.gap-3 {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .developer-info, .position-info, .school-info {
                width: 100%;
                text-align: center;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
        
        .mobile-menu-toggle {
            display: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
        }
        
        .mobile-menu-toggle:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        .navbar-brand {
            font-weight: 600;
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
        }
        .badge {
            font-size: 0.75rem;
        }
        .work-group-academic { background-color: #0d6efd; }
        .work-group-budget { background-color: #6f42c1; }
        .work-group-hr { background-color: #fd7e14; }
        .work-group-general { background-color: #198754; }
        .work-group-other { background-color: #6c757d; }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
    
    <?php if ($isLoggedIn): ?>
    <div class="container-fluid main-content">
        <div class="row">
            <!-- Sidebar Overlay for Mobile -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <?php if ($siteConfig['site_icon']): ?>
                        <img src="<?= htmlspecialchars($siteConfig['site_icon']) ?>" 
                             alt="<?= htmlspecialchars($siteConfig['site_name']) ?>" 
                             style="width: 48px; height: 48px; object-fit: contain;" 
                             class="mb-2">
                        <?php endif; ?>
                        <h5 class="text-white"><?= htmlspecialchars($siteConfig['site_name']) ?></h5>
                        <small class="text-white-50"><?= htmlspecialchars($siteConfig['organization_name']) ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                                <i class="bi bi-speedometer2 me-2"></i>
                                แดชบอร์ด
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'projects' ? 'active' : '' ?>" href="?page=projects">
                                <i class="bi bi-folder me-2"></i>
                                จัดการโครงการ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'budget-control' ? 'active' : '' ?>" href="?page=budget-control">
                                <i class="bi bi-calculator me-2"></i>
                                ควบคุมงบประมาณ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'budget-summary' ? 'active' : '' ?>" href="?page=budget-summary">
                                <i class="bi bi-bar-chart me-2"></i>
                                สรุปงบประมาณ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'budget-transfer' ? 'active' : '' ?>" href="?page=budget-transfer">
                                <i class="bi bi-arrow-left-right me-2"></i>
                                โอนงบประมาณ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'category-management' ? 'active' : '' ?>" href="?page=category-management">
                                <i class="bi bi-tags me-2"></i>
                                จัดการหมวดหมู่
                            </a>
                        </li>
                        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'user-management' ? 'active' : '' ?>" href="?page=user-management">
                                <i class="bi bi-people me-2"></i>
                                จัดการผู้ใช้
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $page === 'system-settings' ? 'active' : '' ?>" href="?page=system-settings">
                                <i class="bi bi-gear me-2"></i>
                                การตั้งค่าระบบ
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i>
                            <strong><?= htmlspecialchars($currentUser['display_name']) ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                            <li><a class="dropdown-item" href="?page=profile">โปรไฟล์</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?page=logout">ออกจากระบบ</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div class="d-flex align-items-center">
                        <button class="btn mobile-menu-toggle me-3" id="mobileMenuToggle">
                            <i class="bi bi-list"></i>
                        </button>
                        <h1 class="h2 mb-0">
                        <?php
                        switch ($page) {
                            case 'dashboard': echo 'แดชบอร์ด'; break;
                            case 'projects': echo 'จัดการโครงการ'; break;
                            case 'budget-control': echo 'ควบคุมงบประมาณ'; break;
                            case 'budget-summary': echo 'สรุปงบประมาณ'; break;
                            case 'budget-transfer': echo 'โอนงบประมาณ'; break;
                            case 'category-management': echo 'จัดการหมวดหมู่'; break;
                            case 'user-management': echo 'จัดการผู้ใช้'; break;
                            case 'system-settings': echo 'การตั้งค่าระบบ'; break;
                            case 'profile': echo 'โปรไฟล์'; break;
                            default: echo 'ระบบควบคุมงบประมาณ';
                        }
                        ?>
                        </h1>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="badge bg-success">ออนไลน์</span>
                        </div>
                    </div>
                </div>
                
                <!-- Page Content -->
                <div class="content">
                    <?php
                    switch ($page) {
                        case 'dashboard':
                            include 'pages/dashboard.php';
                            break;
                        case 'projects':
                            include 'pages/projects.php';
                            break;
                        case 'budget-control':
                            include 'pages/budget-control.php';
                            break;
                        case 'budget-summary':
                            include 'pages/budget-summary.php';
                            break;
                        case 'budget-transfer':
                            include 'pages/budget-transfer.php';
                            break;
                        case 'category-management':
                            include 'pages/category-management.php';
                            break;
                        case 'user-management':
                            if ($currentUser && $currentUser['role'] === 'admin') {
                                include 'pages/user-management.php';
                            } else {
                                echo '<div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
                            }
                            break;
                        case 'system-settings':
                            if ($currentUser && $currentUser['role'] === 'admin') {
                                include 'pages/system-settings.php';
                            } else {
                                echo '<div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
                            }
                            break;
                        case 'profile':
                            include 'pages/profile.php';
                            break;
                        default:
                            include 'pages/dashboard.php';
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>
    <?php else: ?>
    <!-- Login Page -->
    <?php include 'pages/login.php'; ?>
    <?php endif; ?>
    


    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js for dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- PWA Service Worker Registration -->
    <?php if ($siteConfig['enable_pwa']): ?>
    <script>
    // Register service worker for PWA
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js')
                .then(function(registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                })
                .catch(function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
        });
    }
    </script>
    <?php endif; ?>
    
    <!-- Mobile Loading and Menu Toggle Script -->
    <script>
    // Hide loading overlay and show content
    function hideLoadingOverlay() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        const mainContent = document.querySelector('.main-content');
        
        if (loadingOverlay) {
            loadingOverlay.classList.remove('show');
            // Reset inline styles that might have been set as fallback
            loadingOverlay.style.display = '';
            loadingOverlay.style.opacity = '';
        }
        
        if (mainContent) {
            mainContent.classList.remove('loading');
        }
    }
    
    // Show loading overlay and hide content
    function showLoadingOverlay() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        const mainContent = document.querySelector('.main-content');
        
        if (loadingOverlay) {
            loadingOverlay.classList.add('show');
        }
        
        if (mainContent) {
            mainContent.classList.add('loading');
        }
    }
    
    // Make functions globally accessible
    window.hideLoadingOverlay = hideLoadingOverlay;
    window.showLoadingOverlay = showLoadingOverlay;
    
    // Initialize page loading
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure content is visible immediately
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.classList.remove('loading');
        }
        
        // Hide loading overlay after a short delay
        setTimeout(hideLoadingOverlay, 500);
        
        // Also hide on window load as backup
        window.addEventListener('load', function() {
            setTimeout(hideLoadingOverlay, 100);
        });
        
        // Force hide loading overlay if it's still visible after 1.5 seconds
        setTimeout(function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay && (loadingOverlay.classList.contains('show') || loadingOverlay.style.display === 'flex')) {
                hideLoadingOverlay();
            }
        }, 1500);
        
        // Mobile menu functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (mobileMenuToggle && sidebar && sidebarOverlay) {
            // Toggle sidebar on button click
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });
            
            // Close sidebar when clicking on overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });
            
            // Close sidebar when clicking on a menu item (mobile only)
            const menuLinks = sidebar.querySelectorAll('.nav-link');
            menuLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });
            
            // Close sidebar on window resize if screen becomes larger
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                }
            });
        }
    });
    </script>
</body>
</html>