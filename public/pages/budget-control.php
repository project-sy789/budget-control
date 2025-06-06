<?php
/**
 * Budget Control Page - Transaction Management
 */

// Check if this page is being accessed directly (not included by index.php)
if (!isset($projectService)) {
    session_start();
    
    // Include required files
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../src/Auth/SessionManager.php';
    require_once __DIR__ . '/../../src/Auth/AuthService.php';
    require_once __DIR__ . '/../../src/Services/ProjectService.php';
    require_once __DIR__ . '/../../src/Services/TransactionService.php';
    
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Initialize services
    $sessionManager = new SessionManager();
    $authService = new AuthService();
    $projectService = new ProjectService();
    $transactionService = new TransactionService();
    
    // Services create their own database connections in constructors
    // No need to set connections manually
    
    // Check if user is logged in
    $isLoggedIn = $sessionManager->isLoggedIn();
    $currentUser = null;
    
    if ($isLoggedIn) {
        $userId = $sessionManager->getCurrentUserId();
        $currentUser = $authService->getUserById($userId);
    } else {
        // Redirect to login if not logged in
        header('Location: ../index.php?page=login');
        exit;
    }
}

// Handle AJAX request for getting budget categories
if (isset($_GET['get_categories']) && isset($_GET['project_id'])) {
    header('Content-Type: application/json');
    $projectId = intval($_GET['project_id']);
    $categories = $projectService->getProjectBudgetCategories($projectId);
    echo json_encode($categories);
    exit;
}

