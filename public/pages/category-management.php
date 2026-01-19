<?php
/**
 * Category Management Page
 */

require_once __DIR__ . '/../../src/Services/CategoryService.php';

$categoryService = new CategoryService($db);

$error = '';
$success = '';
$editCategory = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $categoryKey = strtoupper(trim($_POST['category_key'] ?? ''));
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validation
        if (empty($categoryName)) {
            $error = 'กรุณากรอกชื่อหมวดหมู่';
        } elseif (empty($categoryKey)) {
            $error = 'กรุณากรอกรหัสหมวดหมู่';
        } elseif (!$categoryService->isValidCategoryKey($categoryKey)) {
            $error = 'รหัสหมวดหมู่ต้องเป็นตัวอักษรภาษาอังกฤษพิมพ์ใหญ่และขีดล่างเท่านั้น';
        } else {
            $categoryData = [
                'category_key' => $categoryKey,
                'category_name' => $categoryName,
                'description' => $description,
                'created_by' => $currentUser['id']
            ];
            
            if ($action === 'create') {
                $result = $categoryService->createCategory($categoryData);
                if ($result) {
                    $success = 'เพิ่มหมวดหมู่เรียบร้อยแล้ว';
                } else {
                    $error = 'รหัสหมวดหมู่นี้มีอยู่แล้ว';
                }
            } else {
                $categoryId = intval($_POST['category_id']);
                $result = $categoryService->updateCategory($categoryId, $categoryData);
                if ($result) {
                    $success = 'อัปเดตหมวดหมู่เรียบร้อยแล้ว';
                } else {
                    $error = 'รหัสหมวดหมู่นี้มีอยู่แล้ว';
                }
            }
        }
    } elseif ($action === 'delete') {
        $categoryId = intval($_POST['category_id']);
        $result = $categoryService->deleteCategory($categoryId);
        if ($result) {
            $success = 'ลบหมวดหมู่เรียบร้อยแล้ว';
        } else {
            $error = 'ไม่สามารถลบหมวดหมู่ที่มีการใช้งานอยู่';
        }
    } elseif ($action === 'restore') {
        $categoryId = intval($_POST['category_id']);
        $result = $categoryService->restoreCategory($categoryId);
        if ($result) {
            $success = 'กู้คืนหมวดหมู่เรียบร้อยแล้ว';
        } else {
            $error = 'เกิดข้อผิดพลาดในการกู้คืนหมวดหมู่';
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editCategory = $categoryService->getCategoryById($editId);
}

// Get all categories (including inactive ones for admin)
$categories = $categoryService->getAllCategories();
?>



<!-- Alert Messages -->
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

<!-- Category Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-<?= $editCategory ? 'pencil' : 'plus' ?> me-2"></i>
            <?= $editCategory ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่' ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="categoryForm">
            <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
            <?php if ($editCategory): ?>
            <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="category_key" class="form-label">รหัสหมวดหมู่ *</label>
                    <input type="text" class="form-control" id="category_key" name="category_key" 
                           value="<?= htmlspecialchars($editCategory['category_key'] ?? '') ?>"
                           placeholder="เช่น BOOKS, EQUIPMENT" 
                           pattern="[A-Z_]+" 
                           title="ใช้ตัวอักษรภาษาอังกฤษพิมพ์ใหญ่และขีดล่างเท่านั้น"
                           required>
                    <div class="form-text">ใช้ตัวอักษรภาษาอังกฤษพิมพ์ใหญ่และขีดล่าง (_) เท่านั้น</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="category_name" class="form-label">ชื่อหมวดหมู่ *</label>
                    <input type="text" class="form-control" id="category_name" name="category_name" 
                           value="<?= htmlspecialchars($editCategory['category_name'] ?? '') ?>"
                           placeholder="เช่น ค่าหนังสือและเอกสาร" 
                           required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="description" class="form-label">คำอธิบาย</label>
                    <input type="text" class="form-control" id="description" name="description" 
                           value="<?= htmlspecialchars($editCategory['description'] ?? '') ?>"
                           placeholder="คำอธิบายเพิ่มเติม">
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check me-1"></i>
                    <?= $editCategory ? 'อัปเดต' : 'เพิ่ม' ?>หมวดหมู่
                </button>
                <?php if ($editCategory): ?>
                <a href="?page=category-management" class="btn btn-secondary">
                    <i class="bi bi-x me-1"></i>
                    ยกเลิก
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-info" onclick="generateKey()">
                    <i class="bi bi-magic me-1"></i>
                    สร้างรหัสอัตโนมัติ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Categories List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            รายการหมวดหมู่ทั้งหมด (<?= count($categories) ?> หมวดหมู่)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($categories)): ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <p class="text-muted mt-2">ยังไม่มีหมวดหมู่งบประมาณ</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อหมวดหมู่</th>
                        <th>คำอธิบาย</th>
                        <th>สถานะ</th>
                        <th>วันที่สร้าง</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr class="<?= !$category['is_active'] ? 'table-secondary' : '' ?>">
                        <td>
                            <code><?= htmlspecialchars($category['category_key']) ?></code>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($category['category_name']) ?></strong>
                        </td>
                        <td>
                            <?= htmlspecialchars($category['description'] ?: '-') ?>
                        </td>
                        <td>
                            <?php if ($category['is_active']): ?>
                            <span class="badge bg-success">ใช้งาน</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">ไม่ใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($category['created_at'])) ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="?page=category-management&edit=<?= $category['id'] ?>" 
                                   class="btn btn-outline-primary" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($category['is_active']): ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['category_name']) ?>')" 
                                        title="ลบ">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-outline-success" 
                                        onclick="restoreCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['category_name']) ?>')" 
                                        title="กู้คืน">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ยืนยันการลบ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการลบหมวดหมู่ "<span id="deleteCategoryName"></span>" หรือไม่?</p>
                <p class="text-danger"><small>หมวดหมู่ที่มีการใช้งานอยู่จะไม่สามารถลบได้</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" id="deleteCategoryId">
                    <button type="submit" class="btn btn-danger">ลบหมวดหมู่</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ยืนยันการกู้คืน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>คุณต้องการกู้คืนหมวดหมู่ "<span id="restoreCategoryName"></span>" หรือไม่?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="category_id" id="restoreCategoryId">
                    <button type="submit" class="btn btn-success">กู้คืนหมวดหมู่</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Thai to English mapping for common budget category terms
