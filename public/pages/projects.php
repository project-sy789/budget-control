<?php
/**
 * Projects Management Page
 */

$error = '';
$success = '';
$editProject = null;

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
        $budgetCategories = $_POST['budget_categories'] ?? [];
        
        // Debug: Log the submitted budget categories
        echo "<!-- Debug - Budget Categories: " . json_encode($budgetCategories) . " -->";
        
        // Process budget categories
        $validCategories = [];
        $totalCategoryBudget = 0;
        
        // Debug: Show raw POST data
        echo "<!-- DEBUG POST DATA: " . htmlspecialchars(print_r($_POST, true)) . " -->";
        
        if (isset($_POST['budget_categories']) && is_array($_POST['budget_categories'])) {
            echo "<!-- DEBUG: Found budget_categories array with " . count($_POST['budget_categories']) . " items -->";
            foreach ($_POST['budget_categories'] as $index => $category) {
                echo "<!-- DEBUG Category $index: " . htmlspecialchars(print_r($category, true)) . " -->";
                
                // Get category name and budget amount
                $categoryName = $category['category'] ?? '';
                $categoryBudget = $category['budget'] ?? 0;
                $categoryDescription = $category['description'] ?? '';
                
                echo "<!-- DEBUG Parsed - Name: '$categoryName', Budget: '$categoryBudget' -->";
                
                if (!empty($categoryName) && !empty($categoryBudget) && floatval($categoryBudget) > 0) {
                    $validCategories[] = [
                        'category' => $categoryName,
                        'amount' => floatval($categoryBudget),
                        'description' => $categoryDescription
                    ];
                    $totalCategoryBudget += floatval($categoryBudget);
                    echo "<!-- DEBUG: Added valid category '$categoryName' with budget " . floatval($categoryBudget) . " -->";
                } else {
                    echo "<!-- DEBUG: Skipped invalid category - Name: '$categoryName', Budget: '$categoryBudget' -->";
                }
            }
        } else {
            echo "<!-- DEBUG: No budget_categories found in POST or not an array -->";
        }
        
        // Use calculated total budget from categories
        $totalBudget = $totalCategoryBudget;
        
        // Debug: Log the calculated total budget
        echo "<!-- Debug - Total Budget Calculated: " . $totalBudget . ", Valid Categories: " . count($validCategories) . " -->";
        
        // Validation
        if (empty($name) || empty($workGroup) || empty($responsiblePerson) || $totalBudget <= 0) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วนและต้องมีหมวดหมู่งบประมาณอย่างน้อย 1 หมวดหมู่';
        } elseif (strtotime($endDate) < strtotime($startDate)) {
            $error = 'วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มต้น';
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
                'budget_categories' => $validCategories
            ];
            
            if ($action === 'create') {
                // Debug output
                error_log('=== PROJECT CREATION DEBUG ===');
                error_log('Full POST data: ' . print_r($_POST, true));
                error_log('Submitted budget_categories: ' . print_r($_POST['budget_categories'], true));
                error_log('Calculated total budget: ' . $totalBudget);
                error_log('Valid categories count: ' . count($validCategories));
                error_log('Project data to be saved: ' . print_r($projectData, true));
                error_log('Current user ID: ' . ($currentUser['id'] ?? 'NULL'));
                error_log('=== END DEBUG ===');
                
                // Test database connection
                try {
                    $testQuery = $db->query('SELECT COUNT(*) as count FROM projects');
                    $testResult = $testQuery->fetch();
                    error_log('Database connection test - Current projects count: ' . $testResult['count']);
                    echo "<!-- Database connection test successful. Current projects: " . $testResult['count'] . " -->";
                } catch (Exception $e) {
                    error_log('Database connection test failed: ' . $e->getMessage());
                    echo "<!-- Database connection test FAILED: " . htmlspecialchars($e->getMessage()) . " -->";
                    $error = 'ข้อผิดพลาดการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
                }
                
                $result = $projectService->createProject($projectData, $currentUser['id']);
                error_log('Create project result: ' . print_r($result, true));
                
                if ($result['success']) {
                    $success = 'สร้างโครงการเรียบร้อยแล้ว';
                    // Verify the project was actually saved
                    try {
                        $verifyQuery = $db->query('SELECT COUNT(*) as count FROM projects');
                        $verifyResult = $verifyQuery->fetch();
                        error_log('After creation - Projects count: ' . $verifyResult['count']);
                    } catch (Exception $e) {
                        error_log('Verification query failed: ' . $e->getMessage());
                    }
                } else {
                    $error = $result['message'] ?? 'เกิดข้อผิดพลาดในการสร้างโครงการ';
                    error_log('Project creation failed: ' . $error);
                }
            } else {
                $projectId = intval($_POST['project_id']);
                $result = $projectService->updateProject($projectId, $projectData);
                if ($result['success']) {
                    $success = 'อัปเดตโครงการเรียบร้อยแล้ว';
                } else {
                    $error = $result['message'] ?? 'เกิดข้อผิดพลาดในการอัปเดตโครงการ';
                }
            }
         }
    } elseif ($action === 'delete') {
        $projectId = intval($_POST['project_id']);
        $result = $projectService->deleteProject($projectId);
        if ($result) {
            $success = 'ลบโครงการเรียบร้อยแล้ว';
        } else {
            $error = 'ไม่สามารถลบโครงการได้ เนื่องจากมีรายการเบิกจ่ายอยู่';
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

// Get all projects
$projects = $projectService->getAllProjects();

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
            // Check if project has transfer transactions
            $hasTransfers = false;
            try {
                $transferCheckQuery = "SELECT COUNT(*) as count FROM transactions WHERE (project_id = :project_id OR transfer_to_project_id = :project_id OR transfer_from_project_id = :project_id) AND is_transfer = 1";
                $transferCheckStmt = $db->prepare($transferCheckQuery);
                $transferCheckStmt->bindParam(':project_id', $editProject['id']);
                $transferCheckStmt->execute();
                $transferResult = $transferCheckStmt->fetch(PDO::FETCH_ASSOC);
                $hasTransfers = $transferResult['count'] > 0;
            } catch (Exception $e) {
                // Ignore error, assume no transfers
            }
        ?>
        <?php if ($hasTransfers): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>คำเตือน:</strong> โครงการนี้มีประวัติการโอนงบประมาณ การแก้ไขหมวดหมู่งบประมาณจะอัปเดตเฉพาะงบประมาณเริ่มต้น 
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
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">ชื่อโครงการ *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?= htmlspecialchars($editProject['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="work_group" class="form-label">กลุ่มงาน *</label>
                    <select class="form-select" id="work_group" name="work_group" required>
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
                           value="<?= htmlspecialchars($editProject['responsible_person'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">รายละเอียด</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($editProject['description'] ?? '') ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="total_budget" class="form-label">งบประมาณทั้งหมด (บาท) *</label>
                    <input type="number" class="form-control bg-light" id="total_budget" name="total_budget" 
                           value="<?= $editProject['total_budget'] ?? '' ?>" min="0" step="0.01" readonly required>
                    <small class="text-muted">คำนวณอัตโนมัติจากหมวดหมู่งบประมาณ</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="start_date" class="form-label">วันที่เริ่มต้น *</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?= $editProject['start_date'] ?? '' ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="end_date" class="form-label">วันที่สิ้นสุด *</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?= $editProject['end_date'] ?? '' ?>" required>
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
                <div id="budgetCategories">
                    <?php if ($editProject && !empty($editProject['categories'])): ?>
                        <?php foreach ($editProject['categories'] as $index => $category): ?>
                        <div class="row mb-2 budget-category-row">
                            <div class="col-md-6">
                                <select class="form-select" 
                                        name="budget_categories[<?= $index ?>][category]" 
                                        required>
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
                                       min="0" step="0.01" required>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-category">
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
                <button type="button" class="btn btn-secondary btn-sm" id="addCategory">
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
        <h5 class="mb-0">
            <i class="bi bi-list me-2"></i>
            รายการโครงการ (<?= count($projects) ?> โครงการ)
        </h5>
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
                            <strong><?= htmlspecialchars($project['name']) ?></strong>
                            <?php if ($project['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(substr($project['description'], 0, 50)) ?><?= strlen($project['description']) > 50 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $workGroupClass ?> text-white">
                                <?= $workGroups[$project['work_group']] ?? $project['work_group'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($project['responsible_person']) ?></td>
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
    
    // Automatically update the total budget field
    document.getElementById('total_budget').value = total;
    
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
});

// Delete project function
function deleteProject(id, name) {
    document.getElementById('deleteProjectId').value = id;
    document.getElementById('deleteProjectName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Form validation
document.getElementById('projectForm').addEventListener('submit', function(e) {
    // Show elegant Thai notification
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 9999;
        font-family: 'Sarabun', sans-serif;
        font-size: 14px;
        animation: slideIn 0.3s ease-out;
    `;
    notification.innerHTML = '🚀 กำลังส่งข้อมูลโครงการ...';
    document.body.appendChild(notification);
    
    // Add CSS animation
    if (!document.getElementById('notificationStyle')) {
        const style = document.createElement('style');
        style.id = 'notificationStyle';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideIn 0.3s ease-out reverse';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
    console.log('=== FORM SUBMISSION START ===');
    
    // Check all form data
    const formData = new FormData(this);
    console.log('Form data entries:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    const budgetInputs = document.querySelectorAll('.category-budget');
    let totalCategoryBudget = 0;
    let hasValidCategory = false;
    
    console.log('Form submission - Budget inputs found:', budgetInputs.length);
    
    budgetInputs.forEach((input, index) => {
        const value = parseFloat(input.value) || 0;
        console.log(`Budget input ${index}: value = ${value}, name = ${input.name}`);
        if (value > 0) {
            hasValidCategory = true;
        }
        totalCategoryBudget += value;
    });
    
    console.log('Total category budget:', totalCategoryBudget, 'Has valid category:', hasValidCategory);
    
    // Temporarily disable validation to debug
    console.log('Validation check - hasValidCategory:', hasValidCategory, 'totalCategoryBudget:', totalCategoryBudget);
    
    /*
    // Validate budget categories
    if (!hasValidCategory || totalCategoryBudget <= 0) {
        e.preventDefault();
        
        // Show elegant Thai error notification
        const errorNotification = document.createElement('div');
        errorNotification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 9999;
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
        `;
        errorNotification.innerHTML = '⚠️ กรุณาเพิ่มหมวดหมู่งบประมาณอย่างน้อย 1 หมวดหมู่ และระบุจำนวนเงินที่ถูกต้อง';
        document.body.appendChild(errorNotification);
        
        // Remove notification after 5 seconds
        setTimeout(() => {
            if (errorNotification.parentNode) {
                errorNotification.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => errorNotification.remove(), 300);
            }
        }, 5000);
        
        return false;
    }
    */
    
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    
    if (endDate < startDate) {
        e.preventDefault();
        alert('วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มต้น');
        console.log('Form submission blocked: Invalid date range');
        return false;
    }
    
    console.log('Form validation passed, submitting...');
    console.log('=== FORM SUBMISSION END ===');
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Attach event listeners to existing budget inputs
    attachBudgetListeners();
    
    // Only calculate initial total if not in edit mode
    // In edit mode, preserve the existing total budget value
    <?php if (!$editProject): ?>
    updateCategoryBudgetTotal();
    <?php endif; ?>
});
</script>