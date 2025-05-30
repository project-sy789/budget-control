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
        $totalBudget = floatval($_POST['total_budget'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        $budgetCategories = $_POST['budget_categories'] ?? [];
        
        // Validation
        if (empty($name) || empty($workGroup) || $totalBudget <= 0) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } elseif (strtotime($endDate) < strtotime($startDate)) {
            $error = 'วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มต้น';
        } else {
            // Validate budget categories
            $totalCategoryBudget = 0;
            $validCategories = [];
            
            foreach ($budgetCategories as $category) {
                if (!empty($category['name']) && !empty($category['budget']) && $category['budget'] > 0) {
                    $validCategories[] = [
                        'name' => trim($category['name']),
                        'budget' => floatval($category['budget'])
                    ];
                    $totalCategoryBudget += floatval($category['budget']);
                }
            }
            
            if ($totalCategoryBudget > $totalBudget) {
                $error = 'งบประมาณรวมของหมวดหมู่เกินงบประมาณโครงการ';
            } else {
                $projectData = [
                    'name' => $name,
                    'description' => $description,
                    'work_group' => $workGroup,
                    'total_budget' => $totalBudget,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status,
                    'created_by' => $currentUser['id']
                ];
                
                if ($action === 'create') {
                    $result = $projectService->createProject($projectData, $validCategories);
                    if ($result) {
                        $success = 'สร้างโครงการเรียบร้อยแล้ว';
                    } else {
                        $error = 'เกิดข้อผิดพลาดในการสร้างโครงการ';
                    }
                } else {
                    $projectId = intval($_POST['project_id']);
                    $result = $projectService->updateProject($projectId, $projectData);
                    if ($result) {
                        $success = 'อัปเดตโครงการเรียบร้อยแล้ว';
                    } else {
                        $error = 'เกิดข้อผิดพลาดในการอัปเดตโครงการ';
                    }
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
                                <input type="text" class="form-control" 
                                       name="budget_categories[<?= $index ?>][name]" 
                                       placeholder="ชื่อหมวดหมู่" 
                                       value="<?= htmlspecialchars($category['name']) ?>">
                            </div>
                            <div class="col-md-5">
                                <input type="number" class="form-control category-budget" 
                                       name="budget_categories[<?= $index ?>][budget]" 
                                       placeholder="งบประมาณ" 
                                       value="<?= $category['budget'] ?>" 
                                       min="0" step="0.01">
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
                            <input type="text" class="form-control" 
                                   name="budget_categories[0][name]" 
                                   placeholder="ชื่อหมวดหมู่">
                        </div>
                        <div class="col-md-5">
                            <input type="number" class="form-control category-budget" 
                                   name="budget_categories[0][budget]" 
                                   placeholder="งบประมาณ" 
                                   min="0" step="0.01">
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

document.getElementById('addCategory').addEventListener('click', function() {
    const container = document.getElementById('budgetCategories');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 budget-category-row';
    newRow.innerHTML = `
        <div class="col-md-6">
            <input type="text" class="form-control" 
                   name="budget_categories[${categoryIndex}][name]" 
                   placeholder="ชื่อหมวดหมู่">
        </div>
        <div class="col-md-5">
            <input type="number" class="form-control category-budget" 
                   name="budget_categories[${categoryIndex}][budget]" 
                   placeholder="งบประมาณ" 
                   min="0" step="0.01">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm remove-category">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    categoryIndex++;
    updateCategoryBudgetTotal();
});

// Remove category
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

// Update total budget calculation
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

// Listen for budget changes
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('category-budget')) {
        updateCategoryBudgetTotal();
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
    const budgetInputs = document.querySelectorAll('.category-budget');
    let totalCategoryBudget = 0;
    let hasValidCategory = false;
    
    budgetInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        if (value > 0) {
            hasValidCategory = true;
        }
        totalCategoryBudget += value;
    });
    
    if (!hasValidCategory || totalCategoryBudget <= 0) {
        e.preventDefault();
        alert('กรุณากรอกงบประมาณในหมวดหมู่อย่างน้อย 1 หมวดหมู่');
        return false;
    }
    
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    
    if (endDate < startDate) {
        e.preventDefault();
        alert('วันที่สิ้นสุดต้องมากกว่าวันที่เริ่มต้น');
        return false;
    }
});

// Initialize total calculation
updateCategoryBudgetTotal();
</script>