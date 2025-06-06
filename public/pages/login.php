<?php
/**
 * Login Page - Username/Password Authentication
 */

// Include SettingsService if not already included
if (!class_exists('SettingsService')) {
    require_once __DIR__ . '/../../src/Services/SettingsService.php';
    $settingsService = new SettingsService();
    $siteConfig = $settingsService->getSiteConfig();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $loginResult = $authService->login($username, $password);
        
        if ($loginResult['success']) {
            // Create session
            $sessionManager->createSession($loginResult['user']['id']);
            
            // Set success flag for JavaScript handling
            $success = 'login_success';
        } else {
            $error = $loginResult['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?= htmlspecialchars($siteConfig['site_title']) ?></title>
    
    <!-- Favicon -->
    <?php if ($siteConfig['site_icon']): ?>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($siteConfig['site_icon']) ?>">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($siteConfig['site_icon']) ?>">
    <?php else: ?>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <?php endif; ?>
    
    <!-- PWA Manifest -->
    <?php if ($siteConfig['enable_pwa']): ?>
    <link rel="manifest" href="../manifest.php">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($siteConfig['site_name']) ?>">
    <?php endif; ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            backdrop-filter: blur(10px);
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem 1rem 0 0;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-radius: 0.5rem 0 0 0.5rem;
            border-right: none;
        }
        .form-control.with-icon {
            border-radius: 0 0.5rem 0.5rem 0;
            border-left: none;
        }
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 70%;
            left: 80%;
            animation-delay: 2s;
        }
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 40%;
            left: 70%;
            animation-delay: 4s;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body>
    <!-- Floating background shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="login-container">
        <!-- Header -->
        <div class="login-header text-center py-4">
            <?php if ($siteConfig['site_icon']): ?>
            <img src="<?= htmlspecialchars($siteConfig['site_icon']) ?>" 
                 alt="<?= htmlspecialchars($siteConfig['site_name']) ?>" 
                 style="width: 64px; height: 64px; object-fit: contain;" 
                 class="mb-2">
            <?php else: ?>
            <i class="bi bi-shield-lock fs-1 mb-2"></i>
            <?php endif; ?>
            <h4 class="mb-1"><?= htmlspecialchars($siteConfig['site_name']) ?></h4>
            <small class="opacity-75"><?= htmlspecialchars($siteConfig['organization_name']) ?></small>
        </div>
        
        <!-- Login Form -->
        <div class="p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold">
                        <i class="bi bi-person me-1"></i>
                        ชื่อผู้ใช้
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" 
                               class="form-control with-icon" 
                               id="username" 
                               name="username" 
                               placeholder="กรอกชื่อผู้ใช้"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required 
                               autocomplete="username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">
                        <i class="bi bi-lock me-1"></i>
                        รหัสผ่าน
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control with-icon" 
                               id="password" 
                               name="password" 
                               placeholder="กรอกรหัสผ่าน"
                               required 
                               autocomplete="current-password">
                        <button class="btn btn-outline-secondary" 
                                type="button" 
                                id="togglePassword"
                                style="border-radius: 0 0.5rem 0.5rem 0;">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        เข้าสู่ระบบ
                    </button>
                </div>
            </form>
            
            <hr class="my-4">
            
            <div class="text-center">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    ติดต่อผู้ดูแลระบบหากมีปัญหาการเข้าสู่ระบบ
                </small>
            </div>
            
            <!-- Developer Information -->
            <div class="text-center mt-3">
                <div class="mb-2">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-gear-fill me-2"></i>
                        ระบบควบคุมการเบิกจ่ายโครงการ v2.0
                    </h6>
                </div>
                <div class="d-flex justify-content-center align-items-center flex-wrap gap-2 mb-2">
                    <div class="text-center">
                        <i class="bi bi-person-badge text-success me-1"></i>
                        <span class="text-muted">พัฒนาโดย:</span>
                        <strong class="text-dark">นายณัฐรวี วิเศษสมบัติ</strong>
                    </div>
                </div>
                <div class="d-flex justify-content-center align-items-center flex-wrap gap-2 mb-2">
                    <div class="text-center">
                        <i class="bi bi-briefcase text-primary me-1"></i>
                        <span class="text-muted">ตำแหน่ง:</span>
                        <strong class="text-dark">ครูผู้ช่วย</strong>
                    </div>
                </div>
                <div class="d-flex justify-content-center align-items-center flex-wrap gap-2 mb-2">
                    <div class="text-center">
                        <i class="bi bi-building text-warning me-1"></i>
                        <span class="text-muted">สังกัด:</span>
                        <strong class="text-dark"><?= htmlspecialchars($siteConfig['organization_name']) ?></strong>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                password.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        });
        
        // Form validation and loading
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
                return false;
            }
            
            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังเข้าสู่ระบบ...';
                submitBtn.disabled = true;
            }
            
            // Add a small delay to ensure the loading state is visible
            setTimeout(() => {
                // The form will submit naturally after this timeout
            }, 100);
        });
        
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Handle Enter key
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
        
        // Handle successful login redirect
        <?php if ($success === 'login_success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Show loading overlay
            document.body.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.95); display: flex; justify-content: center; align-items: center; z-index: 9999; flex-direction: column;">
                    <div style="width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p style="margin-top: 20px; color: #667eea; font-family: 'Sarabun', sans-serif;">กำลังเข้าสู่ระบบ...</p>
                    <style>
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                </div>
            `;
            
            // Redirect after a short delay
            setTimeout(function() {
                window.location.replace('index.php?page=dashboard');
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>