$error = '';
$success = '';
$editTransaction = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $projectId = intval($_POST['project_id'] ?? 0);
        $categoryId = intval($_POST['category_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $transactionDate = $_POST['transaction_date'] ?? '';
        $reference = trim($_POST['reference'] ?? '');
        
        // Enhanced validation with debugging
        if ($projectId <= 0) {
            $error = 'กรุณาเลือกโครงการ';
        } elseif ($categoryId <= 0) {
            $error = 'กรุณาเลือกหมวดหมู่งบประมาณ';
        } elseif (empty($type)) {
            $error = 'กรุณาเลือกประเภทรายการ';
        } elseif ($amount <= 0) {
            $error = 'จำนวนเงินต้องมากกว่า 0';
        } elseif (empty($description)) {
            $error = 'กรุณากรอกรายละเอียด';
        } else {
            $transactionData = [
                'project_id' => $projectId,
                'category_type_id' => $categoryId,
                'amount' => $amount,
                'transaction_type' => $type,
                'description' => $description,
                'transaction_date' => $transactionDate,
                'reference_number' => $reference
            ];
            
            if ($action === 'create') {
                $result = $transactionService->createTransaction($transactionData, $currentUser['id']);
                if ($result && isset($result['success']) && $result['success']) {
                    $success = 'บันทึกรายการเรียบร้อยแล้ว';
                } else {
                    $error = isset($result['message']) ? $result['message'] : 'เกิดข้อผิดพลาดในการบันทึกรายการ';
                }
            } else {
                $transactionId = intval($_POST['transaction_id']);
                $result = $transactionService->updateTransaction($transactionId, $transactionData);
                if ($result && isset($result['success']) && $result['success']) {
                    $success = 'อัปเดตรายการเรียบร้อยแล้ว';
                } else {
                    $error = isset($result['message']) ? $result['message'] : 'เกิดข้อผิดพลาดในการอัปเดตรายการ';
                }
            }
        }
    } elseif ($action === 'delete') {
        $transactionId = intval($_POST['transaction_id']);
        $result = $transactionService->deleteTransaction($transactionId);
        if ($result && isset($result['success']) && $result['success']) {
            $success = 'ลบรายการเรียบร้อยแล้ว';
        } else {
            $error = isset($result['message']) ? $result['message'] : 'เกิดข้อผิดพลาดในการลบรายการ';
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editTransaction = $transactionService->getTransactionById($editId);
    
    // The category_type_id is already available in the transaction data
    if ($editTransaction && isset($editTransaction['category_type_id'])) {
        $editTransaction['category_id'] = $editTransaction['category_type_id'];
    }
}

// Get filter parameters
$currentPage = intval($_GET['page_num'] ?? 1);
$projectFilter = intval($_GET['project_filter'] ?? 0);
$typeFilter = $_GET['type_filter'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$perPage = 20;

// Build filters
$filters = [];
if ($projectFilter > 0) {
    $filters['project_id'] = $projectFilter;
}
if (!empty($typeFilter)) {
    $filters['type'] = $typeFilter;
}
if (!empty($dateFrom)) {
    $filters['date_from'] = $dateFrom;
}
if (!empty($dateTo)) {
    $filters['date_to'] = $dateTo;
}

// Get transactions with pagination
$transactions = $transactionService->getAllTransactions($projectFilter > 0 ? $projectFilter : null, null, $perPage, ($currentPage - 1) * $perPage, $filters);
$totalTransactions = $transactionService->getTransactionsCount($projectFilter > 0 ? $projectFilter : null, null, $filters);
$totalPages = ceil($totalTransactions / $perPage);

// Get projects for dropdown
$projects = $projectService->getAllProjects();

// Define work groups
$workGroups = [
    'academic' => 'งานวิชาการ',
    'budget' => 'งานงบประมาณ',
    'hr' => 'งานบุคลากร',
    'general' => 'งานทั่วไป',
    'other' => 'อื่น ๆ'
];

// Get dynamic budget categories from CategoryService
require_once __DIR__ . '/../../src/Services/CategoryService.php';
$categoryService = new CategoryService($db);
$budgetCategoriesData = $categoryService->getAllActiveCategories();
$budgetCategories = [];
foreach ($budgetCategoriesData as $category) {
    $budgetCategories[$category['category_key']] = $category['category_name'];
}

// Get all project categories for JavaScript (same approach as budget-transfer.php)
$projectCategories = [];
foreach ($projects as $project) {
    $categories = $projectService->getProjectBudgetCategories($project['id']);
    $projectCategories[$project['id']] = $categories;
}

// Check if this is a direct access (not included by index.php)
$isDirectAccess = !isset($page);
?>

<?php if ($isDirectAccess): ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ควบคุมงบประมาณ - Budget Control System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
                    <div class="container-fluid">
                        <a class="navbar-brand" href="../index.php">
                            <i class="bi bi-calculator me-2"></i>
                            Budget Control System
                        </a>
                        <div class="navbar-nav ms-auto">
                            <span class="navbar-text me-3">
                                <i class="bi bi-person-circle me-1"></i>
                                <?= htmlspecialchars($currentUser['display_name'] ?? 'User') ?>
                            </span>
                            <a href="../index.php?page=logout" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right me-1"></i>
                                ออกจากระบบ
                            </a>
                        </div>
                    </div>
                </nav>
                <div class="container">
<?php endif; ?>

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

<!-- Transaction Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-<?= $editTransaction ? 'pencil' : 'plus' ?>-circle me-2"></i>
            <?= $editTransaction ? 'แก้ไขรายการ' : 'เพิ่มรายการใหม่' ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="transactionForm">
            <input type="hidden" name="action" value="<?= $editTransaction ? 'update' : 'create' ?>">
            <?php if ($editTransaction): ?>
            <input type="hidden" name="transaction_id" value="<?= $editTransaction['id'] ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="work_group_filter" class="form-label">กลุ่มงาน</label>
                    <select class="form-select" id="work_group_filter" name="work_group_filter">
                        <option value="">เลือกกลุ่มงานก่อน</option>
                        <?php foreach ($workGroups as $key => $label): ?>
                        <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="project_id" class="form-label">โครงการ *</label>
                    <select class="form-select" id="project_id" name="project_id" required disabled>
                        <option value="">เลือกกลุ่มงานก่อน</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="category_id" class="form-label">หมวดหมู่งบประมาณ *</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">เลือกหมวดหมู่</option>
                        <?php if ($editTransaction): ?>
                        <option value="<?= $editTransaction['category_id'] ?>" selected>
                            <?= htmlspecialchars($editTransaction['category_name']) ?>
                        </option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text" id="categoryInfo"></div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="type" class="form-label">ประเภท *</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">เลือกประเภท</option>
                        <option value="income" <?= ($editTransaction['type'] ?? '') === 'income' ? 'selected' : '' ?>>
                            รายรับ
                        </option>
                        <option value="expense" <?= ($editTransaction['type'] ?? '') === 'expense' ? 'selected' : '' ?>>
                            รายจ่าย
                        </option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="amount" class="form-label">จำนวนเงิน (บาท) *</label>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           value="<?= $editTransaction['amount'] ?? '' ?>" min="0" step="0.01" required>
                    <div class="form-text" id="amountInfo"></div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="transaction_date" class="form-label">วันที่ *</label>
                    <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                           value="<?= $editTransaction['transaction_date'] ?? date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">รายละเอียด *</label>
                <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($editTransaction['description'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="reference" class="form-label">เลขที่อ้างอิง</label>
                <input type="text" class="form-control" id="reference" name="reference" 
                       value="<?= htmlspecialchars($editTransaction['reference_number'] ?? '') ?>" 
                       placeholder="เลขที่ใบเสร็จ, เลขที่เอกสาร">
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check me-1"></i>
                    <?= $editTransaction ? 'อัปเดต' : 'บันทึก' ?>รายการ
                </button>
                <?php if ($editTransaction): ?>
                <a href="?page=budget-control" class="btn btn-secondary">
                    <i class="bi bi-x me-1"></i>
                    ยกเลิก
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="bi bi-funnel me-2"></i>
            ตัวกรอง
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="budget-control">
            
            <div class="col-md-3">
                <label for="project_filter" class="form-label">โครงการ</label>
                <select class="form-select" id="project_filter" name="project_filter">
                    <option value="">ทุกโครงการ</option>
                    <?php foreach ($projects as $project): ?>
                    <option value="<?= $project['id'] ?>" <?= $projectFilter == $project['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($project['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="type_filter" class="form-label">ประเภท</label>
                <select class="form-select" id="type_filter" name="type_filter">
                    <option value="">ทุกประเภท</option>
                    <option value="income" <?= $typeFilter === 'income' ? 'selected' : '' ?>>รายรับ</option>
                    <option value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>รายจ่าย</option>
                    <option value="transfer" <?= $typeFilter === 'transfer' ? 'selected' : '' ?>>การโอน</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">วันที่เริ่มต้น</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $dateFrom ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">วันที่สิ้นสุด</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $dateTo ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>
                        ค้นหา
                    </button>
                    <a href="?page=budget-control" class="btn btn-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        รีเซ็ต
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Transactions List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            รายการทั้งหมด (<?= number_format($totalTransactions) ?> รายการ)
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" onclick="exportTransactions()">
                <i class="bi bi-download me-1"></i>
                ส่งออก Excel
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <p class="text-muted mt-2">ไม่พบรายการ</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>โครงการ</th>
                        <th>หมวดหมู่</th>
                        <th>รายละเอียด</th>
                        <th>ประเภท</th>
                        <th>จำนวน</th>
                        <th>เลขที่อ้างอิง</th>
                        <th>ผู้บันทึก</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($transaction['transaction_date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($transaction['project_name']) ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars($transaction['category_name']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($transaction['description']) ?>
                        </td>
                        <td>
                            <?php if ($transaction['type'] === 'income'): ?>
                                <span class="badge bg-success">รายรับ</span>
                            <?php elseif ($transaction['type'] === 'expense'): ?>
                                <span class="badge bg-danger">รายจ่าย</span>
                            <?php elseif ($transaction['type'] === 'transfer_in'): ?>
                                <span class="badge bg-primary">โอนเข้า</span>
                            <?php elseif ($transaction['type'] === 'transfer_out'): ?>
                                <span class="badge bg-warning">โอนออก</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">อื่น ๆ</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($transaction['type'] === 'income'): ?>
                                <span class="text-success fw-bold">+฿<?= number_format($transaction['amount'], 2) ?></span>
                            <?php elseif ($transaction['type'] === 'expense'): ?>
                                <span class="text-danger fw-bold">-฿<?= number_format($transaction['amount'], 2) ?></span>
                            <?php elseif ($transaction['type'] === 'transfer_in'): ?>
                                <span class="text-primary fw-bold">+฿<?= number_format($transaction['amount'], 2) ?></span>
                            <?php elseif ($transaction['type'] === 'transfer_out'): ?>
                                <span class="text-warning fw-bold">-฿<?= number_format($transaction['amount'], 2) ?></span>
                            <?php else: ?>
                                <span class="text-secondary fw-bold">฿<?= number_format($transaction['amount'], 2) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($transaction['reference_number']) ?>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($transaction['created_by_name']) ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($transaction['type'] !== 'transfer_in' && $transaction['type'] !== 'transfer_out'): ?>
                                <a href="?page=budget-control&edit=<?= $transaction['id'] ?>" 
                                   class="btn btn-outline-primary" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteTransaction(<?= $transaction['id'] ?>, '<?= htmlspecialchars($transaction['description']) ?>', '<?= $transaction['type'] ?>')" 
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
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($currentPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=budget-control&page_num=<?= $currentPage - 1 ?><?= $projectFilter ? '&project_filter=' . $projectFilter : '' ?><?= $typeFilter ? '&type_filter=' . $typeFilter : '' ?><?= $dateFrom ? '&date_from=' . $dateFrom : '' ?><?= $dateTo ? '&date_to=' . $dateTo : '' ?>">
                        ก่อนหน้า
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="?page=budget-control&page_num=<?= $i ?><?= $projectFilter ? '&project_filter=' . $projectFilter : '' ?><?= $typeFilter ? '&type_filter=' . $typeFilter : '' ?><?= $dateFrom ? '&date_from=' . $dateFrom : '' ?><?= $dateTo ? '&date_to=' . $dateTo : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=budget-control&page_num=<?= $currentPage + 1 ?><?= $projectFilter ? '&project_filter=' . $projectFilter : '' ?><?= $typeFilter ? '&type_filter=' . $typeFilter : '' ?><?= $dateFrom ? '&date_from=' . $dateFrom : '' ?><?= $dateTo ? '&date_to=' . $dateTo : '' ?>">
                        ถัดไป
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
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
                <p>คุณต้องการลบรายการ "<span id="deleteTransactionDesc"></span>" หรือไม่?</p>
                <div id="transferWarning" class="alert alert-warning" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>คำเตือน:</strong> การลบรายการโอนจะลบทั้งรายการโอนเข้าและโอนออกพร้อมกัน
                </div>
                <p class="text-danger"><small>การลบจะไม่สามารถกู้คืนได้</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="transaction_id" id="deleteTransactionId">
                    <button type="submit" class="btn btn-danger">ลบรายการ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Budget Categories mapping from PHP
const budgetCategories = <?= json_encode($budgetCategories) ?>;

// Project categories data from PHP (same approach as budget-transfer.php)
const projectCategories = <?= json_encode($projectCategories) ?>;

// Work group change event to filter projects
document.getElementById('work_group_filter').addEventListener('change', function() {
    const workGroup = this.value;
    const projectSelect = document.getElementById('project_id');
    const categorySelect = document.getElementById('category_id');
    
    // Clear project and category selections
    projectSelect.innerHTML = '<option value="">เลือกโครงการ</option>';
    categorySelect.innerHTML = '<option value="">เลือกหมวดหมู่</option>';
    
    if (workGroup) {
        // Enable project dropdown
        projectSelect.disabled = false;
        
        // Filter projects by work group
        const allProjects = <?= json_encode($projects) ?>;
        const filteredProjects = allProjects.filter(project => project.work_group === workGroup);
        
        // Populate project dropdown with filtered projects
        filteredProjects.forEach(project => {
            const option = document.createElement('option');
            option.value = project.id;
            option.textContent = project.name;
            projectSelect.appendChild(option);
        });
    } else {
        // Disable project dropdown if no work group selected
        projectSelect.disabled = true;
        projectSelect.innerHTML = '<option value="">เลือกกลุ่มงานก่อน</option>';
    }
});

// Load categories when project is selected
document.getElementById('project_id').addEventListener('change', function() {
    const projectId = this.value;
    const categorySelect = document.getElementById('category_id');
    
    // Clear existing options
    categorySelect.innerHTML = '<option value="">เลือกหมวดหมู่</option>';
    document.getElementById('categoryInfo').innerHTML = '';
    document.getElementById('amountInfo').innerHTML = '';
    
    if (projectId && projectCategories[projectId]) {
        // Use pre-loaded project categories data
        const categories = projectCategories[projectId];
        categories.forEach(category => {
            const option = document.createElement('option');
            // Use the category_type_id for transactions (not budget_categories.id)
            option.value = category.category_type_id || category.id;
            // Display only category name, remove budget amount from dropdown
            const categoryName = category.category_name || category.category;
            option.textContent = categoryName;
            // Store category key as data attribute for reference
            option.setAttribute('data-category-key', category.category);
            option.setAttribute('data-amount', category.amount || 0);
            categorySelect.appendChild(option);
        });
    }
});

// Update category info when category is selected
document.getElementById('category_id').addEventListener('change', function() {
    const selectedOption = this.selectedOptions[0];
    const categoryInfo = document.getElementById('categoryInfo');
    
    if (selectedOption && selectedOption.value) {
        const projectId = document.getElementById('project_id').value;
        const categoryKey = selectedOption.dataset.categoryKey;
        
        if (projectId && categoryKey) {
            // Show initial budget amount while fetching current balance
            const categoryAmount = parseFloat(selectedOption.dataset.amount || 0);
            categoryInfo.innerHTML = `<span class="text-info">งบประมาณเริ่มต้น: ฿${categoryAmount.toLocaleString()}</span>`;
            
            // Fetch current remaining balance
            fetch(`../src/Services/get_category_balance.php?project_id=${projectId}&category=${categoryKey}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const remainingBalance = parseFloat(data.remaining_balance || 0);
                        categoryInfo.innerHTML = `<span class="text-success">งบประมาณคงเหลือ: ฿${remainingBalance.toLocaleString()}</span>`;
                    } else {
                        categoryInfo.innerHTML = `<span class="text-info">งบประมาณเริ่มต้น: ฿${categoryAmount.toLocaleString()}</span>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching balance:', error);
                    categoryInfo.innerHTML = `<span class="text-info">งบประมาณเริ่มต้น: ฿${categoryAmount.toLocaleString()}</span>`;
                });
        }
    } else {
        categoryInfo.innerHTML = '';
    }
    updateBalancePreview();
});

// Update balance preview when amount changes
document.getElementById('amount').addEventListener('input', updateBalancePreview);
document.getElementById('type').addEventListener('change', updateBalancePreview);

// Function to update balance preview
function updateBalancePreview() {
    const categorySelect = document.getElementById('category_id');
    const amountInput = document.getElementById('amount');
    const typeSelect = document.getElementById('type');
    const amountInfo = document.getElementById('amountInfo');
    
    const selectedOption = categorySelect.selectedOptions[0];
    const amount = parseFloat(amountInput.value || 0);
    const type = typeSelect.value;
    
    if (selectedOption && selectedOption.value && amount > 0 && type) {
        let currentBalance = parseFloat(selectedOption.dataset.amount || 0);
        
        // Check if we're editing a transaction
        const isEditing = document.querySelector('input[name="action"][value="update"]') !== null;
        const originalAmount = parseFloat('<?= $editTransaction['amount'] ?? 0 ?>');
        const originalType = '<?= $editTransaction['type'] ?? '' ?>';
        
        // If editing, adjust the current balance to account for the original transaction
        if (isEditing && originalAmount > 0 && originalType) {
            if (originalType === 'income') {
                currentBalance = currentBalance - originalAmount; // Remove original income
            } else if (originalType === 'expense') {
                currentBalance = currentBalance + originalAmount; // Add back original expense
            }
        }
        
        let newBalance = currentBalance;
        let changeText = '';
        let changeClass = '';
        
        if (type === 'income') {
            newBalance = currentBalance + amount;
            changeText = `เพิ่มขึ้น +฿${amount.toLocaleString()}`;
            changeClass = 'text-success';
        } else if (type === 'expense') {
            newBalance = currentBalance - amount;
            changeText = `ลดลง -฿${amount.toLocaleString()}`;
            changeClass = 'text-danger';
            
            if (newBalance < 0) {
                amountInfo.innerHTML = `<span class="text-warning">คำเตือน: งบประมาณไม่เพียงพอ</span><br>
                                      <span class="${changeClass}">${changeText}</span><br>
                                      <span class="text-muted">ยอดคงเหลือหลังรายการ: ฿${newBalance.toLocaleString()}</span>`;
                return;
            }
        } else if (type === 'transfer') {
            changeText = `การโอน ฿${amount.toLocaleString()}`;
            changeClass = 'text-info';
        }
        
        amountInfo.innerHTML = `<span class="${changeClass}">${changeText}</span><br>
                              <span class="text-muted">ยอดคงเหลือหลังรายการ: ฿${newBalance.toLocaleString()}</span>`;
    } else {
        amountInfo.innerHTML = '';
    }
}

// Delete transaction function
function deleteTransaction(id, description, type) {
    document.getElementById('deleteTransactionId').value = id;
    document.getElementById('deleteTransactionDesc').textContent = description;
    
    // Show transfer warning if it's a transfer transaction
    const transferWarning = document.getElementById('transferWarning');
    if (type === 'transfer_in' || type === 'transfer_out') {
        transferWarning.style.display = 'block';
    } else {
        transferWarning.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Export transactions function
function exportTransactions() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'export.php?' + params.toString();
}

// Form validation
document.getElementById('transactionForm').addEventListener('submit', function(e) {
    const amount = parseFloat(document.getElementById('amount').value);
    const projectId = document.getElementById('project_id').value;
    const categoryId = document.getElementById('category_id').value;
    const type = document.getElementById('type').value;
    const description = document.getElementById('description').value.trim();
    
    // Validate required fields
    if (!projectId || projectId <= 0) {
        e.preventDefault();
        alert('กรุณาเลือกโครงการ');
        return false;
    }
    
    if (!categoryId || categoryId <= 0) {
        e.preventDefault();
        alert('กรุณาเลือกหมวดหมู่งบประมาณ');
        return false;
    }
    
    if (!type) {
        e.preventDefault();
        alert('กรุณาเลือกประเภทรายการ');
        return false;
    }
    
    if (amount <= 0) {
        e.preventDefault();
        alert('จำนวนเงินต้องมากกว่า 0');
        return false;
    }
    
    if (!description) {
        e.preventDefault();
        alert('กรุณากรอกรายละเอียด');
        return false;
    }
});

// Auto-load work group, project and categories if editing
<?php if ($editTransaction): ?>
document.addEventListener('DOMContentLoaded', function() {
    const editProjectId = '<?= $editTransaction['project_id'] ?? '' ?>';
    const editCategoryId = '<?= $editTransaction['category_id'] ?? '' ?>';
    
    if (editProjectId) {
        // Find the project to get its work group
        const allProjects = <?= json_encode($projects) ?>;
        const editProject = allProjects.find(project => project.id == editProjectId);
        
        if (editProject) {
            // Auto-select work group
            const workGroupSelect = document.getElementById('work_group_filter');
            workGroupSelect.value = editProject.work_group;
            
            // Trigger work group change to populate projects
            workGroupSelect.dispatchEvent(new Event('change'));
            
            // Wait a bit for projects to populate, then select the project
            setTimeout(function() {
                const projectSelect = document.getElementById('project_id');
                projectSelect.value = editProjectId;
                
                // Trigger project change to load categories
                projectSelect.dispatchEvent(new Event('change'));
                
                // Wait for categories to load, then select the correct one
                setTimeout(function() {
                    const categorySelect = document.getElementById('category_id');
                    if (editCategoryId && categorySelect.querySelector(`option[value="${editCategoryId}"]`)) {
                        categorySelect.value = editCategoryId;
                        // Trigger category change to show balance info
                        categorySelect.dispatchEvent(new Event('change'));
                    }
                }, 500);
            }, 300);
        }
    }
});
<?php endif; ?>
</script>

<?php if ($isDirectAccess): ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>