const thaiToEnglishMap = {
    'หนังสือ': 'BOOKS',
    'เอกสาร': 'DOCUMENTS',
    'อุปกรณ์': 'EQUIPMENT',
    'เครื่องใช้': 'SUPPLIES',
    'วัสดุ': 'MATERIALS',
    'ค่าใช้จ่าย': 'EXPENSES',
    'ค่าตอบแทน': 'COMPENSATION',
    'ค่าเดินทาง': 'TRAVEL',
    'ค่าที่พัก': 'ACCOMMODATION',
    'ค่าอาหาร': 'MEALS',
    'ค่าขนส่ง': 'TRANSPORTATION',
    'ค่าบริการ': 'SERVICES',
    'ค่าซ่อมแซม': 'REPAIR',
    'ค่าบำรุงรักษา': 'MAINTENANCE',
    'ค่าสาธารณูปโภค': 'UTILITIES',
    'ค่าไฟฟ้า': 'ELECTRICITY',
    'ค่าน้ำ': 'WATER',
    'ค่าโทรศัพท์': 'TELEPHONE',
    'ค่าอินเทอร์เน็ต': 'INTERNET',
    'ค่าเช่า': 'RENTAL',
    'ค่าประกัน': 'INSURANCE',
    'ค่าฝึกอบรม': 'TRAINING',
    'ค่าสัมมนา': 'SEMINAR',
    'ค่าประชุม': 'MEETING',
    'ค่าโฆษณา': 'ADVERTISING',
    'ค่าพิมพ์': 'PRINTING',
    'ค่าจัดส่ง': 'DELIVERY',
    'ค่าบรรจุภัณฑ์': 'PACKAGING',
    'ค่าแรงงาน': 'LABOR',
    'ค่าที่ปรึกษา': 'CONSULTING',
    'ค่าออกแบบ': 'DESIGN',
    'ค่าพัฒนา': 'DEVELOPMENT',
    'ค่าทดสอบ': 'TESTING',
    'ค่าตรวจสอบ': 'INSPECTION',
    'ค่าประเมิน': 'EVALUATION',
    'ค่าวิจัย': 'RESEARCH',
    'ค่าศึกษา': 'STUDY',
    'ค่าสำรวจ': 'SURVEY',
    'ค่าวิเคราะห์': 'ANALYSIS',
    'ค่าจัดการ': 'MANAGEMENT',
    'ค่าดำเนินการ': 'OPERATION',
    'ค่าควบคุม': 'CONTROL',
    'ค่าติดตาม': 'MONITORING',
    'ค่าประสานงาน': 'COORDINATION'
};

// Generate category key from name
function generateKey() {
    const nameInput = document.getElementById('category_name');
    const keyInput = document.getElementById('category_key');
    
    if (nameInput.value.trim()) {
        let name = nameInput.value.trim();
        let key = '';
        
        // Check if the name contains Thai words and try to translate
        let foundTranslation = false;
        for (const [thai, english] of Object.entries(thaiToEnglishMap)) {
            if (name.includes(thai)) {
                key = english;
                foundTranslation = true;
                break;
            }
        }
        
        // If no translation found, use the original logic
        if (!foundTranslation) {
            key = name.toUpperCase();
            // Replace Thai characters and special characters with underscore
            key = key.replace(/[^A-Z0-9]/g, '_');
        }
        
        // Clean up the key
        key = key.replace(/_+/g, '_'); // Remove multiple underscores
        key = key.replace(/^_+|_+$/g, ''); // Remove leading/trailing underscores
        
        // If key is empty or too short, generate a generic one
        if (!key || key.length < 2) {
            key = 'CATEGORY_' + Date.now().toString().slice(-4);
        }
        
        keyInput.value = key;
    }
}

// Auto-generate key when typing name
document.getElementById('category_name').addEventListener('input', function() {
    if (!document.getElementById('category_key').value) {
        generateKey();
    }
});

// Delete category function
function deleteCategory(id, name) {
    document.getElementById('deleteCategoryId').value = id;
    document.getElementById('deleteCategoryName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Restore category function
function restoreCategory(id, name) {
    document.getElementById('restoreCategoryId').value = id;
    document.getElementById('restoreCategoryName').textContent = name;
    new bootstrap.Modal(document.getElementById('restoreModal')).show();
}

// Form validation
document.getElementById('categoryForm').addEventListener('submit', function(e) {
    const key = document.getElementById('category_key').value;
    const name = document.getElementById('category_name').value;
    
    if (!key.match(/^[A-Z_]+$/)) {
        e.preventDefault();
        alert('รหัสหมวดหมู่ต้องเป็นตัวอักษรภาษาอังกฤษพิมพ์ใหญ่และขีดล่างเท่านั้น');
        return false;
    }
    
    if (!name.trim()) {
        e.preventDefault();
        alert('กรุณากรอกชื่อหมวดหมู่');
        return false;
    }
});
</script>