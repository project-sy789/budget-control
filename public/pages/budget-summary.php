<?php
/**
 * Budget Summary Page - Reports and Analysis
 */

// Initialize FiscalYearService
require_once __DIR__ . '/../../src/Services/FiscalYearService.php';
$fiscalYearService = new FiscalYearService();
$fiscalYears = $fiscalYearService->getAll();
$activeFiscalYear = $fiscalYearService->getActiveYear();

// Get filter parameters
$fiscalYearFilter = $_GET['fiscal_year_filter'] ?? ($activeFiscalYear ? $activeFiscalYear['id'] : null);
$projectFilter = intval($_GET['project_filter'] ?? 0);
$workGroupFilter = $_GET['work_group_filter'] ?? '';

// Determine default dates based on fiscal year
$defaultDateFrom = date('Y-01-01');
$defaultDateTo = date('Y-12-31');

if (!empty($fiscalYearFilter)) {
    // Find selected fiscal year dates
    foreach ($fiscalYears as $fy) {
        if ($fy['id'] == $fiscalYearFilter) {
            $defaultDateFrom = $fy['start_date'];
            $defaultDateTo = $fy['end_date'];
            break;
        }
    }
}

$dateFrom = $_GET['date_from'] ?? $defaultDateFrom;
$dateTo = $_GET['date_to'] ?? $defaultDateTo;

// Build filters
$filters = [];
if ($projectFilter > 0) {
    $filters['project_id'] = $projectFilter;
}
if (!empty($fiscalYearFilter)) {
    $filters['fiscal_year_id'] = $fiscalYearFilter;
}
if (!empty($dateFrom)) {
    $filters['date_from'] = $dateFrom;
}
if (!empty($dateTo)) {
    $filters['date_to'] = $dateTo;
}

// Get data (filtered by fiscal year)
$allProjects = $projectService->getAllProjects(null, null, $fiscalYearFilter);
$transactionStats = $transactionService->getTransactionSummary($filters);
$projectSummary = $projectService->getProjectSummary($fiscalYearFilter);

// Get dynamic budget categories from CategoryService
require_once '../src/Services/CategoryService.php';
$categoryService = new CategoryService($db);

// Filter projects for display in dropdown (keep all projects for dropdown)
$projects = $allProjects;

// Filter projects for calculations based on both work group and project filters
$filteredProjects = $allProjects;
if (!empty($workGroupFilter)) {
    $filteredProjects = array_filter($filteredProjects, function($project) use ($workGroupFilter) {
        return $project['work_group'] === $workGroupFilter;
    });
}
if ($projectFilter > 0) {
    $filteredProjects = array_filter($filteredProjects, function($project) use ($projectFilter) {
        return $project['id'] == $projectFilter;
    });
}

// Calculate summary data
$totalBudget = 0;
$totalIncome = 0;
$totalExpense = 0;
$totalRemaining = 0;
$projectsByWorkGroup = [];
$budgetByCategory = [];

foreach ($filteredProjects as $project) {
    $totalBudget += $project['total_budget'];
    $totalRemaining += $project['remaining_budget'];
    
    // Group by work group
    $workGroup = $project['work_group'];
    if (!isset($projectsByWorkGroup[$workGroup])) {
        $projectsByWorkGroup[$workGroup] = [
            'count' => 0,
            'total_budget' => 0,
            'used_budget' => 0,
            'remaining_budget' => 0
        ];
    }
    $projectsByWorkGroup[$workGroup]['count']++;
    $projectsByWorkGroup[$workGroup]['total_budget'] += $project['total_budget'];
    $projectsByWorkGroup[$workGroup]['used_budget'] += $project['used_budget'];
    $projectsByWorkGroup[$workGroup]['remaining_budget'] += $project['remaining_budget'];
    
    // Get category breakdown for this project
    $categories = $projectService->getProjectBudgetCategories($project['id']);
    
    // Also check for transactions in categories that don't have budget allocations
    $allActiveCategories = $categoryService->getAllActiveCategories();
    $existingCategoryKeys = array_column($categories, 'category');
    
    // Add categories that have transactions but no budget allocation
    foreach ($allActiveCategories as $activeCategory) {
        if (!in_array($activeCategory['category_key'], $existingCategoryKeys)) {
            // Check if this category has any transactions for this project
            $remainingBalance = $transactionService->getProjectCategoryBalanceForSummary($project['id'], $activeCategory['category_key']);
            if ($remainingBalance != 0) { // Only include if there are transactions
                $categories[] = [
                    'category' => $activeCategory['category_key'],
                    'category_name' => $activeCategory['category_name'],
                    'amount' => 0 // No budget allocation
                ];
            }
        }
    }
    
    foreach ($categories as $category) {
        // Use category key for transaction balance lookup
        $categoryKey = $category['category'] ?? '';
        $categoryName = $category['category_name'] ?? $categoryKey;
        
        // Skip if no category key
        if (empty($categoryKey)) {
            continue;
        }
        
        // Get remaining balance using category key
        $remainingBalance = $transactionService->getProjectCategoryBalanceForSummary($project['id'], $categoryKey);
        $budgetAmount = floatval($category['amount'] ?? 0);
        
        // Calculate used amount: budget - remaining
        $usedAmount = $budgetAmount - $remainingBalance;
        
        // Use category name as key for grouping
        if (!isset($budgetByCategory[$categoryName])) {
            $budgetByCategory[$categoryName] = [
                'budget' => 0,
                'used' => 0,
                'remaining' => 0
            ];
        }
        
        $budgetByCategory[$categoryName]['budget'] += $budgetAmount;
        $budgetByCategory[$categoryName]['used'] += max(0, $usedAmount); // Ensure non-negative
        $budgetByCategory[$categoryName]['remaining'] += max(0, $remainingBalance); // Ensure non-negative
    }
}

