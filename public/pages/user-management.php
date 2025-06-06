<?php
/**
 * User Management Page - Admin only
 */

// Check if user is admin
if ($currentUser['role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

$message = '';
$messageType = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $displayName = trim($_POST['full_name']);
            $role = $_POST['role'];
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if ($password !== $confirmPassword) {
                $message = 'รหัสผ่านไม่ตรงกัน';
                $messageType = 'danger';
            } elseif (strlen($password) < 6) {
                $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
                $messageType = 'danger';
            } else {
                try {
                    $userData = [
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'display_name' => $displayName,
                        'department' => '',
                        'position' => '',
                        'role' => $role,
                        'approved' => 1
                    ];
                    $result = $authService->register($userData);
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        $message = $result['message'];
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'update_user':
            $userId = intval($_POST['user_id']);
            $email = trim($_POST['email']);
            $displayName = trim($_POST['full_name']);
            $role = $_POST['role'];
            $approved = isset($_POST['is_approved']) ? 1 : 0;
            
            // Debug logging
            error_log("Update user request - User ID: $userId, Role: $role, Email: $email, Display Name: $displayName, Approved: $approved");
            
            // Validate role
            $validRoles = ['user', 'manager', 'admin'];
            if (!in_array($role, $validRoles)) {
                $message = 'บทบาทที่เลือกไม่ถูกต้อง';
                $messageType = 'danger';
                break;
            }
            
            try {
                $result = $authService->updateUser($userId, $email, $displayName, $role, $approved);
                error_log("Update user result: " . json_encode($result));
                
                if ($result['success']) {
                    $message = $result['message'];
                    $messageType = 'success';
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                error_log("Update user exception: " . $e->getMessage());
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'reset_password':
            $userId = intval($_POST['user_id']);
            $newPassword = $_POST['new_password'];
            
            if (strlen($newPassword) < 6) {
                $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
                $messageType = 'danger';
            } else {
                try {
                    $result = $authService->changePassword($userId, '', $newPassword, true); // Admin reset
                    if ($result) {
                        $message = 'รีเซ็ตรหัสผ่านสำเร็จ';
                        $messageType = 'success';
                    } else {
                        $message = 'ไม่สามารถรีเซ็ตรหัสผ่านได้';
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'delete_user':
            $userId = intval($_POST['user_id']);
            
            // Prevent admin from deleting themselves
            if ($userId == $currentUser['id']) {
                $message = 'ไม่สามารถลบบัญชีของตนเองได้';
                $messageType = 'danger';
            } else {
                try {
                    $result = $authService->deleteUser($userId);
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        $message = $result['message'];
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;
    }
}

// Get all users
$users = $authService->getAllUsers();

// User roles
$roles = [
    'user' => 'ผู้ใช้ทั่วไป',
    'manager' => 'ผู้จัดการ',
    'admin' => 'ผู้ดูแลระบบ'
];
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Create User Form -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-person-plus me-2"></i>
                    เพิ่มผู้ใช้ใหม่
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" id="createUserForm">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">ชื่อผู้ใช้ *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">อีเมล *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">ชื่อ-นามสกุล *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">บทบาท *</label>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach ($roles as $roleKey => $roleLabel): ?>
                            <option value="<?= $roleKey ?>"><?= $roleLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">รหัสผ่าน *</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               minlength="6" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-1"></i>
                        เพิ่มผู้ใช้
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Users List -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-people me-2"></i>
                    รายการผู้ใช้ (<?= count($users) ?> คน)
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>ชื่อผู้ใช้</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>อีเมล</th>
                                <th>บทบาท</th>
                                <th>สถานะ</th>
                                <th>เข้าใช้ล่าสุด</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                    <?php if ($user['id'] == $currentUser['id']): ?>
                                    <span class="badge bg-info ms-1">คุณ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user['display_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning' : 'secondary') ?>">
                                        <?= $roles[$user['role']] ?? $user['role'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['approved']): ?>
                                    <span class="badge bg-success">อนุมัติ</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">รออนุมัติ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                    <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                                    <?php else: ?>
                                    <span class="text-muted">ยังไม่เคยเข้าใช้</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editUser(<?php 
                                                    // Pass user data directly without modification
                                                    $userForEdit = $user;
                                                    // Debug: log the complete user data being passed
                                                    error_log('Passing user to editUser - ID: ' . $user['id'] . ', Username: ' . $user['username'] . ', Role: "' . $user['role'] . '"');
                                                    error_log('Complete user data: ' . json_encode($userForEdit));
                                                    echo htmlspecialchars(json_encode($userForEdit, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                                ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" 
                                                onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <?php if ($user['id'] != $currentUser['id'] && $user['username'] !== 'admin'): ?>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
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
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">แก้ไขผู้ใช้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">อีเมล</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">บทบาท</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <?php foreach ($roles as $roleKey => $roleLabel): ?>
                            <option value="<?= $roleKey ?>"><?= $roleLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_approved" name="is_approved">
                            <label class="form-check-label" for="edit_is_approved">
                                อนุมัติให้เข้าใช้ระบบ
                            </label>
                        </div>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">รีเซ็ตรหัสผ่าน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="resetPasswordForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <p>รีเซ็ตรหัสผ่านสำหรับผู้ใช้: <strong id="reset_username"></strong></p>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               minlength="6" required>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ผู้ใช้จะต้องเปลี่ยนรหัสผ่านในการเข้าใช้ครั้งถัดไป
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">รีเซ็ตรหัสผ่าน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ลบผู้ใช้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="deleteUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>คำเตือน!</strong> การลบผู้ใช้จะไม่สามารถกู้คืนได้
                    </div>
                    
                    <p>คุณแน่ใจหรือไม่ที่จะลบผู้ใช้: <strong id="delete_username"></strong></p>
                    
                    <p class="text-muted">ข้อมูลทั้งหมดของผู้ใช้นี้จะถูกลบออกจากระบบอย่างถาวร</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">ลบผู้ใช้</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('รหัสผ่านไม่ตรงกัน');
        return false;
    }
});

// Add form submission debugging for edit user form
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const formData = new FormData(this);
    console.log('Edit user form submission:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
});

// Edit user function
function editUser(user) {
    console.log('editUser called with:', user);
    console.log('User ID:', user.id, 'Username:', user.username, 'Role:', user.role);
    console.log('Role type:', typeof user.role, 'Role length:', user.role ? user.role.length : 'undefined');
    
    // Validate user object
    if (!user || !user.id) {
        console.error('Invalid user object passed to editUser:', user);
        alert('เกิดข้อผิดพลาดในการโหลดข้อมูลผู้ใช้');
        return;
    }
    
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_full_name').value = user.display_name || '';
    document.getElementById('edit_is_approved').checked = user.approved == 1;
    
    // Show modal first
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
    
    // Set role value after a small delay to ensure dropdown is rendered
    setTimeout(function() {
        const roleSelect = document.getElementById('edit_role');
        console.log('Setting role to:', user.role);
        console.log('Available options:', Array.from(roleSelect.options).map(opt => opt.value));
        
        // Set role value with better validation
        if (user.role && roleSelect) {
            // First, try to find the exact role match
            let roleFound = false;
            for (let i = 0; i < roleSelect.options.length; i++) {
                if (roleSelect.options[i].value === user.role) {
                    roleSelect.selectedIndex = i;
                    roleFound = true;
                    console.log('Role set successfully to:', user.role);
                    break;
                }
            }
            
            // If role not found, log error but don't change to default
            if (!roleFound) {
                console.error('Role "' + user.role + '" not found in dropdown options');
                console.log('Available options:', Array.from(roleSelect.options).map(opt => opt.value));
                // Keep the current selection instead of defaulting to first option
            }
        } else {
            console.warn('No role specified or role select not found');
        }
    }, 100);
}

// Reset password function
function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('new_password').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
}

function deleteUser(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    modal.show();
}

// DataTable initialization
$(document).ready(function() {
    $('#usersTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Thai.json"
        },
        "pageLength": 25,
        "order": [[0, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": [6] }
        ]
    });
});

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strength = getPasswordStrength(password);
    
    // You can add visual feedback here
});

function getPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    return strength;
}
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

.table th {
    border-top: none;
    font-weight: 600;
    color: #5a5c69;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<!-- Include DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>