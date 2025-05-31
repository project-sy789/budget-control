<?php
/**
 * Budget Control System v2 - Main Entry Point
 * PHP/MySQL with Bootstrap 5
 */

session_start();

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth/SessionManager.php';
require_once __DIR__ . '/../src/Auth/AuthService.php';
require_once __DIR__ . '/../src/Services/ProjectService.php';
require_once __DIR__ . '/../src/Services/TransactionService.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Initialize services
$sessionManager = new SessionManager();
$authService = new AuthService();
$projectService = new ProjectService();
$transactionService = new TransactionService();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบควบคุมการเบิกจ่ายโครงการ v2</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
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
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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
    <?php if ($isLoggedIn): ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">ระบบควบคุมงบประมาณ</h5>
                        <small class="text-white-50">เวอร์ชัน 2.0</small>
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
                    <h1 class="h2">
                        <?php
                        switch ($page) {
                            case 'dashboard': echo 'แดชบอร์ด'; break;
                            case 'projects': echo 'จัดการโครงการ'; break;
                            case 'budget-control': echo 'ควบคุมงบประมาณ'; break;
                            case 'budget-summary': echo 'สรุปงบประมาณ'; break;
                            case 'budget-transfer': echo 'โอนงบประมาณ'; break;
                            case 'category-management': echo 'จัดการหมวดหมู่'; break;
                            case 'user-management': echo 'จัดการผู้ใช้'; break;
                            case 'profile': echo 'โปรไฟล์'; break;
                            default: echo 'ระบบควบคุมงบประมาณ';
                        }
                        ?>
                    </h1>
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
    
    <!-- Footer with Developer Credits -->
    <footer class="bg-light border-top mt-5 py-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-center">
                    <small class="text-muted">
                        <i class="bi bi-code-slash me-1"></i>
                        พัฒนาโดย <strong>นายณัฐรวี วิเศษสมบัติ</strong> 
                        ตำแหน่ง ครูผู้ช่วย โรงเรียนซับใหญ่วิทยาคม
                        <br>
                        ระบบควบคุมการเบิกจ่ายโครงการ v2.0
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js for dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/app.js"></script>
</body>
</html>