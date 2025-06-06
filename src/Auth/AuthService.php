<?php
/**
 * Authentication Service
 * Budget Control System v2
 */

require_once __DIR__ . '/../../config/database.php';

class AuthService {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Authenticate user with username and password
     */
    public function login($username, $password) {
        try {
            // Prepare SQL to get user by username or email
            $query = "SELECT id, username, email, password_hash, display_name, role, approved, department, position, last_login 
                     FROM users 
                     WHERE (username = :username OR email = :username) AND approved = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login time
                $this->updateLastLogin($user['id']);
                
                // Remove password hash from returned data
                unset($user['password_hash']);
                
                return [
                    'success' => true,
                    'user' => $user,
                    'message' => 'เข้าสู่ระบบสำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
                ];
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ'
            ];
        }
    }

    /**
     * Register new user
     */
    public function register($userData) {
        try {
            // Check if username or email already exists
            $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':username', $userData['username']);
            $checkStmt->bindParam(':email', $userData['email']);
            $checkStmt->execute();

            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว'
                ];
            }

            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);

            // Insert new user
            $query = "INSERT INTO users (username, email, password_hash, display_name, department, position, role, approved) 
                     VALUES (:username, :email, :password_hash, :display_name, :department, :position, :role, :approved)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $userData['username']);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':display_name', $userData['display_name']);
            $stmt->bindParam(':department', $userData['department']);
            $stmt->bindParam(':position', $userData['position']);
            $stmt->bindParam(':role', $userData['role']);
            $stmt->bindParam(':approved', $userData['approved']);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'สร้างบัญชีผู้ใช้สำเร็จ',
                    'user_id' => $this->conn->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถสร้างบัญชีผู้ใช้ได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการสร้างบัญชี'
            ];
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        try {
            $query = "SELECT id, username, email, display_name, role, approved, department, position, created_at, last_login 
                     FROM users WHERE id = :user_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login time
     */
    private function updateLastLogin($userId) {
        try {
            $query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $query = "SELECT password_hash FROM users WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'
                ];
            }

            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET password_hash = :password_hash WHERE id = :user_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':password_hash', $newPasswordHash);
            $updateStmt->bindParam(':user_id', $userId);

            if ($updateStmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถเปลี่ยนรหัสผ่านได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'
            ];
        }
    }

    /**
     * Get all users (admin only)
     */
    public function getAllUsers() {
        try {
            $query = "SELECT id, username, email, display_name, role, approved, department, position, created_at, last_login 
                     FROM users ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update user role and approval status (admin only)
     */
    public function updateUserStatus($userId, $role, $approved) {
        try {
            $query = "UPDATE users SET role = :role, approved = :approved WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':approved', $approved);
            $stmt->bindParam(':user_id', $userId);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'อัปเดตสถานะผู้ใช้สำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถอัปเดตสถานะผู้ใช้ได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Update user status error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการอัปเดตสถานะ'
            ];
        }
    }

    /**
     * Update user information (admin only)
     */
    public function updateUser($userId, $email, $displayName, $role, $approved) {
        try {
            $query = "UPDATE users SET email = :email, display_name = :display_name, role = :role, approved = :approved WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':display_name', $displayName);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':approved', $approved);
            $stmt->bindParam(':user_id', $userId);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'อัปเดตข้อมูลผู้ใช้สำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถอัปเดตข้อมูลผู้ใช้ได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล'
            ];
        }
    }

    /**
     * Delete user (admin only)
     */
    public function deleteUser($userId) {
        try {
            // Check if user exists
            $checkQuery = "SELECT id, username, role FROM users WHERE id = :user_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();
            
            $user = $checkStmt->fetch();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'ไม่พบผู้ใช้ที่ต้องการลบ'
                ];
            }
            
            // Prevent deletion of default admin user
            if ($user['username'] === 'admin') {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถลบผู้ดูแลระบบเริ่มต้นได้'
                ];
            }
            
            // Delete user
            $deleteQuery = "DELETE FROM users WHERE id = :user_id";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':user_id', $userId);
            
            if ($deleteStmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'ลบผู้ใช้สำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถลบผู้ใช้ได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการลบผู้ใช้'
            ];
        }
    }
}
?>