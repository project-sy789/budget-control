<?php
/**
 * Fiscal Year Management Page (Admin Only)
 */

if ($currentUser['role'] !== 'admin') {
    echo '<div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
    return;
}

require_once __DIR__ . '/../../src/Services/FiscalYearService.php';
$fiscalYearService = new FiscalYearService();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name']);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        
        $result = $fiscalYearService->create($name, $startDate, $endDate);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        
        $result = $fiscalYearService->update($id, $name, $startDate, $endDate);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        $result = $fiscalYearService->delete($id);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'set_active') {
        $id = intval($_POST['id']);
        
        $result = $fiscalYearService->setActiveYear($id);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$fiscalYears = $fiscalYearService->getAll();
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

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>จัดการ<?= $yearLabel ?></h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFiscalYearModal">
            <i class="bi bi-plus-lg me-1"></i> เพิ่ม<?= $yearLabel ?>
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?= $yearLabel ?></th>
                        <th>วันที่เริ่มต้น</th>
                        <th>วันที่สิ้นสุด</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fiscalYears as $fy): ?>
                    <tr class="<?= $fy['is_active'] ? 'table-success' : '' ?>">
                        <td><strong><?= htmlspecialchars($fy['name']) ?></strong></td>
                        <td><?= date('d/m/Y', strtotime($fy['start_date'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($fy['end_date'])) ?></td>
                        <td>
                            <?php if ($fy['is_active']): ?>
                                <span class="badge bg-success">ปัจจุบัน</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">ทั่วไป</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if (!$fy['is_active']): ?>
                                <button type="button" class="btn btn-outline-success" 
                                        onclick="setActive(<?= $fy['id'] ?>, '<?= htmlspecialchars($fy['name']) ?>')" 
                                        title="ตั้งเป็นปีปัจจุบัน">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="editFiscalYear(<?= htmlspecialchars(json_encode($fy)) ?>)" 
                                        title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteFiscalYear(<?= $fy['id'] ?>, '<?= htmlspecialchars($fy['name']) ?>')" 
                                        title="ลบ" <?= $fy['is_active'] ? 'disabled' : '' ?>>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addFiscalYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">เพิ่ม<?= $yearLabel ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ชื่อ<?= $yearLabel ?> (เช่น 2567)</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">วันที่เริ่มต้น</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editFiscalYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">แก้ไข<?= $yearLabel ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ชื่อ<?= $yearLabel ?></label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">วันที่เริ่มต้น</label>
                        <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteFiscalYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title">ยืนยันการลบ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>คุณต้องการลบ<?= $yearLabel ?> "<span id="delete_name"></span>" ใช่หรือไม่?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">ลบ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Set Active Modal -->
<div class="modal fade" id="setActiveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="set_active">
                <input type="hidden" name="id" id="active_id">
                <div class="modal-header">
                    <h5 class="modal-title">ยืนยันการเปลี่ยน<?= $yearLabel ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>คุณต้องการตั้ง "<span id="active_name"></span>" เป็น<?= $yearLabel ?>ปัจจุบันใช่หรือไม่?</p>
                    <small class="text-muted"><?= $yearLabel ?>เดิมจะถูกเปลี่ยนสถานะเป็น "ทั่วไป"</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">ยืนยัน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editFiscalYear(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_start_date').value = data.start_date;
    document.getElementById('edit_end_date').value = data.end_date;
    
    new bootstrap.Modal(document.getElementById('editFiscalYearModal')).show();
}

function deleteFiscalYear(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    
    new bootstrap.Modal(document.getElementById('deleteFiscalYearModal')).show();
}

function setActive(id, name) {
    document.getElementById('active_id').value = id;
    document.getElementById('active_name').textContent = name;
    
    new bootstrap.Modal(document.getElementById('setActiveModal')).show();
}
</script>
