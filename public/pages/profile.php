<?php
/**
 * User Profile Page
 * Budget Control System v2
 */

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ?page=login');
    exit;
}

$message = '';
$messageType = 'info';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $displayName = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($displayName)) {
        $message = 'กรุณากรอกชื่อที่แสดง';
        $messageType = 'danger';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'กรุณากรอกอีเมลที่ถูกต้อง';
        $messageType = 'danger';
    } else {
        // Update profile
        $updateData = [
            'display_name' => $displayName,
            'email' => $email
        ];
        
        // If password change is requested
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                $message = 'กรุณากรอกรหัสผ่านปัจจุบัน';
                $messageType = 'danger';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'รหัสผ่านใหม่ไม่ตรงกัน';
                $messageType = 'danger';
            } elseif (strlen($newPassword) < 6) {
                $message = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
                $messageType = 'danger';
            } else {
                // Verify current password
                $user = $authService->getUserById($_SESSION['user_id']);
                if (!password_verify($currentPassword, $user['password_hash'])) {
                    $message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                    $messageType = 'danger';
                } else {
                    $updateData['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }
        }
        
        if (empty($message)) {
            $result = $authService->updateUser($_SESSION['user_id'], $updateData);
            if ($result['success']) {
                $message = 'อัปเดตโปรไฟล์สำเร็จ';
                $messageType = 'success';
                // Update session data
                $_SESSION['display_name'] = $displayName;
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        }
    }
}

// Get current user data
$currentUser = $authService->getUserById($_SESSION['user_id']);
if (!$currentUser) {
    header('Location: ?page=login');
    exit;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="bi bi-person-circle me-2"></i>
                    โปรไฟล์ผู้ใช้
                </h1>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-person-gear me-2"></i>
                        ข้อมูลส่วนตัว
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                    <input type="text" class="form-control" id="username" 
                                           value="<?= htmlspecialchars($currentUser['username']) ?>" 
                                           readonly>
                                    <div class="form-text">ไม่สามารถเปลี่ยนแปลงชื่อผู้ใช้ได้</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">บทบาท</label>
                                    <input type="text" class="form-control" id="role" 
                                           value="<?= $currentUser['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้ทั่วไป' ?>" 
                                           readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="display_name" class="form-label">ชื่อที่แสดง <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="display_name" name="display_name" 
                                           value="<?= htmlspecialchars($currentUser['display_name']) ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" 
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-key me-2"></i>
                            เปลี่ยนรหัสผ่าน (ไม่บังคับ)
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <div class="form-text">อย่างน้อย 6 ตัวอักษร</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>
                                บันทึกการเปลี่ยนแปลง
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-info-circle me-2"></i>
                        ข้อมูลบัญชี
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>วันที่สร้างบัญชี:</strong><br>
                        <span class="text-muted">
                            <?= date('d/m/Y H:i', strtotime($currentUser['created_at'])) ?> น.
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>เข้าสู่ระบบล่าสุด:</strong><br>
                        <span class="text-muted">
                            <?= $currentUser['last_login'] ? date('d/m/Y H:i', strtotime($currentUser['last_login'])) . ' น.' : 'ไม่มีข้อมูล' ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>สถานะบัญชี:</strong><br>
                        <span class="badge bg-<?= $currentUser['is_active'] ? 'success' : 'danger' ?>">
                            <?= $currentUser['is_active'] ? 'ใช้งานได้' : 'ถูกระงับ' ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid">
                        <a href="?page=dashboard" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-1"></i>
                            กลับสู่หน้าหลัก
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Security Tips -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="bi bi-shield-check me-2"></i>
                        เคล็ดลับความปลอดภัย
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            ใช้รหัสผ่านที่แข็งแกร่ง
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            เปลี่ยนรหัสผ่านเป็นประจำ
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            ไม่แชร์ข้อมูลการเข้าสู่ระบบ
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success me-2"></i>
                            ออกจากระบบเมื่อใช้งานเสร็จ
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('รหัสผ่านไม่ตรงกัน');
    } else {
        this.setCustomValidity('');
    }
});

// Show/hide password change section
document.getElementById('new_password').addEventListener('input', function() {
    const currentPasswordField = document.getElementById('current_password');
    if (this.value.length > 0) {
        currentPasswordField.required = true;
    } else {
        currentPasswordField.required = false;
    }
});
</script>