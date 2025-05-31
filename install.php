<?php
/**
 * Budget Control System v2 - Installation Wizard
 * Similar to WordPress installation process
 */

// Configure session settings for installation
ini_set('session.cookie_lifetime', 7200); // 2 hours
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Try to set a writable session save path
$sessionPath = sys_get_temp_dir();
if (!is_writable($sessionPath)) {
    // Try alternative paths for shared hosting
    $altPaths = [
        __DIR__ . '/tmp',
        __DIR__ . '/sessions',
        '/tmp'
    ];
    
    foreach ($altPaths as $path) {
        if (!file_exists($path)) {
            @mkdir($path, 0755, true);
        }
        if (is_dir($path) && is_writable($path)) {
            $sessionPath = $path;
            break;
        }
    }
}

ini_set('session.save_path', $sessionPath);
ini_set('session.use_strict_mode', 0); // Disable for compatibility
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Set session name for installation
session_name('BUDGET_INSTALL_SESSION');

session_start();

// Regenerate session ID for security and ensure session persistence
if (!isset($_SESSION['install_session_started'])) {
    session_regenerate_id(true);
    $_SESSION['install_session_started'] = true;
    $_SESSION['install_start_time'] = time();
}

// Debug session for installation
if (isset($_GET['debug_session'])) {
    echo '<pre style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
    echo "=== SESSION DEBUG INFO ===\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session Name: " . session_name() . "\n";
    echo "Session Status: " . session_status() . " (1=disabled, 2=active, 3=none)\n";
    echo "Session Save Path: " . session_save_path() . "\n";
    echo "Session Save Path Writable: " . (is_writable(session_save_path()) ? 'YES' : 'NO') . "\n";
    echo "Current Step: " . ($_GET['step'] ?? 1) . "\n";
    echo "\nSession Cookie Params:\n";
    print_r(session_get_cookie_params());
    echo "\nSession Data:\n";
    print_r($_SESSION);
    echo "\n=== SERVER INFO ===\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
    echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
    echo '</pre>';
    echo '<a href="install.php" style="display: inline-block; margin: 20px; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px;">กลับไปติดตั้ง</a>';
    exit;
}

