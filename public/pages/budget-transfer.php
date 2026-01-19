<?php
/**
 * Budget Transfer Page - Transfer budget between projects
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
    require_once __DIR__ . '/../../src/Services/FiscalYearService.php';
    
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Initialize services
    $sessionManager = new SessionManager();
    $authService = new AuthService();
    $projectService = new ProjectService();

    $transactionService = new TransactionService();
    $fiscalYearService = new FiscalYearService();
    
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
    
    $isDirectAccess = true;
} else {
    $isDirectAccess = false;
    
    // Ensure fiscalYearService is available if included
    if (!isset($fiscalYearService)) {
        require_once __DIR__ . '/../../src/Services/FiscalYearService.php';
        $fiscalYearService = new FiscalYearService();
    }
}

$message = '';
$messageType = '';

// Check for success parameter from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = 'โอนงบประมาณสำเร็จ';
    $messageType = 'success';
}

// Handle transfer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $fromProjectId = intval($_POST['from_project_id']);
    $toProjectId = intval($_POST['to_project_id']);
    $fromCategory = trim($_POST['from_category']);
    $toCategory = trim($_POST['to_category']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $transferDate = date('Y-m-d'); // Always use current date
    
    if ($fromProjectId && $toProjectId && $fromCategory && $toCategory && $amount > 0 && $fromProjectId !== $toProjectId) {
        try {
            $result = $transactionService->transferBudgetWithCategory(
                $fromProjectId,
                $toProjectId,
                $fromCategory,
                $toCategory,
                $amount,
                $description,
                $transferDate,
                $currentUser['id']
            );
            
            if ($result) {
                $message = 'โอนงบประมาณสำเร็จ';
                $messageType = 'success';
                // Redirect to refresh the page and show updated balances
                if ($isDirectAccess) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                    exit;
                }
            } else {
                $message = 'เกิดข้อผิดพลาดในการโอนงบประมาณ';
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง (รวมถึงหมวดหมู่งบประมาณ)';
        $messageType = 'warning';
    }
}

// Get all fiscal years
$fiscalYears = $fiscalYearService->getAll();
$activeFiscalYear = $fiscalYearService->getActiveYear();

// Determine selected fiscal year
$selectedFiscalYearId = $_GET['fiscal_year_id'] ?? ($activeFiscalYear ? $activeFiscalYear['id'] : null);

// Get projects for dropdown (filtered by selected fiscal year)
$projects = $projectService->getAllProjects(null, 'active', $selectedFiscalYearId);

// Get dynamic budget categories from CategoryService
require_once '../src/Services/CategoryService.php';
$categoryService = new CategoryService($db);
$budgetCategoriesData = $categoryService->getAllActiveCategories();
$availableCategoryTypes = [];
foreach ($budgetCategoriesData as $category) {
    $availableCategoryTypes[] = $category['category_key'];
}

// Get all project categories for AJAX
$projectCategories = [];
foreach ($projects as $project) {
    $categories = $projectService->getProjectBudgetCategories($project['id']);
    $projectCategories[$project['id']] = $categories;
}

// Get recent transfers (filter for transfer transactions only)
$recentTransfers = $transactionService->getTransferHistory(10, 0);
?>

<?php if ($isDirectAccess): ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โอนงบประมาณ - Budget Control System</title>
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

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="d-flex justify-content-end flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <div class="btn-toolbar mb-2 mb-md-0">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="page" value="budget-transfer">
            <label for="fiscal_year_id" class="me-2 text-nowrap"><?= isset($yearLabel) ? $yearLabel : 'ปีงบประมาณ' ?>:</label>
            <select class="form-select form-select-sm" id="fiscal_year_id" name="fiscal_year_id" onchange="this.form.submit()">
                <?php foreach ($fiscalYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $selectedFiscalYearId == $fy['id'] ? 'selected' : '' ?>>
                    <?= $fy['name'] ?> <?= $fy['is_active'] ? '(ปัจจุบัน)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="row">
    <!-- Transfer Form -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-arrow-left-right me-2"></i>
                    โอนงบประมาณระหว่างโครงการ
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" id="transferForm">
                    <input type="hidden" name="action" value="transfer">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="from_project_id" class="form-label">โครงการต้นทาง *</label>
                            <select class="form-select" id="from_project_id" name="from_project_id" required>
                                <option value="">เลือกโครงการต้นทาง</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" 
                                        data-budget="<?= $project['remaining_budget'] ?>"
                                        data-name="<?= htmlspecialchars($project['name']) ?>">
                                    <?= htmlspecialchars($project['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="fromProjectInfo"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="to_project_id" class="form-label">โครงการปลายทาง *</label>
                            <select class="form-select" id="to_project_id" name="to_project_id" required>
                                <option value="">เลือกโครงการปลายทาง</option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" 
                                        data-budget="<?= $project['remaining_budget'] ?>"
                                        data-name="<?= htmlspecialchars($project['name']) ?>">
                                    <?= htmlspecialchars($project['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="toProjectInfo"></div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="from_category" class="form-label">หมวดหมู่งบประมาณต้นทาง *</label>
                            <select class="form-select" id="from_category" name="from_category" required disabled>
                                <option value="">เลือกโครงการต้นทางก่อน</option>
                            </select>
                            <div class="form-text" id="fromCategoryInfo"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="to_category" class="form-label">หมวดหมู่งบประมาณปลายทาง *</label>
                            <select class="form-select" id="to_category" name="to_category" required disabled>
                                <option value="">เลือกหมวดหมู่ต้นทางก่อน</option>
                            </select>
                            <div class="form-text" id="toCategoryInfo">
                                <small class="text-info">หมายเหตุ: จะโอนเป็นหมวดหมู่เดียวกับต้นทาง (หากไม่มีจะสร้างใหม่)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">จำนวนเงิน *</label>
                            <div class="input-group">
                                <span class="input-group-text">฿</span>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0.01" required>
                            </div>
                            <div class="form-text" id="amountInfo"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="transfer_date" class="form-label">วันที่โอน *</label>
                            <input type="date" class="form-control" id="transfer_date" name="transfer_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">หมายเหตุ</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="ระบุเหตุผลในการโอนงบประมาณ..."></textarea>
                    </div>
                    
                    <!-- Transfer Summary -->
                    <div class="card bg-light mb-3" id="transferSummary" style="display: none;">
                        <div class="card-body">
                            <h6 class="card-title">สรุปการโอน</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>จาก:</strong> <span id="summaryFromProject"></span><br>
                                    <strong>หมวดหมู่:</strong> <span id="summaryFromCategory"></span><br>
                                    <strong>งบประมาณคงเหลือ:</strong> <span id="summaryFromBudget"></span><br>
                                    <strong>หลังโอน:</strong> <span id="summaryFromAfter" class="text-danger"></span>
                                </div>
                                <div class="col-md-6">
                                    <strong>ไป:</strong> <span id="summaryToProject"></span><br>
                                    <strong>หมวดหมู่:</strong> <span id="summaryToCategory"></span><br>
                                    <strong>งบประมาณคงเหลือ:</strong> <span id="summaryToBudget"></span><br>
                                    <strong>หลังรับโอน:</strong> <span id="summaryToAfter" class="text-success"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="bi bi-arrow-left-right me-1"></i>
                            โอนงบประมาณ
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            รีเซ็ต
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Transfer Guidelines -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">
                    <i class="bi bi-info-circle me-2"></i>
                    หลักเกณฑ์การโอนงบประมาณ
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        สามารถโอนได้เฉพาะโครงการที่มีสถานะ "ดำเนินการ"
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        จำนวนเงินที่โอนต้องไม่เกินงบประมาณคงเหลือ
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        ต้องระบุเหตุผลในการโอนงบประมาณ
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        จะโอนเป็นหมวดหมู่เดียวกับต้นทาง (หากปลายทางไม่มีจะสร้างใหม่)
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        การโอนจะถูกบันทึกเป็นรายการธุรกรรม
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                        ไม่สามารถยกเลิกการโอนได้หลังจากยืนยัน
                    </li>
                </ul>
                
                <hr>
                
                <h6 class="text-muted">ข้อมูลเพิ่มเติม</h6>
                <p class="small text-muted">
                    การโอนงบประมาณจะสร้างรายการธุรกรรมแบบ "โอนออก" สำหรับโครงการต้นทาง 
                    และ "รับโอน" สำหรับโครงการปลายทาง โดยจะโอนเป็นหมวดหมู่เดียวกับต้นทาง หากโครงการปลายทางไม่มีหมวดหมู่นั้นจะสร้างใหม่
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transfers -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="bi bi-clock-history me-2"></i>
            ประวัติการโอนล่าสุด
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($recentTransfers)): ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <p class="text-muted mt-2">ยังไม่มีประวัติการโอนงบประมาณ</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>จาก</th>
                        <th>ไป</th>
                        <th>จำนวน</th>
                        <th>หมายเหตุ</th>
                        <th>ผู้ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransfers as $transfer): ?>
                    <?php 
                        $isTransferOut = $transfer['amount'] < 0;
                        $transferAmount = abs($transfer['amount']);
                    ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($transfer['transaction_date'])) ?></td>
                        <td>
                            <?php if ($isTransferOut): ?>
                            <span class="text-danger">
                                <i class="bi bi-arrow-right me-1"></i>
                                <?= htmlspecialchars($transfer['project_name']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isTransferOut): ?>
                            <span class="text-success">
                                <i class="bi bi-arrow-left me-1"></i>
                                <?= htmlspecialchars($transfer['project_name']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="<?= $isTransferOut ? 'text-danger' : 'text-success' ?>">
                                <?= $isTransferOut ? '-' : '+' ?>฿<?= number_format($transferAmount, 2) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($transfer['description']) ?></td>
                        <td><?= htmlspecialchars($transfer['created_by_name'] ?? 'ไม่ระบุ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fromProjectSelect = document.getElementById('from_project_id');
    const toProjectSelect = document.getElementById('to_project_id');
    const fromCategorySelect = document.getElementById('from_category');
    const toCategorySelect = document.getElementById('to_category');
    const amountInput = document.getElementById('amount');
    const submitBtn = document.getElementById('submitBtn');
    const transferSummary = document.getElementById('transferSummary');
    const form = document.getElementById('transferForm');
    
    // No hardcoded category names - use dynamic categories from database
    
    // Project categories data from PHP
    const projectCategories = <?= json_encode($projectCategories) ?>;
    
    // Update category dropdown based on selected project with fresh balance data
    function updateCategoryDropdown(projectId, categorySelect, defaultText) {
        categorySelect.innerHTML = '';
        
        if (!projectId) {
            categorySelect.innerHTML = `<option value="">${defaultText}</option>`;
            categorySelect.disabled = true;
            return Promise.resolve();
        }
        
        // Show loading state
        categorySelect.innerHTML = '<option value="">กำลังโหลด...</option>';
        categorySelect.disabled = true;
        
        // Fetch fresh category data with current balances using aggressive cache-busting
        const cacheBreaker = `_t=${Date.now()}&_r=${Math.random()}`;
        return fetch(`../../src/Services/get_project_categories.php?project_id=${projectId}&${cacheBreaker}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Fresh category data received for project:', projectId, data);
                categorySelect.innerHTML = '<option value="">เลือกหมวดหมู่งบประมาณ</option>';
                
                if (data.success && data.categories) {
                    data.categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.category;
                        option.textContent = category.category_name;
                        option.dataset.amount = category.remaining_balance || 0;
                        categorySelect.appendChild(option);
                        console.log(`Added category ${category.category} with balance: ${category.remaining_balance}`);
                    });
                    categorySelect.disabled = false;
                } else {
                    console.error('Failed to load categories:', data);
                    categorySelect.innerHTML = `<option value="">${defaultText}</option>`;
                    categorySelect.disabled = true;
                }
            })
            .catch(error => {
                console.error('Error fetching categories:', error);
                categorySelect.innerHTML = '<option value="">เกิดข้อผิดพลาดในการโหลดข้อมูล</option>';
                categorySelect.disabled = true;
            });
    }
    
    // Update destination category dropdown to match source category with fresh data
    function updateDestinationCategory(selectedCategory) {
        const toProjectId = toProjectSelect.value;
        
        toCategorySelect.innerHTML = '';
        
        if (!selectedCategory || !toProjectId) {
            toCategorySelect.innerHTML = '<option value="">เลือกหมวดหมู่ต้นทางก่อน</option>';
            toCategorySelect.disabled = true;
            return;
        }
        
        // Show loading state
        toCategorySelect.innerHTML = '<option value="">กำลังโหลด...</option>';
        toCategorySelect.disabled = true;
        
        // Fetch fresh category data for destination project with aggressive cache-busting
        const cacheBreaker = `_t=${Date.now()}&_r=${Math.random()}`;
        fetch(`../../src/Services/get_project_categories.php?project_id=${toProjectId}&${cacheBreaker}`)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(function(data) {
                console.log('Fresh destination category data received:', data);
                toCategorySelect.innerHTML = '<option value="">เลือกหมวดหมู่งบประมาณ</option>';
                
                var option = document.createElement('option');
                option.value = selectedCategory;
                
                // Check if category exists in destination project
                var matchingCategory = null;
                if (data.success && data.categories) {
                    matchingCategory = data.categories.find(function(cat) { return cat.category === selectedCategory; });
                }
                
                if (matchingCategory) {
                    // If category exists in destination project, show current balance
                    option.textContent = matchingCategory.category_name;
                    option.dataset.amount = matchingCategory.remaining_balance || 0;
                } else {
                    // If category doesn't exist, get category name from source and show as new
                    var fromProjectId = fromProjectSelect.value;
                    const sourceCacheBreaker = `_t=${Date.now()}&_r=${Math.random()}`;
                    fetch(`../../src/Services/get_project_categories.php?project_id=${fromProjectId}&${sourceCacheBreaker}`)
                        .then(function(response) {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(function(sourceData) {
                            if (sourceData.success && sourceData.categories) {
                                var sourceCategory = sourceData.categories.find(function(cat) { return cat.category === selectedCategory; });
                                var categoryName = sourceCategory ? sourceCategory.category_name : selectedCategory;
                                option.textContent = categoryName + ' (หมวดหมู่ใหม่)';
                            } else {
                                option.textContent = selectedCategory + ' (หมวดหมู่ใหม่)';
                            }
                        })
                        .catch(function(error) {
                            console.error('Error fetching source category name:', error);
                            option.textContent = selectedCategory + ' (หมวดหมู่ใหม่)';
                        });
                    option.dataset.amount = 0; // New category starts with 0 balance
                }
                
                toCategorySelect.appendChild(option);
                toCategorySelect.disabled = false;
            })
    }
    
    // Store current balances globally
    var currentBalances = {};
    
    // Update project info when selection changes with real-time data
    function updateProjectInfo() {
        var fromOption = fromProjectSelect.selectedOptions[0];
        var toOption = toProjectSelect.selectedOptions[0];
        
        // Update from project info
        if (fromOption && fromOption.value) {
            document.getElementById('fromProjectInfo').innerHTML = '<span class="text-muted">กำลังโหลด...</span>';
            fetch(`../../src/Services/get_project_balance.php?project_id=${fromOption.value}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            document.getElementById('fromProjectInfo').innerHTML = 
                                `<span class="text-info">งบประมาณคงเหลือ: ฿${parseFloat(data.balance).toLocaleString()}</span>`;
                            currentBalances[fromOption.value] = data.balance;
                            fromOption.dataset.currentBalance = data.balance;
                        } else {
                            console.error('API Error:', data.message);
                            document.getElementById('fromProjectInfo').innerHTML = `<span class="text-danger">ไม่สามารถโหลดข้อมูลได้: ${data.message || 'Unknown error'}</span>`;
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e, 'Raw text:', text);
                        document.getElementById('fromProjectInfo').innerHTML = '<span class="text-danger">ข้อมูลไม่ถูกต้อง</span>';
                    }
                    validateForm();
                })
                .catch((error) => {
                    console.error('Fetch Error:', error);
                    document.getElementById('fromProjectInfo').innerHTML = `<span class="text-danger">เกิดข้อผิดพลาด: ${error.message}</span>`;
                    validateForm();
                });
        } else {
            document.getElementById('fromProjectInfo').innerHTML = '';
        }
        
        // Update to project info
        if (toOption && toOption.value) {
            document.getElementById('toProjectInfo').innerHTML = '<span class="text-muted">กำลังโหลด...</span>';
            fetch(`../../src/Services/get_project_balance.php?project_id=${toOption.value}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            document.getElementById('toProjectInfo').innerHTML = 
                                `<span class="text-info">งบประมาณคงเหลือ: ฿${parseFloat(data.balance).toLocaleString()}</span>`;
                            currentBalances[toOption.value] = data.balance;
                            toOption.dataset.currentBalance = data.balance;
                        } else {
                            console.error('API Error:', data.message);
                            document.getElementById('toProjectInfo').innerHTML = `<span class="text-danger">ไม่สามารถโหลดข้อมูลได้: ${data.message || 'Unknown error'}</span>`;
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e, 'Raw text:', text);
                        document.getElementById('toProjectInfo').innerHTML = '<span class="text-danger">ข้อมูลไม่ถูกต้อง</span>';
                    }
                    validateForm();
                })
                .catch((error) => {
                    console.error('Fetch Error:', error);
                    document.getElementById('toProjectInfo').innerHTML = `<span class="text-danger">เกิดข้อผิดพลาด: ${error.message}</span>`;
                    validateForm();
                });
        } else {
            document.getElementById('toProjectInfo').innerHTML = '';
        }
    }
    
    // Validate amount input with enhanced balance preview using real-time data
    function validateAmount() {
        const fromOption = fromProjectSelect.selectedOptions[0];
        const fromCategoryOption = fromCategorySelect.selectedOptions[0];
        const amount = parseFloat(amountInput.value);
        const amountInfo = document.getElementById('amountInfo');
        
        if (fromOption && fromOption.value && amount > 0) {
            // Use current balance from API or fallback to dataset
            const fromBudget = parseFloat(currentBalances[fromOption.value] || fromOption.dataset.currentBalance || fromOption.dataset.budget || 0);
            const fromCategoryAmount = fromCategoryOption ? parseFloat(fromCategoryOption.dataset.amount || 0) : 0;
            
            let validationHtml = '';
            let isValid = true;
            
            // Check against total project budget
            if (amount > fromBudget) {
                validationHtml += '<span class="text-danger">จำนวนเงินเกินงบประมาณโครงการ</span><br>';
                isValid = false;
            }
            
            // Check against category budget if category is selected
            if (fromCategoryOption && fromCategoryOption.value && amount > fromCategoryAmount) {
                validationHtml += '<span class="text-warning">จำนวนเงินเกินงบประมาณหมวดหมู่</span><br>';
            }
            
            // Show balance preview
            if (isValid) {
                validationHtml += '<span class="text-success">จำนวนเงินถูกต้อง</span><br>';
                validationHtml += `<span class="text-muted">งบประมาณโครงการหลังโอน: ฿${(fromBudget - amount).toLocaleString()}</span>`;
                
                if (fromCategoryOption && fromCategoryOption.value) {
                    validationHtml += `<br><span class="text-muted">งบประมาณหมวดหมู่หลังโอน: ฿${(fromCategoryAmount - amount).toLocaleString()}</span>`;
                }
            }
            
            amountInfo.innerHTML = validationHtml;
            return isValid;
        } else {
            amountInfo.innerHTML = '';
            return false;
        }
    }
    
    // Update transfer summary with real-time balance data
    function updateSummary() {
        const fromOption = fromProjectSelect.selectedOptions[0];
        const toOption = toProjectSelect.selectedOptions[0];
        const fromCategory = fromCategorySelect.value;
        const toCategory = toCategorySelect.value;
        const amount = parseFloat(amountInput.value);
        
        if (fromOption && fromOption.value && toOption && toOption.value && 
            fromCategory && toCategory && amount > 0) {
            // Use current balance from API or fallback to dataset
            var fromBudget = parseFloat(currentBalances[fromOption.value] || fromOption.dataset.currentBalance || fromOption.dataset.budget || 0);
            var toBudget = parseFloat(currentBalances[toOption.value] || toOption.dataset.currentBalance || toOption.dataset.budget || 0);
            
            document.getElementById('summaryFromProject').textContent = fromOption.dataset.name;
            document.getElementById('summaryToProject').textContent = toOption.dataset.name;
            document.getElementById('summaryFromCategory').textContent = fromCategory;
            document.getElementById('summaryToCategory').textContent = toCategory;
            document.getElementById('summaryFromBudget').textContent = `฿${fromBudget.toLocaleString()}`;
            document.getElementById('summaryToBudget').textContent = `฿${toBudget.toLocaleString()}`;
            document.getElementById('summaryFromAfter').textContent = `฿${(fromBudget - amount).toLocaleString()}`;
            document.getElementById('summaryToAfter').textContent = `฿${(toBudget + amount).toLocaleString()}`;
            
            transferSummary.style.display = 'block';
        } else {
            transferSummary.style.display = 'none';
        }
    }
    
    // Validate entire form
    function validateForm() {
        var fromProjectId = fromProjectSelect.value;
        var toProjectId = toProjectSelect.value;
        var fromCategory = fromCategorySelect.value;
        var toCategory = toCategorySelect.value;
        var amount = parseFloat(amountInput.value);
        
        var isValid = fromProjectId && toProjectId && fromCategory && toCategory &&
                       fromProjectId !== toProjectId && 
                       amount > 0 && validateAmount();
        
        submitBtn.disabled = !isValid;
        
        if (isValid) {
            updateSummary();
        } else {
            transferSummary.style.display = 'none';
        }
    }
    
    // Event listeners
    fromProjectSelect.addEventListener('change', function() {
        // Disable same project in to dropdown
        Array.from(toProjectSelect.options).forEach(function(option) {
            option.disabled = option.value === this.value;
        }.bind(this));
        
        // Update category dropdown for source project
        updateCategoryDropdown(this.value, fromCategorySelect, 'เลือกโครงการต้นทางก่อน');
        
        // Reset category selection
        fromCategorySelect.value = '';
        document.getElementById('fromCategoryInfo').innerHTML = '';
        
        // Reset destination category when source project changes
        toCategorySelect.innerHTML = '<option value="">เลือกหมวดหมู่ต้นทางก่อน</option>';
        toCategorySelect.disabled = true;
        
        updateProjectInfo();
    });
    
    toProjectSelect.addEventListener('change', function() {
        // Disable same project in from dropdown
        Array.from(fromProjectSelect.options).forEach(function(option) {
            option.disabled = option.value === this.value;
        }.bind(this));
        
        // Reset category selection
        toCategorySelect.value = '';
        document.getElementById('toCategoryInfo').innerHTML = '';
        
        // Update destination category based on selected source category
        const selectedFromCategory = fromCategorySelect.value;
        if (selectedFromCategory) {
            updateDestinationCategory(selectedFromCategory);
        } else {
            toCategorySelect.innerHTML = '<option value="">เลือกหมวดหมู่ต้นทางก่อน</option>';
            toCategorySelect.disabled = true;
        }
        
        updateProjectInfo();
    });
    
    amountInput.addEventListener('input', function() {
        validateAmount();
        updateDestinationCategoryPreview();
        validateForm();
    });
    
    // Function to update destination category preview when amount changes
    function updateDestinationCategoryPreview() {
        const selectedOption = toCategorySelect.selectedOptions[0];
        const amount = parseFloat(amountInput.value || 0);
        
        if (selectedOption && selectedOption.value) {
            const categoryAmount = parseFloat(selectedOption.dataset.amount);
            let infoHtml = `<span class="text-info">งบประมาณในหมวดหมู่: ฿${categoryAmount.toLocaleString()}</span>`;
            
            // Show preview of balance after receiving transfer
            if (amount > 0) {
                var newBalance = categoryAmount + amount;
                infoHtml += '<br><span class="text-success">หลังรับโอน: ฿' + newBalance.toLocaleString() + '</span>';
            }
            
            document.getElementById('toCategoryInfo').innerHTML = infoHtml;
        }
    }
    
    fromCategorySelect.addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        if (selectedOption && selectedOption.value) {
            // Always fetch fresh balance data, never use cached dataset.amount
            const projectId = fromProjectSelect.value;
            const categoryName = selectedOption.value;
            
            // Show loading state
            document.getElementById('fromCategoryInfo').innerHTML = 
                `<span class="text-muted">กำลังโหลดข้อมูลงบประมาณ...</span>`;
            
            // Force fresh data with aggressive cache-busting
            const cacheBreaker = `_t=${Date.now()}&_r=${Math.random()}`;
            fetch(`../../src/Services/get_category_balance.php?project_id=${projectId}&category=${encodeURIComponent(categoryName)}&${cacheBreaker}`)
                .then(response => {
                    // Ensure we're not getting cached response
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Fresh balance data received:', data);
                    if (data.success) {
                        const categoryAmount = parseFloat(data.remaining_balance || 0);
                        document.getElementById('fromCategoryInfo').innerHTML = 
                            `<span class="text-info">งบประมาณในหมวดหมู่: ฿${categoryAmount.toLocaleString()}</span>`;
                        // Update the dataset for consistency
                        selectedOption.dataset.amount = categoryAmount;
                        console.log(`Updated category ${categoryName} balance to: ${categoryAmount}`);
                    } else {
                        console.error('API returned error:', data);
                        document.getElementById('fromCategoryInfo').innerHTML = 
                            `<span class="text-danger">ไม่สามารถโหลดข้อมูลงบประมาณได้</span>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching category balance:', error);
                    document.getElementById('fromCategoryInfo').innerHTML = 
                        `<span class="text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</span>`;
                });
        } else {
            document.getElementById('fromCategoryInfo').innerHTML = '';
        }
        // Update destination category to match source category
        updateDestinationCategory(this.value);
        // Re-validate amount when category changes
        validateAmount();
        validateForm();
    });
    
    toCategorySelect.addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        const amount = parseFloat(amountInput.value || 0);
        
        if (selectedOption && selectedOption.value) {
            const categoryAmount = parseFloat(selectedOption.dataset.amount);
            let infoHtml = `<span class="text-info">งบประมาณในหมวดหมู่: ฿${categoryAmount.toLocaleString()}</span>`;
            
            // Show preview of balance after receiving transfer
            if (amount > 0) {
                var newBalance = categoryAmount + amount;
                infoHtml += '<br><span class="text-success">หลังรับโอน: ฿' + newBalance.toLocaleString() + '</span>';
            }
            
            document.getElementById('toCategoryInfo').innerHTML = infoHtml;
        } else {
            document.getElementById('toCategoryInfo').innerHTML = '';
        }
        validateForm();
    });
    
    // Form submission confirmation
    form.addEventListener('submit', function(e) {
        if (!confirm('คุณต้องการโอนงบประมาณนี้หรือไม่? การดำเนินการนี้ไม่สามารถยกเลิกได้')) {
            e.preventDefault();
        }
    });
    
    // Function to refresh category data after successful transfer
    function refreshCategoryData() {
        const fromProjectId = fromProjectSelect.value;
        const toProjectId = toProjectSelect.value;
        
        // Store current selections to restore them after refresh
        const currentFromCategory = fromCategorySelect.value;
        const currentToCategory = toCategorySelect.value;
        
        // Clear category selections first
        fromCategorySelect.value = '';
        toCategorySelect.value = '';
        
        // Clear info displays
        document.getElementById('fromCategoryInfo').innerHTML = '';
        document.getElementById('toCategoryInfo').innerHTML = '';
        document.getElementById('amountInfo').innerHTML = '';
        transferSummary.style.display = 'none';
        submitBtn.disabled = true;
        
        // Refresh category dropdowns with fresh data
        const promises = [];
        
        if (fromProjectId) {
            const fromPromise = updateCategoryDropdown(fromProjectId, fromCategorySelect, 'เลือกโครงการต้นทางก่อน').then(() => {
                // If there was a previously selected category, reselect it and fetch fresh balance
                if (currentFromCategory) {
                    fromCategorySelect.value = currentFromCategory;
                    // Trigger change event to fetch fresh balance data
                    fromCategorySelect.dispatchEvent(new Event('change'));
                }
            });
            promises.push(fromPromise);
        }
        
        if (toProjectId) {
            const toPromise = updateCategoryDropdown(toProjectId, toCategorySelect, 'เลือกโครงการปลายทางก่อน').then(() => {
                // If there was a previously selected category, reselect it
                if (currentToCategory) {
                    toCategorySelect.value = currentToCategory;
                    // Trigger change event to update display
                    toCategorySelect.dispatchEvent(new Event('change'));
                }
            });
            promises.push(toPromise);
        }
        
        // Wait for all updates to complete
        Promise.all(promises).then(() => {
            console.log('Category data refresh completed');
        }).catch(error => {
            console.error('Error refreshing category data:', error);
        });
        
        // Clear amount input
        amountInput.value = '';
        
        // Show success message to user
        const successMessage = document.createElement('div');
        successMessage.className = 'alert alert-success alert-dismissible fade show';
        successMessage.innerHTML = `
            <strong>สำเร็จ!</strong> การโอนงบประมาณเสร็จสิ้น กรุณาเลือกหมวดหมู่อีกครั้งเพื่อดูยอดงบประมาณที่อัปเดต
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert message at the top of the form
        const formContainer = document.querySelector('.card-body');
        formContainer.insertBefore(successMessage, formContainer.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (successMessage.parentNode) {
                successMessage.remove();
            }
        }, 5000);
    }
    
    // Check for success message and refresh data if transfer was successful
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        // Small delay to ensure DOM is ready
        setTimeout(function() {
            refreshCategoryData();
        }, 100);
    }
    
    // Reset form
    form.addEventListener('reset', function() {
        setTimeout(function() {
            Array.from(fromProjectSelect.options).forEach(function(option) {
                option.disabled = false;
            });
            Array.from(toProjectSelect.options).forEach(function(option) {
                option.disabled = false;
            });
            
            // Reset category dropdowns
            fromCategorySelect.innerHTML = '<option value="">เลือกโครงการต้นทางก่อน</option>';
            toCategorySelect.innerHTML = '<option value="">เลือกโครงการปลายทางก่อน</option>';
            fromCategorySelect.disabled = true;
            toCategorySelect.disabled = true;
            
            // Clear all info displays
            document.getElementById('fromProjectInfo').innerHTML = '';
            document.getElementById('toProjectInfo').innerHTML = '';
            document.getElementById('fromCategoryInfo').innerHTML = '';
            document.getElementById('toCategoryInfo').innerHTML = '';
            document.getElementById('amountInfo').innerHTML = '';
            
            transferSummary.style.display = 'none';
            submitBtn.disabled = true;
        }, 10);
    });
});
</script>

<style>
.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.form-control:focus, .form-select:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

.btn-primary {
    background-color: #4e73df;
    border-color: #4e73df;
}

.btn-primary:hover {
    background-color: #2e59d9;
    border-color: #2653d4;
}

.text-xs {
    font-size: 0.7rem;
}

.font-weight-bold {
    font-weight: 700 !important;
}

#transferSummary {
    border: 1px solid #e3e6f0;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #5a5c69;
}
</style>

<?php if ($isDirectAccess): ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>