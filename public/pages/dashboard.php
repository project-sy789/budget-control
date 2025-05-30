<?php
/**
 * Dashboard Page - Overview of projects and budget status
 */

// Get dashboard data
$projects = $projectService->getAllProjects();
$projectStats = $projectService->getProjectSummary();
$transactionStats = $transactionService->getTransactionSummary();

// Calculate totals
$totalBudget = 0;
$totalUsed = 0;
$totalRemaining = 0;
$activeProjects = 0;

foreach ($projects as $project) {
    $totalBudget += $project['total_budget'];
    $totalUsed += $project['used_budget'];
    $totalRemaining += $project['remaining_budget'];
    if ($project['status'] === 'active') {
        $activeProjects++;
    }
}

// Recent transactions
$recentTransactions = $transactionService->getAllTransactions(null, null, 5, 0); // Get latest 5 transactions
?>

<div class="row">
    <!-- Summary Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            โครงการทั้งหมด
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format(count($projects)) ?> โครงการ
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-folder fs-2 text-primary"></i>
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
                            โครงการที่ดำเนินการ
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($activeProjects) ?> โครงการ
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-play-circle fs-2 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            งบประมาณทั้งหมด
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            ฿<?= number_format($totalBudget, 2) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-currency-dollar fs-2 text-info"></i>
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
    <!-- Budget Usage Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-bar-chart me-2"></i>
                    การใช้งบประมาณตามโครงการ
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="budgetChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Budget Status Pie Chart -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-pie-chart me-2"></i>
                    สถานะงบประมาณ
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="mr-2">
                        <i class="bi bi-circle-fill text-primary me-1"></i>ใช้แล้ว
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
    <!-- Recent Transactions -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-clock-history me-2"></i>
                    รายการล่าสุด
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentTransactions)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2">ยังไม่มีรายการ</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>รายการ</th>
                                <th>จำนวน</th>
                                <th>ประเภท</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($transaction['date'])) ?></td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($transaction['project_name']) ?></small><br>
                                    <?= htmlspecialchars($transaction['description']) ?>
                                </td>
                                <td>
                                    <span class="<?= $transaction['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $transaction['amount'] >= 0 ? '+' : '-' ?>
                                        ฿<?= number_format(abs($transaction['amount']), 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $transaction['amount'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $transaction['amount'] >= 0 ? 'รายรับ' : 'รายจ่าย' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="?page=budget-control" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye me-1"></i>
                        ดูทั้งหมด
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Project Status -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-list-check me-2"></i>
                    สถานะโครงการ
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($projects)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-folder-x fs-1 text-muted"></i>
                    <p class="text-muted mt-2">ยังไม่มีโครงการ</p>
                    <a href="?page=projects" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus me-1"></i>
                        เพิ่มโครงการ
                    </a>
                </div>
                <?php else: ?>
                <?php 
                $displayProjects = array_slice($projects, 0, 5); // Show only first 5 projects
                foreach ($displayProjects as $project): 
                    $usagePercent = $project['total_budget'] > 0 ? ($project['used_budget'] / $project['total_budget']) * 100 : 0;
                    $statusClass = $project['status'] === 'active' ? 'success' : ($project['status'] === 'completed' ? 'primary' : 'secondary');
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="mb-0"><?= htmlspecialchars($project['name']) ?></h6>
                        <span class="badge bg-<?= $statusClass ?>">
                            <?= $project['status'] === 'active' ? 'ดำเนินการ' : ($project['status'] === 'completed' ? 'เสร็จสิ้น' : 'ระงับ') ?>
                        </span>
                    </div>
                    <div class="progress mb-1" style="height: 6px;">
                        <div class="progress-bar bg-<?= $usagePercent > 80 ? 'danger' : ($usagePercent > 60 ? 'warning' : 'success') ?>" 
                             role="progressbar" 
                             style="width: <?= min($usagePercent, 100) ?>%"></div>
                    </div>
                    <small class="text-muted">
                        ใช้ไป ฿<?= number_format($project['used_budget'], 2) ?> 
                        จาก ฿<?= number_format($project['total_budget'], 2) ?>
                        (<?= number_format($usagePercent, 1) ?>%)
                    </small>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($projects) > 5): ?>
                <div class="text-center mt-3">
                    <a href="?page=projects" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye me-1"></i>
                        ดูทั้งหมด (<?= count($projects) ?> โครงการ)
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard charts initializing...');
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded!');
        return;
    }
    
    // Budget Chart
    const budgetCtx = document.getElementById('budgetChart');
    if (!budgetCtx) {
        console.error('Budget chart canvas not found!');
        return;
    }
    
    console.log('Creating budget chart...');
    const budgetChart = new Chart(budgetCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: [<?php 
                $labels = [];
                foreach (array_slice($projects, 0, 10) as $project) {
                    $labels[] = "'" . addslashes($project['name']) . "'";
                }
                echo implode(', ', $labels);
            ?>],
            datasets: [{
                label: 'งบประมาณทั้งหมด',
                data: [<?php 
                    $budgets = [];
                    foreach (array_slice($projects, 0, 10) as $project) {
                        $budgets[] = $project['total_budget'];
                    }
                    echo implode(', ', $budgets);
                ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'ใช้แล้ว',
                data: [<?php 
                    $used = [];
                    foreach (array_slice($projects, 0, 10) as $project) {
                        $used[] = $project['used_budget'];
                    }
                    echo implode(', ', $used);
                ?>],
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '฿' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ฿' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Status Pie Chart
    const statusCtx = document.getElementById('statusChart');
    if (!statusCtx) {
        console.error('Status chart canvas not found!');
        return;
    }
    
    console.log('Creating status chart...');
    const statusChart = new Chart(statusCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['ใช้แล้ว', 'คงเหลือ'],
            datasets: [{
                data: [<?= $totalUsed ?>, <?= $totalRemaining ?>],
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
    
    console.log('Charts initialized successfully!');
});
</script>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
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
</style>