// Check if already installed
if (file_exists('config/database.php') && !isset($_GET['force'])) {
    $config = file_get_contents('config/database.php');
    if (strpos($config, 'localhost') !== false && strpos($config, 'your_database') === false) {
        header('Location: public/index.php');
        exit;
    }
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Database configuration
            $dbHost = trim($_POST['db_host'] ?? '');
            $dbName = trim($_POST['db_name'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_pass'] ?? '';
            $dbPort = intval($_POST['db_port'] ?? 3306);
            
            if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
                $errors[] = 'กรุณากรอกข้อมูลการเชื่อมต่อฐานข้อมูลให้ครบถ้วน';
            } else {
                // Test database connection
                try {
                    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]);
                    
                    // Check if database exists, create if not
                    $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
                    $stmt->execute([$dbName]);
                    
                    if (!$stmt->fetch()) {
                        $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    }
                    
                    // Store database config in session
                    $_SESSION['db_config'] = [
                        'host' => $dbHost,
                        'name' => $dbName,
                        'user' => $dbUser,
                        'pass' => $dbPass,
                        'port' => $dbPort
                    ];
                    
                    // Force session write and close to ensure data is saved
                    session_write_close();
                    
                    // Restart session for next request
                    session_start();
                    
                    // Debug: Log session after setting db_config
                    error_log("Step 2 - Session ID after setting db_config: " . session_id());
                    error_log("Step 2 - Session data after setting: " . print_r($_SESSION, true));
                    error_log("Step 2 - Session save path: " . session_save_path());
                    
                    $success[] = 'การเชื่อมต่อฐานข้อมูลสำเร็จ!';
                    
                    // Add a small delay to ensure session is written
                    usleep(100000); // 0.1 second
                    
                    header('Location: install.php?step=3');
                    exit;
                    
                } catch (PDOException $e) {
                    $errors[] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . $e->getMessage();
                }
            }
            break;
            
        case 3:
            // Install database tables
            // Debug: Log session state
            error_log("Step 3 - Session ID: " . session_id());
            error_log("Step 3 - Session name: " . session_name());
            error_log("Step 3 - Session save path: " . session_save_path());
            error_log("Step 3 - Session data: " . print_r($_SESSION, true));
            error_log("Step 3 - Cookie params: " . print_r(session_get_cookie_params(), true));
            
            // Check if session is working properly
            if (session_status() !== PHP_SESSION_ACTIVE) {
                error_log("Step 3 - Session is not active, status: " . session_status());
                $_SESSION['error_message'] = 'เกิดปัญหาเกี่ยวกับ Session กรุณาลองใหม่อีกครั้ง';
                header('Location: install.php?step=2');
                exit;
            }
            
            if (!isset($_SESSION['db_config'])) {
                error_log("Step 3 - db_config not found in session");
                error_log("Step 3 - Available session keys: " . implode(', ', array_keys($_SESSION)));
                $_SESSION['error_message'] = 'ข้อมูลการเชื่อมต่อฐานข้อมูลหายไป กรุณากรอกข้อมูลใหม่อีกครั้ง';
                header('Location: install.php?step=2');
                exit;
            } else {
                error_log("Step 3 - db_config found: " . print_r($_SESSION['db_config'], true));
                try {
                    $config = $_SESSION['db_config'];
                    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]);
                    
                    // Read and execute SQL file
                    $sqlFile = 'database/schema_mysql.sql';
                    if (!file_exists($sqlFile)) {
                        $_SESSION['error_message'] = 'ไม่พบไฟล์ SQL สำหรับสร้างฐานข้อมูล';
                        header('Location: install.php?step=2');
                        exit;
                    } else {
                        $sql = file_get_contents($sqlFile);
                        // Remove comments and split by semicolon
                        $sql = preg_replace('/--.*$/m', '', $sql);
                        $statements = explode(';', $sql);
                        
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if (!empty($statement)) {
                                // Skip CREATE DATABASE and USE statements
                                if (stripos($statement, 'CREATE DATABASE') === false && 
                                    stripos($statement, 'USE ') === false) {
                                    $pdo->exec($statement);
                                }
                            }
                        }
                        
                        $success[] = 'สร้างตารางฐานข้อมูลสำเร็จ!';
                        header('Location: install.php?step=4');
                        exit;
                    }
                    
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการสร้างตารางฐานข้อมูล: ' . $e->getMessage();
                    header('Location: install.php?step=2');
                    exit;
                }
            }
            break;
            
        case 4:
            // Create admin user
            $username = trim($_POST['admin_username'] ?? '');
            $email = trim($_POST['admin_email'] ?? '');
            $fullName = trim($_POST['admin_fullname'] ?? '');
            $password = $_POST['admin_password'] ?? '';
            $confirmPassword = $_POST['admin_confirm_password'] ?? '';
            
            if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
                $errors[] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } elseif ($password !== $confirmPassword) {
                $errors[] = 'รหัสผ่านไม่ตรงกัน';
            } elseif (strlen($password) < 6) {
                $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
            } else {
                try {
                    $config = $_SESSION['db_config'];
                    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]);
                    
                    // Check if username or email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    
                    if ($stmt->fetch()) {
                        $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่แล้ว';
                    } else {
                        // Create admin user
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, email, display_name, password_hash, role, approved, created_at) 
                            VALUES (?, ?, ?, ?, 'admin', 1, NOW())
                        ");
                        $stmt->execute([$username, $email, $fullName, $hashedPassword]);
                        
                        $_SESSION['admin_created'] = true;
                        $success[] = 'สร้างบัญชีผู้ดูแลระบบสำเร็จ!';
                        header('Location: install.php?step=5');
                        exit;
                    }
                    
                } catch (PDOException $e) {
                    $errors[] = 'เกิดข้อผิดพลาดในการสร้างบัญชีผู้ใช้: ' . $e->getMessage();
                }
            }
            break;
            
        case 5:
            // Create configuration files
            if (!isset($_SESSION['db_config']) || !isset($_SESSION['admin_created'])) {
                $errors[] = 'ข้อมูลการติดตั้งไม่ครบถ้วน กรุณาเริ่มต้นใหม่';
                header('Location: install.php?step=1');
                exit;
            } else {
                try {
                    $config = $_SESSION['db_config'];
                    
                    // Create database.php
                    $dbConfigContent = "<?php\n/**\n * Database Configuration\n * Generated by Installation Wizard\n */\n\nclass Database {\n    private \$host = '{$config['host']}';\n    private \$port = {$config['port']};\n    private \$db_name = '{$config['name']}';\n    private \$username = '{$config['user']}';\n    private \$password = '{$config['pass']}';\n    public \$conn;\n\n    public function getConnection() {\n        \$this->conn = null;\n        try {\n            \$this->conn = new PDO(\n                \"mysql:host=\" . \$this->host . \";port=\" . \$this->port . \";dbname=\" . \$this->db_name . \";charset=utf8mb4\",\n                \$this->username,\n                \$this->password,\n                [\n                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n                    PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4\"\n                ]\n            );\n        } catch(PDOException \$exception) {\n            error_log(\"Connection error: \" . \$exception->getMessage());\n            throw \$exception;\n        }\n        return \$this->conn;\n    }\n}\n?>";
                    
                    if (!is_dir('config')) {
                        mkdir('config', 0755, true);
                    }
                    
                    file_put_contents('config/database.php', $dbConfigContent);
                    
                    // Create .env file
                    $envContent = "# Budget Control System v2 Environment Configuration\n# Generated by Installation Wizard\n\n# Application Settings\nAPP_NAME=\"Budget Control System v2\"\nAPP_ENV=production\nAPP_DEBUG=false\nAPP_URL=http://localhost\n\n# Database Configuration\nDB_HOST={$config['host']}\nDB_PORT={$config['port']}\nDB_DATABASE={$config['name']}\nDB_USERNAME={$config['user']}\nDB_PASSWORD={$config['pass']}\n\n# Session Configuration\nSESSION_LIFETIME=7200\nSESSION_SECURE=false\nSESSION_HTTPONLY=true\n\n# Security\nAPP_KEY=" . bin2hex(random_bytes(32)) . "\n";
                    
                    file_put_contents('.env', $envContent);
                    
                    // Clear session data
                    unset($_SESSION['db_config']);
                    unset($_SESSION['admin_created']);
                    
                    $success[] = 'การติดตั้งเสร็จสมบูรณ์!';
                    header('Location: install.php?step=6');
                    exit;
                    
                } catch (Exception $e) {
                    $errors[] = 'เกิดข้อผิดพลาดในการสร้างไฟล์การตั้งค่า: ' . $e->getMessage();
                }
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตั้งระบบ Budget Control v2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .install-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .install-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .install-body {
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            font-weight: bold;
            position: relative;
        }
        .step.active {
            background: #4facfe;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .step.pending {
            background: #e9ecef;
            color: #6c757d;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 30px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
        }
        .step.completed:not(:last-child)::after {
            background: #28a745;
        }
        .form-control:focus {
            border-color: #4facfe;
            box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .feature-list li:last-child {
            border-bottom: none;
        }
        .feature-list i {
            color: #28a745;
            margin-right: 0.5rem;
        }
        .requirements-table {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
        }
        .status-ok {
            color: #28a745;
        }
        .status-error {
            color: #dc3545;
        }
        .status-warning {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><i class="bi bi-gear-fill"></i> ติดตั้งระบบ Budget Control v2</h1>
                <p class="mb-0">ระบบจัดการงบประมาณที่ทันสมัยและใช้งานง่าย</p>
            </div>
            
            <div class="install-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <div class="step <?= $i < $step ? 'completed' : ($i == $step ? 'active' : 'pending') ?>">
                            <?= $i < $step ? '<i class="bi bi-check"></i>' : $i ?>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <!-- Debug Link -->
                <div class="text-center mb-3">
                    <a href="install.php?debug_session&step=<?= $step ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="bi bi-bug"></i> ตรวจสอบ Session Debug
                    </a>
                </div>
                
                <!-- Error Messages -->
                <?php 
                // Check for session error message
                if (isset($_SESSION['error_message'])) {
                    $errors[] = $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                }
                ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle-fill"></i> เกิดข้อผิดพลาด</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Success Messages -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <h6><i class="bi bi-check-circle-fill"></i> สำเร็จ</h6>
                        <ul class="mb-0">
                            <?php foreach ($success as $msg): ?>
                                <li><?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Step Content -->
                <?php switch ($step): case 1: ?>
                    <!-- Welcome Step -->
                    <div class="text-center mb-4">
                        <h2><i class="bi bi-house-heart-fill text-primary"></i> ยินดีต้อนรับ</h2>
                        <p class="text-muted">ขอบคุณที่เลือกใช้ระบบ Budget Control v2</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="bi bi-star-fill text-warning"></i> คุณสมบัติหลัก</h5>
                            <ul class="feature-list">
                                <li><i class="bi bi-check-circle-fill"></i> จัดการโครงการและงบประมาณ</li>
                                <li><i class="bi bi-check-circle-fill"></i> บันทึกรายรับ-รายจ่าย</li>
                                <li><i class="bi bi-check-circle-fill"></i> รายงานและสถิติ</li>
                                <li><i class="bi bi-check-circle-fill"></i> ส่งออกข้อมูล Excel/CSV</li>
                                <li><i class="bi bi-check-circle-fill"></i> จัดการผู้ใช้งาน</li>
                                <li><i class="bi bi-check-circle-fill"></i> ระบบความปลอดภัย</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="bi bi-gear-fill text-info"></i> ความต้องการของระบบ</h5>
                            <div class="requirements-table">
                                <?php
                                $requirements = [
                                    'PHP Version' => ['required' => '7.4+', 'current' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '7.4.0', '>=')],
                                    'PDO Extension' => ['required' => 'Required', 'current' => extension_loaded('pdo') ? 'Available' : 'Missing', 'status' => extension_loaded('pdo')],
                                    'PDO MySQL' => ['required' => 'Required', 'current' => extension_loaded('pdo_mysql') ? 'Available' : 'Missing', 'status' => extension_loaded('pdo_mysql')],
                                    'JSON Extension' => ['required' => 'Required', 'current' => extension_loaded('json') ? 'Available' : 'Missing', 'status' => extension_loaded('json')],
                                    'Session Support' => ['required' => 'Required', 'current' => extension_loaded('session') ? 'Available' : 'Missing', 'status' => extension_loaded('session')],
                                ];
                                ?>
                                <?php foreach ($requirements as $name => $req): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= $name ?></span>
                                        <span class="<?= $req['status'] ? 'status-ok' : 'status-error' ?>">
                                            <i class="bi bi-<?= $req['status'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                                            <?= $req['current'] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="?step=2" class="btn btn-primary btn-lg">
                            <i class="bi bi-arrow-right"></i> เริ่มการติดตั้ง
                        </a>
                    </div>
                    
                <?php break; case 2: ?>
                    <!-- Database Configuration -->
                    <div class="text-center mb-4">
                        <h2><i class="bi bi-database-fill text-primary"></i> การตั้งค่าฐานข้อมูล</h2>
                        <p class="text-muted">กรุณากรอกข้อมูลการเชื่อมต่อฐานข้อมูล MySQL</p>
                    </div>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-server"></i> เซิร์ฟเวอร์ฐานข้อมูล</label>
                                    <input type="text" class="form-control" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-ethernet"></i> พอร์ต</label>
                                    <input type="number" class="form-control" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-database"></i> ชื่อฐานข้อมูล</label>
                            <input type="text" class="form-control" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'budget_control_v2') ?>" required>
                            <div class="form-text">หากฐานข้อมูลไม่มีอยู่ ระบบจะสร้างให้อัตโนมัติ</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-person-fill"></i> ชื่อผู้ใช้</label>
                                    <input type="text" class="form-control" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-key-fill"></i> รหัสผ่าน</label>
                                    <input type="password" class="form-control" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="?step=1" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> ย้อนกลับ
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-database-check"></i> ทดสอบการเชื่อมต่อ
                            </button>
                        </div>
                    </form>
                    
                <?php break; case 3: ?>
                    <!-- Database Installation -->
                        <div class="text-center mb-4">
                            <h2><i class="bi bi-gear-wide-connected text-primary"></i> สร้างตารางฐานข้อมูล</h2>
                            <p class="text-muted">ระบบจะสร้างตารางที่จำเป็นในฐานข้อมูล</p>
                        </div>
                        
                        <form method="POST">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle-fill"></i> ข้อมูลการเชื่อมต่อ</h6>
                                <p class="mb-0">
                                    <strong>เซิร์ฟเวอร์:</strong> <?= htmlspecialchars($_SESSION['db_config']['host'] ?? '') ?>:<?= htmlspecialchars($_SESSION['db_config']['port'] ?? '') ?><br>
                                    <strong>ฐานข้อมูล:</strong> <?= htmlspecialchars($_SESSION['db_config']['name'] ?? '') ?><br>
                                    <strong>ผู้ใช้:</strong> <?= htmlspecialchars($_SESSION['db_config']['user'] ?? '') ?>
                                </p>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-database-add"></i> สร้างตารางฐานข้อมูล
                                </button>
                            </div>
                        </form>
                    
                <?php break; case 4: ?>
                    <!-- Admin User Creation -->
                    <div class="text-center mb-4">
                        <h2><i class="bi bi-person-plus-fill text-primary"></i> สร้างบัญชีผู้ดูแลระบบ</h2>
                        <p class="text-muted">สร้างบัญชีผู้ดูแลระบบสำหรับเข้าใช้งานครั้งแรก</p>
                    </div>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-person-circle"></i> ชื่อผู้ใช้</label>
                                    <input type="text" class="form-control" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? 'admin') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-envelope-fill"></i> อีเมล</label>
                                    <input type="email" class="form-control" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-person-badge-fill"></i> ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control" name="admin_fullname" value="<?= htmlspecialchars($_POST['admin_fullname'] ?? '') ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-lock-fill"></i> รหัสผ่าน</label>
                                    <input type="password" class="form-control" name="admin_password" required>
                                    <div class="form-text">อย่างน้อย 6 ตัวอักษร</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-lock-fill"></i> ยืนยันรหัสผ่าน</label>
                                    <input type="password" class="form-control" name="admin_confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="?step=2" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> ย้อนกลับ
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> สร้างบัญชี
                            </button>
                        </div>
                    </form>
                    
                <?php break; case 5: ?>
                    <!-- Configuration Files -->
                    <div class="text-center mb-4">
                        <h2><i class="bi bi-file-earmark-code-fill text-primary"></i> สร้างไฟล์การตั้งค่า</h2>
                        <p class="text-muted">สร้างไฟล์การตั้งค่าที่จำเป็นสำหรับระบบ</p>
                    </div>
                    
                    <form method="POST">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle-fill"></i> ข้อควรระวัง</h6>
                            <p class="mb-0">ระบบจะสร้างไฟล์การตั้งค่าและเขียนทับไฟล์เดิม (หากมี)</p>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-file-plus"></i> สร้างไฟล์การตั้งค่า
                            </button>
                        </div>
                    </form>
                    
                <?php break; case 6: ?>
                    <!-- Installation Complete -->
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        
                        <h2 class="text-success">การติดตั้งเสร็จสมบูรณ์!</h2>
                        <p class="text-muted mb-4">ระบบ Budget Control v2 พร้อมใช้งานแล้ว</p>
                        
                        <div class="alert alert-success">
                            <h6><i class="bi bi-info-circle-fill"></i> ข้อมูลสำคัญ</h6>
                            <p class="mb-0">
                                <strong>URL ระบบ:</strong> <a href="public/index.php" target="_blank">public/index.php</a><br>
                                <strong>ชื่อผู้ใช้:</strong> admin<br>
                                <strong>บทบาท:</strong> ผู้ดูแลระบบ
                            </p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-shield-exclamation"></i> ความปลอดภัย</h6>
                            <p class="mb-0">
                                เพื่อความปลอดภัย กรุณาลบหรือเปลี่ยนชื่อไฟล์ <code>install.php</code> หลังจากการติดตั้งเสร็จสิ้น
                            </p>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="public/index.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
                            </a>
                            <a href="?step=1&force=1" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> ติดตั้งใหม่
                            </a>
                        </div>
                    </div>
                    
                <?php break; endswitch; ?>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-white-50">
                Budget Control System v2 &copy; <?= date('Y') ?> | 
                <a href="https://github.com" class="text-white-50">GitHub</a> | 
                <a href="mailto:support@example.com" class="text-white-50">Support</a>
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>