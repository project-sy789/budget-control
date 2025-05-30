<?php
/**
 * Budget Summary Page - Reports and Analysis
 */

// Get filter parameters
$projectFilter = intval($_GET['project_filter'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-01-01'); // Start of current year
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today
$workGroupFilter = $_GET['work_group_filter'] ?? '';

// Build filters
$filters = [];
if ($projectFilter > 0) {
    $filters['project_id'] = $projectFilter;
}
if (!empty($dateFrom)) {
    $filters['date_from'] = $dateFrom;
}
if (!empty($dateTo)) {
    $filters['date_to'] = $dateTo;
}

// Get data
$projects = $projectService->getAllProjects();
$transactionStats = $transactionService->getTransactionSummary($filters);
$projectSummary = $projectService->getProjectSummary();

// Filter projects by work group if specified
if (!empty($workGroupFilter)) {
    $projects = array_filter($projects, function($project) use ($workGroupFilter) {
        return $project['work_group'] === $workGroupFilter;
    });
}

// Calculate summary data
$totalBudget = 0;
$totalIncome = 0;
$totalExpense = 0;
$totalRemaining = 0;
$projectsByWorkGroup = [];
$budgetByCategory = [];

foreach ($projects as $project) {
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
    foreach ($categories as $category) {
        $remainingBalance = $transactionService->getProjectCategoryBalance($project['id'], $category['category']);
        $categoryName = $category['category']; // Use category enum value as name
        $usedAmount = $category['amount'] - $remainingBalance;
        
        if (!isset($budgetByCategory[$categoryName])) {
            $budgetByCategory[$categoryName] = [
                'budget' => 0,
                'used' => 0,
                'remaining' => 0
            ];
        }
        $budgetByCategory[$categoryName]['budget'] += $category['amount'];
        $budgetByCategory[$categoryName]['used'] += $usedAmount;
        $budgetByCategory[$categoryName]['remaining'] += $remainingBalance;
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
    'other' => 'อื่นๆ'
];
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
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($categoryName) ?></td>
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
                foreach ($projects as $project) {
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
                    $highUsageProjects = array_filter($projects, function($project) {
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