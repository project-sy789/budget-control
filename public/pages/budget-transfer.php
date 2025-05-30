<?php
/**
 * Budget Transfer Page - Transfer budget between projects
 */

$message = '';
$messageType = '';

// Handle transfer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $fromProjectId = intval($_POST['from_project_id']);
    $toProjectId = intval($_POST['to_project_id']);
    $fromCategory = trim($_POST['from_category']);
    $toCategory = trim($_POST['to_category']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $transferDate = $_POST['transfer_date'];
    
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

// Get projects for dropdown
$projects = $projectService->getAllProjects(null, 'active');

// Get budget category types from database schema
function getBudgetCategoryTypes($projectService) {
    try {
        // Use reflection to access the private connection from ProjectService
        $reflection = new ReflectionClass($projectService);
        $connProperty = $reflection->getProperty('conn');
        $connProperty->setAccessible(true);
        $conn = $connProperty->getValue($projectService);
        
        $query = "SHOW COLUMNS FROM budget_categories LIKE 'category'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && isset($result['Type'])) {
            // Extract ENUM values from Type column
            preg_match('/enum\((.+)\)/i', $result['Type'], $matches);
            if (isset($matches[1])) {
                $enumValues = str_getcsv($matches[1], ',', "'");
                return $enumValues;
            }
        }
        return [];
    } catch (Exception $e) {
        error_log("Get budget category types error: " . $e->getMessage());
        return [];
    }
}

// Get available category types from database
$availableCategoryTypes = getBudgetCategoryTypes($projectService);

// Define budget categories mapping for display
$budgetCategoryNames = [
    'SUBSIDY' => 'เงินอุดหนุน',
    'DEVELOPMENT' => 'ค่าพัฒนา',
    'INCOME' => 'รายได้',
    'EQUIPMENT' => 'ครุภัณฑ์',
    'UNIFORM' => 'เครื่องแบบ',
    'BOOKS' => 'หนังสือ',
    'LUNCH' => 'อาหารกลางวัน'
];

// Filter category names to only include available types
$budgetCategoryNames = array_intersect_key($budgetCategoryNames, array_flip($availableCategoryTypes));

// Get all project categories for AJAX
$projectCategories = [];
foreach ($projects as $project) {
    $categories = $projectService->getProjectBudgetCategories($project['id']);
    $projectCategories[$project['id']] = $categories;
}

