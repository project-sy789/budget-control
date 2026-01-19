<?php
/**
 * System Settings Management Page
 */

// Check if user is admin
if (!$currentUser || $currentUser['role'] !== 'admin') {
    echo '<div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
    return;
}

// Include SettingsService
require_once __DIR__ . '/../../src/Services/SettingsService.php';
$settingsService = new SettingsService();

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $settings = [
                    'site_name' => trim($_POST['site_name'] ?? ''),
                    'organization_name' => trim($_POST['organization_name'] ?? ''),
                    'site_title' => trim($_POST['site_title'] ?? ''),
                    'enable_pwa' => isset($_POST['enable_pwa']) ? '1' : '0',
                    'year_label_type' => $_POST['year_label_type'] ?? 'fiscal_year'
                ];
                
                if ($settingsService->updateSettings($settings)) {
                    $message = 'บันทึกการตั้งค่าเรียบร้อยแล้ว';
                    $messageType = 'success';
                } else {
                    $message = 'เกิดข้อผิดพลาดในการบันทึกการตั้งค่า';
                    $messageType = 'danger';
                }
                break;
                
            case 'upload_icon':
                if (isset($_FILES['site_icon']) && $_FILES['site_icon']['error'] === UPLOAD_ERR_OK) {
                    $result = $settingsService->uploadSiteIcon($_FILES['site_icon']);
                    if ($result['success']) {
                        $message = 'อัปโหลดไอคอนเรียบร้อยแล้ว';
                        $messageType = 'success';
                    } else {
                        $message = $result['message'];
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'กรุณาเลือกไฟล์ไอคอน';
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_icon':
                $settingsService->deleteSiteIcon();
                $message = 'ลบไอคอนเรียบร้อยแล้ว';
                $messageType = 'success';
                break;
        }
    }
}

// Get current settings
$siteConfig = $settingsService->getSiteConfig();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>
                        การตั้งค่าระบบ
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Basic Settings -->
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">
                                        <i class="bi bi-globe me-1"></i>
                                        ชื่อเว็บไซต์
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="site_name" 
                                           name="site_name" 
                                           value="<?= htmlspecialchars($siteConfig['site_name']) ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="organization_name" class="form-label">
                                        <i class="bi bi-building me-1"></i>
                                        ชื่อหน่วยงาน
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="organization_name" 
                                           name="organization_name" 
                                           value="<?= htmlspecialchars($siteConfig['organization_name']) ?>"
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="site_title" class="form-label">
                                <i class="bi bi-card-heading me-1"></i>
                                ชื่อเรื่องของเว็บไซต์ (แสดงในแท็บเบราว์เซอร์)
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="site_title" 
                                   name="site_title" 
                                   value="<?= htmlspecialchars($siteConfig['site_title']) ?>"
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="year_label_type" class="form-label">
                                <i class="bi bi-calendar-event me-1"></i>
                                รูปแบบชื่อปีที่ใช้เรียก
                            </label>
                            <select class="form-select" id="year_label_type" name="year_label_type">
                                <option value="fiscal_year" <?= ($siteConfig['year_label_type'] ?? 'fiscal_year') === 'fiscal_year' ? 'selected' : '' ?>>ปีงบประมาณ</option>
                                <option value="academic_year" <?= ($siteConfig['year_label_type'] ?? '') === 'academic_year' ? 'selected' : '' ?>>ปีการศึกษา</option>
                                <option value="budget_year" <?= ($siteConfig['year_label_type'] ?? '') === 'budget_year' ? 'selected' : '' ?>>ปีบัญชี</option>
                            </select>
                            <div class="form-text">เลือกรูปแบบการเรียกชื่อปีที่จะแสดงทั่วทั้งระบบ</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="enable_pwa" 
                                       name="enable_pwa"
                                       <?= $siteConfig['enable_pwa'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enable_pwa">
                                    <i class="bi bi-phone me-1"></i>
                                    เปิดใช้งาน Progressive Web App (PWA) - สามารถติดตั้งเป็นแอปบนโทรศัพท์ได้
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>
                            บันทึกการตั้งค่า
                        </button>
                    </form>
                    
                    <hr>
                    
                    <!-- Icon Upload -->
                    <h6 class="mb-3">
                        <i class="bi bi-image me-2"></i>
                        ไอคอนเว็บไซต์
                    </h6>
                    
                    <?php if ($siteConfig['site_icon']): ?>
                    <div class="mb-3">
                        <div class="d-flex align-items-center">
                            <img src="<?= htmlspecialchars($siteConfig['site_icon']) ?>" 
                                 alt="Site Icon" 
                                 class="me-3" 
                                 style="width: 64px; height: 64px; object-fit: contain; border: 1px solid #dee2e6; border-radius: 8px; padding: 4px;">
                            <div>
                                <p class="mb-1"><strong>ไอคอนปัจจุบัน</strong></p>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete_icon">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('คุณต้องการลบไอคอนนี้หรือไม่?')">
                                        <i class="bi bi-trash me-1"></i>
                                        ลบไอคอน
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_icon">
                        
                        <div class="mb-3">
                            <label for="site_icon" class="form-label">
                                อัปโหลดไอคอนใหม่
                            </label>
                            <input type="file" 
                                   class="form-control" 
                                   id="site_icon" 
                                   name="site_icon" 
                                   accept="image/*"
                                   required>
                            <div class="form-text">
                                รองรับไฟล์: JPG, PNG, GIF, WebP, SVG (ขนาดสูงสุด 2MB)<br>
                                แนะนำขนาด: 512x512 พิกเซล สำหรับการแสดงผลที่ดีที่สุด
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload me-2"></i>
                            อัปโหลดไอคอน
                        </button>
                    </form>
                    
                    <hr>
                    
                    <!-- Preview -->
                    <h6 class="mb-3">
                        <i class="bi bi-eye me-2"></i>
                        ตัวอย่างการแสดงผล
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">หน้าเข้าสู่ระบบ</h6>
                                </div>
                                <div class="card-body text-center">
                                    <?php if ($siteConfig['site_icon']): ?>
                                    <img src="<?= htmlspecialchars($siteConfig['site_icon']) ?>" 
                                         alt="Icon" 
                                         style="width: 48px; height: 48px; object-fit: contain;">
                                    <?php else: ?>
                                    <i class="bi bi-shield-lock" style="font-size: 48px; color: #667eea;"></i>
                                    <?php endif; ?>
                                    <h5 class="mt-2"><?= htmlspecialchars($siteConfig['site_name']) ?></h5>
                                    <small class="text-muted"><?= htmlspecialchars($siteConfig['organization_name']) ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">แท็บเบราว์เซอร์</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-center p-2 bg-light rounded">
                                        <?php if ($siteConfig['site_icon']): ?>
                                        <img src="<?= htmlspecialchars($siteConfig['site_icon']) ?>" 
                                             alt="Icon" 
                                             style="width: 16px; height: 16px; object-fit: contain;" 
                                             class="me-2">
                                        <?php else: ?>
                                        <i class="bi bi-globe me-2" style="font-size: 16px;"></i>
                                        <?php endif; ?>
                                        <span style="font-size: 14px;"><?= htmlspecialchars($siteConfig['site_title']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview uploaded image
document.getElementById('site_icon').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // You can add image preview functionality here if needed
        };
        reader.readAsDataURL(file);
    }
});
</script>