<?php
/**
 * Projects Management Page
 */

// Ensure no output before headers
// ob_start(); // Commented out to fix blank content issue

$error = '';
$success = '';
$editProject = null;

// Handle success messages from redirects
if (isset($_GET['success'])) {
    $success = 'สร้างโครงการเรียบร้อยแล้ว';
} elseif (isset($_GET['updated'])) {
    $success = 'อัปเดตโครงการเรียบร้อยแล้ว';
} elseif (isset($_GET['deleted'])) {
    $success = 'ลบโครงการเรียบร้อยแล้ว';
}

// Hide loading overlay if redirected with success message
$hasSuccessMessage = isset($_GET['success']) || isset($_GET['updated']) || isset($_GET['deleted']);

// Initialize services if not available (for standalone testing)
if (!isset($projectService)) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../src/Services/ProjectService.php';
    require_once __DIR__ . '/../../src/Services/FiscalYearService.php';
    $database = new Database();
    $db = $database->getConnection();
    $projectService = new ProjectService();
    $fiscalYearService = new FiscalYearService();
} else {
    // If services already exist, ensure we have fiscalYearService
    if (!isset($fiscalYearService)) {
        require_once __DIR__ . '/../../src/Services/FiscalYearService.php';
        $fiscalYearService = new FiscalYearService();
    }
}

// Initialize current user if not available
if (!isset($currentUser)) {
    $currentUser = ['id' => 1]; // Default for testing
}