// Get recent transfers (filter for transfer transactions only)
$recentTransfers = $transactionService->getTransferHistory(10, 0);
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

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
                                    (คงเหลือ: ฿<?= number_format($project['remaining_budget'], 2) ?>)
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
                                    (คงเหลือ: ฿<?= number_format($project['remaining_budget'], 2) ?>)
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
                        <td><?= date('d/m/Y', strtotime($transfer['date'])) ?></td>
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
    
    // Budget category names mapping
    const categoryNames = {
        'SUBSIDY': 'เงินอุดหนุน',
        'DEVELOPMENT': 'ค่าพัฒนา',
        'INCOME': 'รายได้',
        'EQUIPMENT': 'ครุภัณฑ์',
        'UNIFORM': 'เครื่องแบบ',
        'BOOKS': 'หนังสือ',
        'LUNCH': 'อาหารกลางวัน'
    };
    
    // Project categories data from PHP
    const projectCategories = <?= json_encode($projectCategories) ?>;
    
    // Update category dropdown based on selected project
    function updateCategoryDropdown(projectId, categorySelect, defaultText) {
        categorySelect.innerHTML = '';
        
        if (!projectId || !projectCategories[projectId]) {
            categorySelect.innerHTML = `<option value="">${defaultText}</option>`;
            categorySelect.disabled = true;
            return;
        }
        
        categorySelect.innerHTML = '<option value="">เลือกหมวดหมู่งบประมาณ</option>';
        
        const categories = projectCategories[projectId];
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.category;
            option.textContent = `${categoryNames[category.category] || category.category} (฿${parseFloat(category.amount).toLocaleString()})`;
            option.dataset.amount = category.amount;
            categorySelect.appendChild(option);
        });
        
        categorySelect.disabled = false;
    }
    
    // Update destination category dropdown to match source category
    function updateDestinationCategory(selectedCategory) {
        const toProjectId = toProjectSelect.value;
        
        toCategorySelect.innerHTML = '';
        
        if (!selectedCategory || !toProjectId || !projectCategories[toProjectId]) {
            toCategorySelect.innerHTML = '<option value="">เลือกหมวดหมู่ต้นทางก่อน</option>';
            toCategorySelect.disabled = true;
            return;
        }
        
        // Find matching category in destination project
        const toCategories = projectCategories[toProjectId];
        const matchingCategory = toCategories.find(cat => cat.category === selectedCategory);
        
        // Always allow transfer with the same category as source
        toCategorySelect.innerHTML = '<option value="">เลือกหมวดหมู่งบประมาณ</option>';
        const option = document.createElement('option');
        option.value = selectedCategory;
        
        if (matchingCategory) {
            // If category exists in destination project, show its current amount
            option.textContent = `${categoryNames[selectedCategory] || selectedCategory} (฿${parseFloat(matchingCategory.amount).toLocaleString()})`;
            option.dataset.amount = matchingCategory.amount;
        } else {
            // If category doesn't exist in destination project, show as new category
            option.textContent = `${categoryNames[selectedCategory] || selectedCategory} (หมวดหมู่ใหม่)`;
            option.dataset.amount = 0;
        }
        
        toCategorySelect.appendChild(option);
        toCategorySelect.disabled = false;
    }
    
    // Update project info when selection changes
    function updateProjectInfo() {
        const fromOption = fromProjectSelect.selectedOptions[0];
        const toOption = toProjectSelect.selectedOptions[0];
        
        // Update from project info
        if (fromOption && fromOption.value) {
            const fromBudget = parseFloat(fromOption.dataset.budget);
            document.getElementById('fromProjectInfo').innerHTML = 
                `<span class="text-info">งบประมาณคงเหลือ: ฿${fromBudget.toLocaleString()}</span>`;
        } else {
            document.getElementById('fromProjectInfo').innerHTML = '';
        }
        
        // Update to project info
        if (toOption && toOption.value) {
            const toBudget = parseFloat(toOption.dataset.budget);
            document.getElementById('toProjectInfo').innerHTML = 
                `<span class="text-info">งบประมาณคงเหลือ: ฿${toBudget.toLocaleString()}</span>`;
        } else {
            document.getElementById('toProjectInfo').innerHTML = '';
        }
        
        validateForm();
    }
    
    // Validate amount input
    function validateAmount() {
        const fromOption = fromProjectSelect.selectedOptions[0];
        const amount = parseFloat(amountInput.value);
        const amountInfo = document.getElementById('amountInfo');
        
        if (fromOption && fromOption.value && amount > 0) {
            const fromBudget = parseFloat(fromOption.dataset.budget);
            
            if (amount > fromBudget) {
                amountInfo.innerHTML = '<span class="text-danger">จำนวนเงินเกินงบประมาณคงเหลือ</span>';
                return false;
            } else {
                amountInfo.innerHTML = '<span class="text-success">จำนวนเงินถูกต้อง</span>';
                return true;
            }
        } else {
            amountInfo.innerHTML = '';
            return false;
        }
    }
    
    // Update transfer summary
    function updateSummary() {
        const fromOption = fromProjectSelect.selectedOptions[0];
        const toOption = toProjectSelect.selectedOptions[0];
        const fromCategory = fromCategorySelect.value;
        const toCategory = toCategorySelect.value;
        const amount = parseFloat(amountInput.value);
        
        if (fromOption && fromOption.value && toOption && toOption.value && 
            fromCategory && toCategory && amount > 0) {
            const fromBudget = parseFloat(fromOption.dataset.budget);
            const toBudget = parseFloat(toOption.dataset.budget);
            
            document.getElementById('summaryFromProject').textContent = fromOption.dataset.name;
            document.getElementById('summaryToProject').textContent = toOption.dataset.name;
            document.getElementById('summaryFromCategory').textContent = categoryNames[fromCategory] || fromCategory;
            document.getElementById('summaryToCategory').textContent = categoryNames[toCategory] || toCategory;
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
        const fromProjectId = fromProjectSelect.value;
        const toProjectId = toProjectSelect.value;
        const fromCategory = fromCategorySelect.value;
        const toCategory = toCategorySelect.value;
        const amount = parseFloat(amountInput.value);
        
        const isValid = fromProjectId && toProjectId && fromCategory && toCategory &&
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
        Array.from(toProjectSelect.options).forEach(option => {
            option.disabled = option.value === this.value;
        });
        
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
        Array.from(fromProjectSelect.options).forEach(option => {
            option.disabled = option.value === this.value;
        });
        
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
    
    amountInput.addEventListener('input', validateForm);
    
    fromCategorySelect.addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        if (selectedOption && selectedOption.value) {
            const categoryAmount = parseFloat(selectedOption.dataset.amount);
            document.getElementById('fromCategoryInfo').innerHTML = 
                `<span class="text-info">งบประมาณในหมวดหมู่: ฿${categoryAmount.toLocaleString()}</span>`;
        } else {
            document.getElementById('fromCategoryInfo').innerHTML = '';
        }
        // Update destination category to match source category
        updateDestinationCategory(this.value);
        validateForm();
    });
    
    toCategorySelect.addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        if (selectedOption && selectedOption.value) {
            const categoryAmount = parseFloat(selectedOption.dataset.amount);
            document.getElementById('toCategoryInfo').innerHTML = 
                `<span class="text-info">งบประมาณในหมวดหมู่: ฿${categoryAmount.toLocaleString()}</span>`;
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
    
    // Reset form
    form.addEventListener('reset', function() {
        setTimeout(() => {
            Array.from(fromProjectSelect.options).forEach(option => option.disabled = false);
            Array.from(toProjectSelect.options).forEach(option => option.disabled = false);
            
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