$totalIncome = $transactionStats['total_income'] ?? 0;
$totalExpense = $transactionStats['total_expense'] ?? 0;

// Work groups
$workGroups = [
    'academic' => 'งานวิชาการ',
    'budget' => 'งานงบประมาณ',
    'hr' => 'งานบุคลากร',
    'general' => 'งานทั่วไป',
    'other' => 'อื่น ๆ'
];

// Get dynamic budget categories from CategoryService
$budgetCategoriesData = $categoryService->getAllActiveCategories();
$budgetCategories = [];
foreach ($budgetCategoriesData as $category) {
    $budgetCategories[$category['category_key']] = $category['category_name'];
}
?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="bi bi-funnel me-2"></i>
            ตัวกรองรายงาน
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="budget-summary">
            
            <div class="col-md-2">
                <label for="fiscal_year_filter" class="form-label"><?= $yearLabel ?></label>
                <select class="form-select" id="fiscal_year_filter" name="fiscal_year_filter" onchange="this.form.submit()">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($fiscalYears as $fy): ?>
                    <option value="<?= $fy['id'] ?>" <?= $fiscalYearFilter == $fy['id'] ? 'selected' : '' ?>>
                        <?= $fy['name'] . ($fy['is_active'] ? ' (ปัจจุบัน)' : '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="work_group_filter" class="form-label">กลุ่มงาน</label>
                <select class="form-select" id="work_group_filter" name="work_group_filter">
                    <option value="">ทุกกลุ่มงาน</option>
                    <?php foreach ($workGroups as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $workGroupFilter === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
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
                        ดูรายงาน
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportReport()">
                        <i class="bi bi-download me-1"></i>
                        ส่งออก
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            งบประมาณทั้งหมด
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ฿<?= number_format($totalBudget, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-currency-dollar fs-2 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            รายรับทั้งหมด
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ฿<?= number_format($totalIncome, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-arrow-up-circle fs-2 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            รายจ่ายทั้งหมด
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ฿<?= number_format($totalExpense, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-arrow-down-circle fs-2 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            งบประมาณคงเหลือ
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ฿<?= number_format($totalRemaining, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-wallet2 fs-2 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Budget by Work Group -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-bar-chart me-2"></i>
                    งบประมาณตามกลุ่มงาน
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>กลุ่มงาน</th>
                                <th>จำนวนโครงการ</th>
                                <th>งบประมาณ</th>
                                <th>ใช้แล้ว</th>
                                <th>คงเหลือ</th>
                                <th>%การใช้</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projectsByWorkGroup as $workGroup => $data): 
                                $usagePercent = $data['total_budget'] > 0 ? ($data['used_budget'] / $data['total_budget']) * 100 : 0;
                                $workGroupClass = 'work-group-' . $workGroup;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $workGroupClass ?> text-white">
                                        <?= $workGroups[$workGroup] ?? $workGroup ?>
                                    </span>
                                </td>
                                <td><?= $data['count'] ?> โครงการ</td>
                                <td>฿<?= number_format($data['total_budget'], 2) ?></td>
                                <td>฿<?= number_format($data['used_budget'], 2) ?></td>
                                <td>฿<?= number_format($data['remaining_budget'], 2) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                            <div class="progress-bar bg-<?= $usagePercent > 80 ? 'danger' : ($usagePercent > 60 ? 'warning' : 'success') ?>" 
                                                 style="width: <?= min($usagePercent, 100) ?>%"></div>
                                        </div>
                                        <small><?= number_format($usagePercent, 1) ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Budget Usage Chart -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-pie-chart me-2"></i>
                    สัดส่วนการใช้งบประมาณ
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="budgetUsageChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="mr-2">
                        <i class="bi bi-circle-fill text-danger me-1"></i>ใช้แล้ว
                    </span>
                    <span class="mr-2">
                        <i class="bi bi-circle-fill text-success me-1"></i>คงเหลือ
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Budget by Category -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-tags me-2"></i>
                    งบประมาณตามหมวดหมู่
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($budgetByCategory)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2">ไม่มีข้อมูลหมวดหมู่</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>หมวดหมู่</th>
                                <th>งบประมาณ</th>
                                <th>ใช้แล้ว</th>
                                <th>คงเหลือ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgetByCategory as $categoryName => $data): 
                                $usagePercent = $data['budget'] > 0 ? ($data['used'] / $data['budget']) * 100 : 0;
                                // categoryName is already the display name from category_name field
                                $displayName = $categoryName;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($displayName) ?></td>
                                <td>฿<?= number_format($data['budget'], 2) ?></td>
                                <td>
                                    <span class="text-danger">฿<?= number_format($data['used'], 2) ?></span>
                                    <div class="progress mt-1" style="height: 4px;">
                                        <div class="progress-bar bg-<?= $usagePercent > 80 ? 'danger' : ($usagePercent > 60 ? 'warning' : 'success') ?>" 
                                             style="width: <?= min($usagePercent, 100) ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-success">฿<?= number_format($data['remaining'], 2) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Project Status Summary -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-clipboard-data me-2"></i>
                    สรุปสถานะโครงการ
                </h6>
            </div>
            <div class="card-body">
                <?php 
                $statusCounts = ['active' => 0, 'completed' => 0, 'suspended' => 0];
                foreach ($filteredProjects as $project) {
                    $statusCounts[$project['status']]++;
                }
                ?>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-end">
                            <div class="h4 text-success"><?= $statusCounts['active'] ?></div>
                            <small class="text-muted">ดำเนินการ</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <div class="h4 text-primary"><?= $statusCounts['completed'] ?></div>
                            <small class="text-muted">เสร็จสิ้น</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-secondary"><?= $statusCounts['suspended'] ?></div>
                        <small class="text-muted">ระงับ</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="mt-3">
                    <h6>โครงการที่ใช้งบประมาณเกิน 80%</h6>
                    <?php 
                    $highUsageProjects = array_filter($filteredProjects, function($project) {
                        return $project['total_budget'] > 0 && ($project['used_budget'] / $project['total_budget']) > 0.8;
                    });
                    ?>
                    <?php if (empty($highUsageProjects)): ?>
                    <p class="text-muted">ไม่มีโครงการที่ใช้งบประมาณเกิน 80%</p>
                    <?php else: ?>
                    <ul class="list-unstyled">
                        <?php foreach (array_slice($highUsageProjects, 0, 5) as $project): 
                            $usagePercent = ($project['used_budget'] / $project['total_budget']) * 100;
                        ?>
                        <li class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><?= htmlspecialchars($project['name']) ?></span>
                                <span class="badge bg-<?= $usagePercent > 90 ? 'danger' : 'warning' ?>">
                                    <?= number_format($usagePercent, 1) ?>%
                                </span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Budget summary charts initializing...');
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded!');
        return;
    }
    
    // Budget Usage Pie Chart
    const budgetUsageCtx = document.getElementById('budgetUsageChart');
    if (!budgetUsageCtx) {
        console.error('Budget usage chart canvas not found!');
        return;
    }
    
    console.log('Creating budget usage chart...');
    const budgetUsageChart = new Chart(budgetUsageCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['ใช้แล้ว', 'คงเหลือ'],
            datasets: [{
                data: [<?= $totalExpense ?>, <?= $totalRemaining ?>],
                backgroundColor: ['#e74c3c', '#2ecc71'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ฿' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    console.log('Budget summary chart initialized successfully!');
});

// Work group change event to filter projects
    document.getElementById('work_group_filter').addEventListener('change', function() {
        const workGroup = this.value;
        const projectSelect = document.getElementById('project_filter');
        
        // Store current project selection
        const currentProject = projectSelect.value;
        
        // Clear project options
        projectSelect.innerHTML = '<option value="">ทุกโครงการ</option>';
        
        // Get all projects from PHP
        const allProjects = <?= json_encode($allProjects) ?>;
        
        if (workGroup) {
            // Filter projects by work group
            const filteredProjects = allProjects.filter(project => project.work_group === workGroup);
            
            // Populate project dropdown with filtered projects
            filteredProjects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.name;
                if (project.id == currentProject) {
                    option.selected = true;
                }
                projectSelect.appendChild(option);
            });
        } else {
            // Show all projects if no work group selected
            allProjects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.name;
                if (project.id == currentProject) {
                    option.selected = true;
                }
                projectSelect.appendChild(option);
            });
        }
});

// Export report function
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'summary');
    window.location.href = 'export.php?' + params.toString();
}

// Print report function
function printReport() {
    window.print();
}
</script>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-danger {
    border-left: 0.25rem solid #e74c3c !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.text-xs {
    font-size: 0.7rem;
}
.font-weight-bold {
    font-weight: 700 !important;
}
.text-gray-800 {
    color: #5a5c69 !important;
}

@media print {
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    .btn, .form-control, .form-select {
        display: none !important;
    }
}
</style>