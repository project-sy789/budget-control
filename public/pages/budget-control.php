<?php
/**
 * Budget Control Page - Transaction Management
 */

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
        
        // Validation
        if ($projectId <= 0 || $categoryId <= 0 || empty($type) || $amount <= 0 || empty($description)) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } else {
            $transactionData = [
                'project_id' => $projectId,
                'category_id' => $categoryId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'transaction_date' => $transactionDate,
                'reference' => $reference,
                'created_by' => $currentUser['id']
            ];
            
            if ($action === 'create') {
                $result = $transactionService->createTransaction($transactionData);
                if ($result) {
                    $success = 'บันทึกรายการเรียบร้อยแล้ว';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกรายการ';
                }
            } else {
                $transactionId = intval($_POST['transaction_id']);
                $result = $transactionService->updateTransaction($transactionId, $transactionData);
                if ($result) {
                    $success = 'อัปเดตรายการเรียบร้อยแล้ว';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการอัปเดตรายการ';
                }
            }
        }
    } elseif ($action === 'delete') {
        $transactionId = intval($_POST['transaction_id']);
        $result = $transactionService->deleteTransaction($transactionId);
        if ($result) {
            $success = 'ลบรายการเรียบร้อยแล้ว';
        } else {
            $error = 'เกิดข้อผิดพลาดในการลบรายการ';
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editTransaction = $transactionService->getTransactionById($editId);
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
$transactions = $transactionService->getAllTransactions($projectFilter > 0 ? $projectFilter : null, null, $perPage, ($currentPage - 1) * $perPage);
$totalTransactions = $transactionService->getTransactionsCount($projectFilter > 0 ? $projectFilter : null, null);
$totalPages = ceil($totalTransactions / $perPage);

// Get projects for dropdown
$projects = $projectService->getAllProjects();

// Get dynamic budget categories from CategoryService
require_once '../src/Services/CategoryService.php';
$categoryService = new CategoryService($db);
$budgetCategoriesData = $categoryService->getAllActiveCategories();
$budgetCategories = [];
foreach ($budgetCategoriesData as $category) {
    $budgetCategories[$category['category_key']] = $category['category_name'];
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
                    <label for="project_id" class="form-label">โครงการ *</label>
                    <select class="form-select" id="project_id" name="project_id" required>
                        <option value="">เลือกโครงการ</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" 
                                <?= ($editTransaction['project_id'] ?? '') == $project['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($project['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                        <option value="transfer" <?= ($editTransaction['type'] ?? '') === 'transfer' ? 'selected' : '' ?>>
                            การโอน
                        </option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="amount" class="form-label">จำนวนเงิน (บาท) *</label>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           value="<?= $editTransaction['amount'] ?? '' ?>" min="0" step="0.01" required>
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
                       value="<?= htmlspecialchars($editTransaction['reference'] ?? '') ?>" 
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
                            <span class="badge <?= $transaction['type'] === 'income' ? 'bg-success' : ($transaction['type'] === 'transfer' ? 'bg-info' : 'bg-danger') ?>">
                                <?= $transaction['type'] === 'income' ? 'รายรับ' : ($transaction['type'] === 'transfer' ? 'การโอน' : 'รายจ่าย') ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $transaction['type'] === 'income' ? 'text-success' : ($transaction['type'] === 'transfer' ? 'text-info' : 'text-danger') ?> fw-bold">
                                <?= $transaction['type'] === 'income' ? '+' : ($transaction['type'] === 'transfer' ? '±' : '-') ?>
                                ฿<?= number_format($transaction['amount'], 2) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($transaction['reference']) ?>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($transaction['created_by_name']) ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=budget-control&edit=<?= $transaction['id'] ?>" 
                                   class="btn btn-outline-primary" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteTransaction(<?= $transaction['id'] ?>, '<?= htmlspecialchars($transaction['description']) ?>')" 
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

// Load categories when project is selected
document.getElementById('project_id').addEventListener('change', function() {
    const projectId = this.value;
    const categorySelect = document.getElementById('category_id');
    
    // Clear existing options
    categorySelect.innerHTML = '<option value="">เลือกหมวดหมู่</option>';
    
    if (projectId) {
        // Fetch categories for selected project
        fetch(`?get_categories=1&project_id=${projectId}`)
            .then(response => response.json())
            .then(categories => {
                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    // Use category_name for display
                    option.textContent = category.category_name || category.category;
                    categorySelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading categories:', error);
            });
    }
});

// Delete transaction function
function deleteTransaction(id, description) {
    document.getElementById('deleteTransactionId').value = id;
    document.getElementById('deleteTransactionDesc').textContent = description;
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
    if (amount <= 0) {
        e.preventDefault();
        alert('จำนวนเงินต้องมากกว่า 0');
        return false;
    }
});

// Auto-load categories if editing
<?php if ($editTransaction): ?>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('project_id');
    if (projectSelect.value) {
        projectSelect.dispatchEvent(new Event('change'));
    }
});
<?php endif; ?>
</script>