// Ensure $yearLabel is set correctly based on configuration
if (!isset($yearLabel)) {
    // If not set globally (e.g. standalone test), fetch from settings
    if (!class_exists('SettingsService')) {
        require_once __DIR__ . '/../../src/Services/SettingsService.php';
    }
    $settingsService = new SettingsService();
    $tempConfig = $settingsService->getSiteConfig();
    $yearLabelType = $tempConfig['year_label_type'] ?? 'fiscal_year';
    
    switch ($yearLabelType) {
        case 'academic_year': $yearLabel = 'ปีการศึกษา'; break;
        case 'budget_year': $yearLabel = 'ปีบัญชี'; break;
        case 'fiscal_year': default: $yearLabel = 'ปีงบประมาณ'; break;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $workGroup = $_POST['work_group'] ?? '';
        $responsiblePerson = trim($_POST['responsible_person'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $fiscalYearId = $_POST['fiscal_year_id'] ?? '';
        $budgetCategories = $_POST['budget_categories'] ?? [];
        
        // Process budget categories
        $validCategories = [];
        $totalCategoryBudget = 0;
        
        if (isset($_POST['budget_categories']) && is_array($_POST['budget_categories'])) {
            foreach ($_POST['budget_categories'] as $category) {
                $categoryName = trim($category['category'] ?? '');
                $categoryBudget = floatval($category['budget'] ?? 0);
                $categoryDescription = trim($category['description'] ?? '');
                
                if (!empty($categoryName) && $categoryBudget > 0) {
                    $validCategories[] = [
                        'category' => $categoryName,
                        'amount' => $categoryBudget,
                        'description' => $categoryDescription
                    ];
                    $totalCategoryBudget += $categoryBudget;
                }
            }
        }
        
        $totalBudget = $totalCategoryBudget;
        
        // Validation
        // For update action with existing transactions, only validate status change
        $isStatusOnlyUpdate = ($action === 'update' && isset($_POST['project_id']));
        if ($isStatusOnlyUpdate) {
            // Check if project has transactions
            $projectId = intval($_POST['project_id']);
            $hasTransactions = false;
            try {
                $transactionCheckQuery = "SELECT COUNT(*) as count FROM transactions WHERE project_id = :project_id";
                $transactionCheckStmt = $db->prepare($transactionCheckQuery);
                $transactionCheckStmt->bindParam(':project_id', $projectId);
                $transactionCheckStmt->execute();
                $transactionResult = $transactionCheckStmt->fetch(PDO::FETCH_ASSOC);
                $hasTransactions = $transactionResult['count'] > 0;
            } catch (Exception $e) {
                // Ignore error, assume no transactions
            }
            
            if ($hasTransactions) {
                // For projects with transactions, only validate status
                if (empty($status)) {
                    $error = 'กรุณาเลือกสถานะโครงการ';
                }
            } else {
                // For projects without transactions, validate normally
                if (empty($name) || empty($workGroup) || empty($responsiblePerson) || $totalBudget <= 0) {
                    $error = 'กรุณากรอกข้อมูลให้ครบถ้วนและต้องมีหมวดหมู่งบประมาณอย่างน้อย 1 หมวดหมู่';
                }
            }
        } else {
            // For create action, validate normally
            if (empty($name) || empty($workGroup) || empty($responsiblePerson) || $totalBudget <= 0) {
                $error = 'กรุณากรอกข้อมูลให้ครบถ้วนและต้องมีหมวดหมู่งบประมาณอย่างน้อย 1 หมวดหมู่';
            }
        }
        
        if (empty($error) && strlen($name) > 255) {
            $error = 'ชื่อโครงการต้องไม่เกิน 255 ตัวอักษร';
        } elseif (strtotime($endDate) < strtotime($startDate)) {
            $error = 'วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มต้น';
        } elseif (!empty($description) && strlen($description) > 1000) {
            $error = 'คำอธิบายต้องไม่เกิน 1000 ตัวอักษร';
        } else {
            // For projects with transactions, only update status
            if ($isStatusOnlyUpdate && isset($hasTransactions) && $hasTransactions) {
                $projectData = [
                    'status' => $status
                ];
            } else {
                $projectData = [
                    'name' => $name,
                    'description' => $description,
                    'work_group' => $workGroup,
                    'responsible_person' => $responsiblePerson,
                    'budget' => $totalBudget,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status,
                    'fiscal_year_id' => $fiscalYearId,
                    'budget_categories' => $validCategories
                ];
            }
            
            if ($action === 'create') {
                try {
                    $result = $projectService->createProject($projectData, $currentUser['id']);
                    
                    if ($result['success']) {
                        $success = 'สร้างโครงการเรียบร้อยแล้ว';
                        // Clear form data after successful creation
                        // header('Location: ?page=projects&success=1');
                        // exit;
                    } else {
                        $error = $result['message'] ?? 'เกิดข้อผิดพลาดในการสร้างโครงการ';
                    }
                } catch (Exception $e) {
                    $error = 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage();
                }
            } else {
                try {
                    $projectId = intval($_POST['project_id']);
                    $result = $projectService->updateProject($projectId, $projectData);
                    
                    if ($result['success']) {
                        $success = 'อัปเดตโครงการเรียบร้อยแล้ว';
                        // Redirect to prevent form resubmission
                        // header('Location: ?page=projects&updated=1');
                        // exit;
                    } else {
                        $error = $result['message'] ?? 'เกิดข้อผิดพลาดในการอัปเดตโครงการ';
                    }
                } catch (Exception $e) {
                    $error = 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage();
                }
            }
         }
    } elseif ($action === 'delete') {
        try {
            $projectId = intval($_POST['project_id']);
            $result = $projectService->deleteProject($projectId);
            
            if ($result['success']) {
                $success = $result['message'] ?? 'ลบโครงการเรียบร้อยแล้ว';
                // header('Location: ?page=projects&deleted=1');
                // exit;
            } else {
                $error = $result['message'] ?? 'ไม่สามารถลบโครงการได้';
            }
        } catch (Exception $e) {
            $error = 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage();
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editProject = $projectService->getProjectById($editId);
    if ($editProject) {
        $editProject['categories'] = $projectService->getProjectBudgetCategories($editId);
    }
}

// Get filter parameters
$filterWorkGroup = $_GET['filter_work_group'] ?? 'all';
$filterStatus = $_GET['filter_status'] ?? 'all';
$filterFiscalYear = $_GET['filter_fiscal_year'] ?? 'all';
$searchTerm = trim($_GET['search_term'] ?? '');

// Pagination parameters
$page = max(1, intval($_GET['page_num'] ?? 1));
$perPage = intval($_GET['per_page'] ?? 10);
$validPerPageOptions = [5, 10, 20, 50, 100];
if (!in_array($perPage, $validPerPageOptions)) {
    $perPage = 10;
}

// Get all projects with filters
$allProjects = $projectService->getAllProjects(
    $filterWorkGroup !== 'all' ? $filterWorkGroup : null,
    $filterStatus !== 'all' ? $filterStatus : null,
    $filterFiscalYear !== 'all' ? $filterFiscalYear : null
);

// Apply search filter if provided
if (!empty($searchTerm)) {
    $allProjects = array_filter($allProjects, function($project) use ($searchTerm) {
        return stripos($project['name'], $searchTerm) !== false ||
               stripos($project['description'], $searchTerm) !== false ||
               stripos($project['responsible_person'], $searchTerm) !== false;
    });
}

// Calculate pagination
$totalProjects = count($allProjects);
$totalPages = ceil($totalProjects / $perPage);
$page = min($page, max(1, $totalPages)); // Ensure page is within valid range
$offset = ($page - 1) * $perPage;

// Get projects for current page
$projects = array_slice($allProjects, $offset, $perPage);

// Pagination info
$startItem = $totalProjects > 0 ? $offset + 1 : 0;
$endItem = min($offset + $perPage, $totalProjects);

// Work groups
$workGroups = [
    'academic' => 'งานวิชาการ',
    'budget' => 'งานงบประมาณ',
    'hr' => 'งานบุคลากร',
    'general' => 'งานทั่วไป',
    'other' => 'อื่น ๆ'
];

// Get dynamic budget categories from CategoryService
require_once '../src/Services/CategoryService.php';
$categoryService = new CategoryService($db);
$budgetCategoriesData = $categoryService->getAllActiveCategories();
$budgetCategories = [];
foreach ($budgetCategoriesData as $category) {
    $budgetCategories[$category['category_key']] = $category['category_name'];
}

// Get Fiscal Years
$fiscalYears = $fiscalYearService->getAll();
$activeFiscalYear = $fiscalYearService->getActiveYear();


// Helper function to build pagination URLs
function buildPaginationUrl($pageNum) {
    $params = $_GET;
    $params['page'] = 'projects';
    $params['page_num'] = $pageNum;
    return '?' . http_build_query($params);
}
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

<!-- Project Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-<?= $editProject ? 'pencil' : 'plus' ?>-circle me-2"></i>
            <?= $editProject ? 'แก้ไขโครงการ' : 'เพิ่มโครงการใหม่' ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if ($editProject): ?>
        <?php 
            // Check if project has any transactions
            $hasTransactions = false;
            $hasTransfers = false;
            try {
                // Check for any transactions
                $transactionCheckQuery = "SELECT COUNT(*) as count FROM transactions WHERE project_id = :project_id";
                $transactionCheckStmt = $db->prepare($transactionCheckQuery);
                $transactionCheckStmt->bindParam(':project_id', $editProject['id']);
                $transactionCheckStmt->execute();
                $transactionResult = $transactionCheckStmt->fetch(PDO::FETCH_ASSOC);
                $hasTransactions = $transactionResult['count'] > 0;
                
                // Check for transfer transactions specifically
                $transferCheckQuery = "SELECT COUNT(*) as count FROM transactions WHERE (project_id = :project_id OR transfer_to_project_id = :project_id OR transfer_from_project_id = :project_id) AND is_transfer = 1";
                $transferCheckStmt = $db->prepare($transferCheckQuery);
                $transferCheckStmt->bindParam(':project_id', $editProject['id']);
                $transferCheckStmt->execute();
                $transferResult = $transferCheckStmt->fetch(PDO::FETCH_ASSOC);
                $hasTransfers = $transferResult['count'] > 0;
            } catch (Exception $e) {
                // Ignore error, assume no transactions
            }
        ?>
        <?php if ($hasTransactions): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>ไม่สามารถแก้ไขได้:</strong> โครงการนี้มีรายการธุรกรรมแล้ว ไม่อนุญาตให้แก้ไขข้อมูลโครงการ
        </div>
        <?php elseif ($hasTransfers): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>คำเตือน:</strong> โครงการนี้มีประวัตการโอนงบประมาณ การแก้ไขหมวดหมู่งบประมาณจะอัปเดตเฉพาะงบประมาณเริ่มต้น 
            ยอดคงเหลือจริงจะคำนวณจากรายการธุรกรรมทั้งหมด รวมถึงการโอนเข้า-ออก
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <form method="POST" id="projectForm">
            <input type="hidden" name="action" value="<?= $editProject ? 'update' : 'create' ?>">
            <?php if ($editProject): ?>
            <input type="hidden" name="project_id" value="<?= $editProject['id'] ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="fiscal_year_id" class="form-label"><?= $yearLabel ?> *</label>
                    <select class="form-select" id="fiscal_year_id" name="fiscal_year_id" required 
                            <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>>
                        <option value="">เลือก<?= $yearLabel ?></option>
                        <?php foreach ($fiscalYears as $fy): ?>
                        <option value="<?= $fy['id'] ?>" 
                            <?= ($editProject && $editProject['fiscal_year_id'] == $fy['id']) ? 'selected' : 
                                (!$editProject && $activeFiscalYear && $activeFiscalYear['id'] == $fy['id'] ? 'selected' : '') ?>>
                            <?= $fy['name'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">ชื่อโครงการ *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?= htmlspecialchars($editProject['name'] ?? '') ?>" required maxlength="255"
                           <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="work_group" class="form-label">กลุ่มงาน *</label>
                    <select class="form-select" id="work_group" name="work_group" required
                            <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>>
                        <option value="">เลือกกลุ่มงาน</option>
                        <?php foreach ($workGroups as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($editProject['work_group'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="responsible_person" class="form-label">ผู้รับผิดชอบ *</label>
                    <input type="text" class="form-control" id="responsible_person" name="responsible_person" 
                           value="<?= htmlspecialchars($editProject['responsible_person'] ?? '') ?>" required maxlength="255"
                           <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">รายละเอียด</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="1000"
                          <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>><?= htmlspecialchars($editProject['description'] ?? '') ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="total_budget" class="form-label">งบประมาณคงเหลือ (บาท) *</label>
                    <input type="number" class="form-control bg-light" id="total_budget" name="total_budget" 
                           value="<?= $editProject['remaining_budget'] ?? $editProject['total_budget'] ?? '' ?>" min="0" step="0.01" required>
                    <small class="text-muted">แสดงยอดคงเหลือปัจจุบัน (รวมการโอนเข้า-ออก)</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="start_date" class="form-label">วันที่เริ่มต้น *</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?= $editProject['start_date'] ?? '' ?>" required
                           <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="end_date" class="form-label">วันที่สิ้นสุด *</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?= $editProject['end_date'] ?? '' ?>" required
                           <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label">สถานะ</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" <?= ($editProject['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>ดำเนินการ</option>
                    <option value="completed" <?= ($editProject['status'] ?? '') === 'completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                    <option value="suspended" <?= ($editProject['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>ระงับ</option>
                </select>
            </div>
            
            <!-- Budget Categories -->
            <div class="mb-3">
                <label class="form-label">หมวดหมู่งบประมาณ</label>
                <?php if ($editProject && $hasTransfers): ?>
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>หมายเหตุ:</strong> ยอดงบประมาณที่แสดงเป็นยอดคงเหลือปัจจุบัน (รวมการโอนเข้า-ออก)
                </div>
                <?php endif; ?>
                <div id="budgetCategories">
                    <?php if ($editProject && !empty($editProject['categories'])): ?>
                        <?php foreach ($editProject['categories'] as $index => $category): ?>
                        <div class="row mb-2 budget-category-row">
                            <div class="col-md-6">
                                <select class="form-select" 
                                        name="budget_categories[<?= $index ?>][category]" 
                                        required
                                        <?= $hasTransactions ? 'disabled' : '' ?>>
                                    <option value="">เลือกหมวดหมู่งบประมาณ</option>
                                    <?php foreach ($budgetCategories as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($category['category'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="number" class="form-control category-budget" 
                                       name="budget_categories[<?= $index ?>][budget]" 
                                       placeholder="งบประมาณ" 
                                       value="<?= $category['amount'] ?? 0 ?>" 
                                       min="0" step="0.01" required
                                       <?= $hasTransactions ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-category"
                                        <?= $hasTransactions ? 'disabled' : '' ?>>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="row mb-2 budget-category-row">
                        <div class="col-md-6">
                            <select class="form-select" 
                                    name="budget_categories[0][category]" 
                                    required>
                                <option value="">เลือกหมวดหมู่งบประมาณ</option>
                                <?php foreach ($budgetCategories as $key => $label): ?>
                                <option value="<?= $key ?>">
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="number" class="form-control category-budget" 
                                   name="budget_categories[0][budget]" 
                                   placeholder="งบประมาณ" 
                                   min="0" step="0.01" required>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm remove-category">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" id="addCategory"
                        <?= ($editProject && $hasTransactions) ? 'disabled' : '' ?>>
                    <i class="bi bi-plus me-1"></i>
                    เพิ่มหมวดหมู่
                </button>
                <div class="mt-2">
                    <small class="text-muted">งบประมาณรวม: <span id="totalCategoryBudget">0</span> บาท</small>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check me-1"></i>
                    <?= $editProject ? 'อัปเดต' : 'สร้าง' ?>โครงการ
                </button>
                <?php if ($editProject): ?>
                <a href="?page=projects" class="btn btn-secondary">
                    <i class="bi bi-x me-1"></i>
                    ยกเลิก
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Projects List -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
             <h5 class="mb-0">
                  <i class="bi bi-list me-2"></i>
                  รายการโครงการ (<?= $totalProjects ?> โครงการ)
                  <?php 
                  $hasActiveFilters = $filterWorkGroup !== 'all' || $filterStatus !== 'all' || $filterFiscalYear !== 'all' || !empty($searchTerm);
                  if ($hasActiveFilters): ?>
                  <span class="badge bg-primary ms-2">มีตัวกรองใช้งาน</span>
                  <?php endif; ?>
                  <?php if ($totalProjects > 0): ?>
                  <small class="text-muted ms-2">(แสดง <?= $startItem ?>-<?= $endItem ?> จาก <?= $totalProjects ?> รายการ)</small>
                  <?php endif; ?>
              </h5>
             <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                 <i class="bi bi-funnel me-1"></i>
                 ตัวกรอง
                 <?php if ($hasActiveFilters): ?>
                 <span class="badge bg-primary ms-1"><?= 
                     ($filterWorkGroup !== 'all' ? 1 : 0) + 
                     ($filterStatus !== 'all' ? 1 : 0) + 
                     ($filterFiscalYear !== 'all' ? 1 : 0) +
                     (!empty($searchTerm) ? 1 : 0) 
                 ?></span>
                 <?php endif; ?>
             </button>
         </div>
        
        <!-- Filter Section -->
        <div class="collapse mt-3" id="filterCollapse">
            <form method="GET" class="row g-3" id="filterForm">
                <input type="hidden" name="page" value="projects">
                
                <div class="col-md-3">
                    <label for="search_term" class="form-label">ค้นหา</label>
                    <input type="text" class="form-control" id="search_term" name="search_term" 
                           placeholder="ชื่อโครงการ, รายละเอียด, ผู้รับผิดชอบ" 
                           value="<?= htmlspecialchars($searchTerm) ?>">
                </div>

                <div class="col-md-2">
                    <label for="filter_fiscal_year" class="form-label"><?= $yearLabel ?></label>
                    <select class="form-select" id="filter_fiscal_year" name="filter_fiscal_year">
                        <option value="all" <?= $filterFiscalYear === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                        <?php foreach ($fiscalYears as $fy): ?>
                        <option value="<?= $fy['id'] ?>" <?= $filterFiscalYear == $fy['id'] ? 'selected' : '' ?>>
                            <?= $fy['name'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filter_work_group" class="form-label">กลุ่มงาน</label>
                    <select class="form-select" id="filter_work_group" name="filter_work_group">
                        <option value="all" <?= $filterWorkGroup === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                        <?php foreach ($workGroups as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filterWorkGroup === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="filter_status" class="form-label">สถานะ</label>
                    <select class="form-select" id="filter_status" name="filter_status">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>ดำเนินการ</option>
                        <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                        <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>ระงับ</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                     <div class="btn-group w-100">
                         <button type="submit" class="btn btn-primary">
                             <i class="bi bi-search me-1"></i>
                             กรอง
                         </button>
                         <a href="?page=projects" class="btn btn-outline-secondary">
                             <i class="bi bi-x-circle me-1"></i>
                             ล้าง
                         </a>
                     </div>
                 </div>
                 
                 <?php if ($hasActiveFilters): ?>
                 <div class="col-12">
                     <div class="alert alert-info py-2 mb-0">
                         <small>
                             <i class="bi bi-info-circle me-1"></i>
                             ตัวกรองที่ใช้งาน:
                             <?php if (!empty($searchTerm)): ?>
                             <span class="badge bg-secondary ms-1">ค้นหา: "<?= htmlspecialchars($searchTerm) ?>"</span>
                             <?php endif; ?>
                             <?php if ($filterWorkGroup !== 'all'): ?>
                             <span class="badge bg-secondary ms-1">กลุ่มงาน: <?= $workGroups[$filterWorkGroup] ?></span>
                             <?php endif; ?>
                             <?php if ($filterStatus !== 'all'): ?>
                             <span class="badge bg-secondary ms-1">สถานะ: <?= $filterStatus === 'active' ? 'ดำเนินการ' : ($filterStatus === 'completed' ? 'เสร็จสิ้น' : 'ระงับ') ?></span>
                             <?php endif; ?>
                             <?php if ($filterFiscalYear !== 'all'): 
                                $fyName = '';
                                foreach($fiscalYears as $fy) { if($fy['id'] == $filterFiscalYear) { $fyName = $fy['name']; break; } }
                             ?>
                             <span class="badge bg-secondary ms-1"><?= $yearLabel ?>: <?= $fyName ?></span>
                             <?php endif; ?>
                         </small>
                     </div>
                 </div>
                 <?php endif; ?>
             </form>
         </div>
         
         <!-- Pagination Controls -->
         <?php if ($totalProjects > 0): ?>
         <div class="card-footer">
             <div class="row align-items-center">
                 <div class="col-md-6">
                     <div class="d-flex align-items-center">
                         <label class="form-label me-2 mb-0">แสดงต่อหน้า:</label>
                         <select class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                             <?php foreach ($validPerPageOptions as $option): ?>
                             <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>>
                                 <?= $option ?> รายการ
                             </option>
                             <?php endforeach; ?>
                         </select>
                     </div>
                 </div>
                 <div class="col-md-6">
                     <?php if ($totalPages > 1): ?>
                     <nav aria-label="Project pagination">
                         <ul class="pagination pagination-sm justify-content-end mb-0">
                             <!-- First Page -->
                             <?php if ($page > 1): ?>
                             <li class="page-item">
                                 <a class="page-link" href="<?= buildPaginationUrl(1) ?>">
                                     <i class="bi bi-chevron-double-left"></i>
                                 </a>
                             </li>
                             <?php endif; ?>
                             
                             <!-- Previous Page -->
                             <?php if ($page > 1): ?>
                             <li class="page-item">
                                 <a class="page-link" href="<?= buildPaginationUrl($page - 1) ?>">
                                     <i class="bi bi-chevron-left"></i>
                                 </a>
                             </li>
                             <?php endif; ?>
                             
                             <!-- Page Numbers -->
                             <?php
                             $startPage = max(1, $page - 2);
                             $endPage = min($totalPages, $page + 2);
                             
                             // Adjust range if we're near the beginning or end
                             if ($endPage - $startPage < 4) {
                                 if ($startPage === 1) {
                                     $endPage = min($totalPages, $startPage + 4);
                                 } else {
                                     $startPage = max(1, $endPage - 4);
                                 }
                             }
                             
                             for ($i = $startPage; $i <= $endPage; $i++): ?>
                             <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                 <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                             </li>
                             <?php endfor; ?>
                             
                             <!-- Next Page -->
                             <?php if ($page < $totalPages): ?>
                             <li class="page-item">
                                 <a class="page-link" href="<?= buildPaginationUrl($page + 1) ?>">
                                     <i class="bi bi-chevron-right"></i>
                                 </a>
                             </li>
                             <?php endif; ?>
                             
                             <!-- Last Page -->
                             <?php if ($page < $totalPages): ?>
                             <li class="page-item">
                                 <a class="page-link" href="<?= buildPaginationUrl($totalPages) ?>">
                                     <i class="bi bi-chevron-double-right"></i>
                                 </a>
                             </li>
                             <?php endif; ?>
                         </ul>
                     </nav>
                     <?php endif; ?>
                 </div>
             </div>
         </div>
         <?php endif; ?>
     </div>
    <div class="card-body">
        <?php if (empty($projects)): ?>
        <div class="text-center py-4">
            <i class="bi bi-folder-x fs-1 text-muted"></i>
            <p class="text-muted mt-2">ยังไม่มีโครงการ</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ชื่อโครงการ</th>
                        <th>กลุ่มงาน</th>
                        <th>ผู้รับผิดชอบ</th>
                        <th>งบประมาณ</th>
                        <th>ใช้แล้ว</th>
                        <th>คงเหลือ</th>
                        <th>สถานะ</th>
                        <th>ระยะเวลา</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): 
                        $usagePercent = $project['total_budget'] > 0 ? ($project['used_budget'] / $project['total_budget']) * 100 : 0;
                        $statusClass = $project['status'] === 'active' ? 'success' : ($project['status'] === 'completed' ? 'primary' : 'secondary');
                        $workGroupClass = 'work-group-' . $project['work_group'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($project['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(substr($project['description'], 0, 50), ENT_QUOTES, 'UTF-8') ?><?= strlen($project['description']) > 50 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $workGroupClass ?> text-white">
                                <?= $workGroups[$project['work_group']] ?? htmlspecialchars($project['work_group'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($project['responsible_person'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>฿<?= number_format($project['total_budget'], 2) ?></td>
                        <td>
                            <span class="text-danger">฿<?= number_format($project['used_budget'], 2) ?></span>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar bg-<?= $usagePercent > 80 ? 'danger' : ($usagePercent > 60 ? 'warning' : 'success') ?>" 
                                     style="width: <?= min($usagePercent, 100) ?>%"></div>
                            </div>
                            <small class="text-muted"><?= number_format($usagePercent, 1) ?>%</small>
                        </td>
                        <td>
                            <span class="text-success">฿<?= number_format($project['remaining_budget'], 2) ?></span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusClass ?>">
                                <?= $project['status'] === 'active' ? 'ดำเนินการ' : ($project['status'] === 'completed' ? 'เสร็จสิ้น' : 'ระงับ') ?>
                            </span>
                        </td>
                        <td>
                            <small>
                                <?= date('d/m/Y', strtotime($project['start_date'])) ?><br>
                                ถึง <?= date('d/m/Y', strtotime($project['end_date'])) ?>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=projects&edit=<?= $project['id'] ?>" class="btn btn-outline-primary" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteProject(<?= $project['id'] ?>, '<?= htmlspecialchars($project['name']) ?>')" 
                                        title="ลบ">
                                    <i class="bi bi-trash"></i>
                                </button>
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
                <p>คุณต้องการลบโครงการ "<span id="deleteProjectName"></span>" หรือไม่?</p>
                <p class="text-danger"><small>การลบจะไม่สามารถกู้คืนได้</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="project_id" id="deleteProjectId">
                    <button type="submit" class="btn btn-danger">ลบโครงการ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Budget category management
let categoryIndex = <?= $editProject && !empty($editProject['categories']) ? count($editProject['categories']) : 1 ?>;

// Function to update total budget calculation
function updateCategoryBudgetTotal() {
    const budgetInputs = document.querySelectorAll('.category-budget');
    let total = 0;
    budgetInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        total += value;
    });
    
    // Update the display
    document.getElementById('totalCategoryBudget').textContent = total.toLocaleString();
    
    // Only update the total budget field if not in edit mode
    // In edit mode, the total budget shows remaining balance which should not be overwritten
    <?php if (!$editProject): ?>
    document.getElementById('total_budget').value = total;
    <?php endif; ?>
    
    // Update styling
    const totalElement = document.getElementById('totalCategoryBudget');
    totalElement.className = 'text-success fw-bold';
}

// Function to attach event listeners to budget inputs
function attachBudgetListeners() {
    const budgetInputs = document.querySelectorAll('.category-budget');
    budgetInputs.forEach(input => {
        // Remove existing listeners to prevent duplicates
        input.removeEventListener('input', updateCategoryBudgetTotal);
        // Add new listener
        input.addEventListener('input', updateCategoryBudgetTotal);
    });
}

// Budget categories data for JavaScript
const budgetCategoriesData = <?= json_encode($budgetCategories) ?>;

// Add category function
document.getElementById('addCategory').addEventListener('click', function() {
    const container = document.getElementById('budgetCategories');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 budget-category-row';
    
    // Build options for select
    let optionsHtml = '<option value="">เลือกหมวดหมู่งบประมาณ</option>';
    for (const [key, label] of Object.entries(budgetCategoriesData)) {
        optionsHtml += `<option value="${key}">${label}</option>`;
    }
    
    newRow.innerHTML = `
        <div class="col-md-6">
            <select class="form-select" 
                    name="budget_categories[${categoryIndex}][category]" 
                    required>
                ${optionsHtml}
            </select>
        </div>
        <div class="col-md-5">
            <input type="number" class="form-control category-budget" 
                   name="budget_categories[${categoryIndex}][budget]" 
                   placeholder="งบประมาณ" 
                   min="0" step="0.01" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm remove-category">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    categoryIndex++;
    
    // Attach event listeners to the new budget input
    attachBudgetListeners();
    updateCategoryBudgetTotal();
});

// Remove category using event delegation
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-category')) {
        const row = e.target.closest('.budget-category-row');
        if (document.querySelectorAll('.budget-category-row').length > 1) {
            row.remove();
            updateCategoryBudgetTotal();
        } else {
            alert('ต้องมีหมวดหมู่อย่างน้อย 1 หมวดหมู่');
        }
     }
     
     // Pagination functions
     window.changePerPage = function(perPage) {
         const url = new URL(window.location);
         url.searchParams.set('per_page', perPage);
         url.searchParams.set('page_num', '1'); // Reset to first page
         window.location.href = url.toString();
     };
 });

// Delete project function
function deleteProject(id, name) {
    if (confirm('คุณต้องการลบโครงการ "' + name + '" หรือไม่?\n\nการลบโครงการจะไม่สามารถกู้คืนได้')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="project_id" value="${id}">
        `;
        document.body.appendChild(form);
        
        // Show loading overlay before form submission
        function showOverlay() {
         if (typeof window.showLoadingOverlay === 'function') {
             window.showLoadingOverlay();
         } else {
             // Fallback: directly show the overlay
             const loadingOverlay = document.getElementById('loadingOverlay');
             if (loadingOverlay) {
                 loadingOverlay.classList.add('show');
                 // Additional fallback with inline styles
                 loadingOverlay.style.display = 'flex';
                 loadingOverlay.style.opacity = '1';
             }
         }
     }
        
        showOverlay();
        
        // Set a timeout to hide overlay if deletion takes too long (fallback)
        setTimeout(function() {
            if (typeof window.hideLoadingOverlay === 'function') {
                window.hideLoadingOverlay();
            } else {
                const loadingOverlay = document.getElementById('loadingOverlay');
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('show');
                    // Additional fallback with inline styles
                    loadingOverlay.style.display = 'none';
                    loadingOverlay.style.opacity = '0';
                }
            }
        }, 10000); // Hide after 10 seconds as fallback
        
        form.submit();
    }
}

// Form validation
document.getElementById('projectForm').addEventListener('submit', function(e) {
    // Show loading overlay on form submission
    function showOverlay() {
        if (typeof window.showLoadingOverlay === 'function') {
            window.showLoadingOverlay();
        } else {
            // Fallback: directly show the overlay
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
                loadingOverlay.style.opacity = '1';
            }
        }
    }
    
    // Show overlay immediately
    showOverlay();
    
    // Set a timeout to hide overlay if form submission takes too long (fallback)
    setTimeout(function() {
        if (typeof window.hideLoadingOverlay === 'function') {
            window.hideLoadingOverlay();
        } else {
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('show');
                // Additional fallback with inline styles
                loadingOverlay.style.display = 'none';
                loadingOverlay.style.opacity = '0';
            }
        }
    }, 10000); // Hide after 10 seconds as fallback
    // Basic date validation
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    
    if (endDate < startDate) {
        e.preventDefault();
        // Hide loading overlay if validation fails
        if (typeof window.hideLoadingOverlay === 'function') {
            window.hideLoadingOverlay();
        } else {
            // Fallback: directly hide the overlay
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('show');
                // Additional fallback with inline styles
                loadingOverlay.style.display = 'none';
                loadingOverlay.style.opacity = '0';
            }
        }
        alert('วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มต้น');
        return false;
    }
    
    // Validate budget categories
    const budgetInputs = document.querySelectorAll('.category-budget');
    let hasValidCategory = false;
    
    budgetInputs.forEach((input) => {
        const value = parseFloat(input.value) || 0;
        if (value > 0) {
            hasValidCategory = true;
        }
    });
    
    if (!hasValidCategory) {
        e.preventDefault();
        // Hide loading overlay if validation fails
        if (typeof window.hideLoadingOverlay === 'function') {
            window.hideLoadingOverlay();
        } else {
            // Fallback: directly hide the overlay
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('show');
                // Additional fallback with inline styles
                loadingOverlay.style.display = 'none';
                loadingOverlay.style.opacity = '0';
            }
        }
        alert('กรุณาเพิ่มหมวดหมู่งบประมาณอย่างน้อย 1 หมวดหมู่ และระบุจำนวนเงินที่ถูกต้อง');
        return false;
    }
    
    // If validation passes, allow form to submit normally
    return true;
});

// Character counter function
function addCharacterCounter(input, maxLength, fieldName) {
    const counter = document.createElement('small');
    counter.className = 'form-text text-muted';
    counter.style.float = 'right';
    input.parentNode.appendChild(counter);
    
    function updateCounter() {
        const remaining = maxLength - input.value.length;
        counter.textContent = `เหลือ ${remaining} ตัวอักษร`;
        counter.className = remaining < 50 ? 'form-text text-warning' : 'form-text text-muted';
        
        if (remaining < 0) {
            counter.className = 'form-text text-danger';
            counter.textContent = `เกินขีดจำกัด ${Math.abs(remaining)} ตัวอักษร`;
        }
    }
    
    input.addEventListener('input', updateCounter);
    updateCounter();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Always ensure loading overlay is hidden on page load
    // Use a slight delay to ensure the overlay functions are available
    setTimeout(function() {
        if (typeof window.hideLoadingOverlay === 'function') {
                 window.hideLoadingOverlay();
             } else {
                 // Fallback: directly hide the overlay
                 const loadingOverlay = document.getElementById('loadingOverlay');
                 if (loadingOverlay) {
                     loadingOverlay.classList.remove('show');
                     // Additional fallback with inline styles
                     loadingOverlay.style.display = 'none';
                     loadingOverlay.style.opacity = '0';
                 }
             }
    }, 100);
    
    // Force hide overlay immediately if we have success/error messages (after redirect)
    const hasMessage = <?php echo $hasSuccessMessage ? 'true' : 'false'; ?>;
    if (hasMessage) {
        const loadingOverlay = document.getElementById('loadingOverlay');
        const mainContent = document.querySelector('.main-content');
        
        if (loadingOverlay) {
            loadingOverlay.classList.remove('show');
            loadingOverlay.style.display = 'none';
            loadingOverlay.style.opacity = '0';
            loadingOverlay.style.visibility = 'hidden';
        }
        
        // Ensure main content is visible
        if (mainContent) {
            mainContent.classList.remove('loading');
            mainContent.style.opacity = '1';
            mainContent.style.visibility = 'visible';
        }
        
        // Also ensure content div is visible
        const contentDiv = document.querySelector('.content');
        if (contentDiv) {
            contentDiv.style.opacity = '1';
            contentDiv.style.visibility = 'visible';
            contentDiv.style.display = 'block';
        }
        
        if (typeof window.hideLoadingOverlay === 'function') {
            window.hideLoadingOverlay();
        }
    }
    
    // Additional safety check - force hide overlay after 2 seconds if still visible
    setTimeout(function() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        const mainContent = document.querySelector('.main-content');
        
        if (loadingOverlay && (loadingOverlay.classList.contains('show') || loadingOverlay.style.display === 'flex')) {
            if (typeof window.hideLoadingOverlay === 'function') {
                window.hideLoadingOverlay();
            } else {
                loadingOverlay.classList.remove('show');
                // Additional fallback with inline styles
                loadingOverlay.style.display = 'none';
                loadingOverlay.style.opacity = '0';
            }
        }
        
        // Ensure main content is always visible
        if (mainContent) {
            mainContent.classList.remove('loading');
            mainContent.style.opacity = '1';
            mainContent.style.visibility = 'visible';
        }
    }, 2000);
    
    // Attach event listeners to existing budget inputs
    attachBudgetListeners();
    
    // Calculate initial total for both create and edit modes
    // This ensures the total budget display is updated correctly
    updateCategoryBudgetTotal();
    
    // Add character counters
    const nameInput = document.getElementById('name');
    const descInput = document.getElementById('description');
    
    if (nameInput) {
        addCharacterCounter(nameInput, 255, 'ชื่อโครงการ');
    }
    
    if (descInput) {
        addCharacterCounter(descInput, 1000, 'คำอธิบาย');
    }
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-info)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Enhanced filter functionality
    const filterForm = document.getElementById('filterForm');
    const searchInput = document.querySelector('input[name="search_term"]');
    const workGroupSelect = document.querySelector('select[name="filter_work_group"]');
    const statusSelect = document.querySelector('select[name="filter_status"]');
    
    // Manual filter only - no auto-submit
    
    // Show filter count in real-time
    function updateFilterCount() {
        const activeFilters = [];
        if (searchInput && searchInput.value.trim()) activeFilters.push('search');
        if (workGroupSelect && workGroupSelect.value !== 'all') activeFilters.push('workgroup');
        if (statusSelect && statusSelect.value !== 'all') activeFilters.push('status');
        
        const filterButton = document.querySelector('[data-bs-target="#filterCollapse"]');
        if (filterButton) {
            const existingBadge = filterButton.querySelector('.badge');
            
            if (activeFilters.length > 0) {
                if (!existingBadge) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-primary ms-1';
                    badge.textContent = activeFilters.length;
                    filterButton.appendChild(badge);
                } else {
                    existingBadge.textContent = activeFilters.length;
                }
            } else if (existingBadge) {
                existingBadge.remove();
            }
        }
     }
     
     // Pagination functions
     window.changePerPage = function(perPage) {
         const url = new URL(window.location);
         url.searchParams.set('per_page', perPage);
         url.searchParams.set('page_num', '1'); // Reset to first page
         window.location.href = url.toString();
     };
 });
 </script>

<?php
// Flush output buffer
// ob_end_flush(); // Commented out to fix blank content issue
?>