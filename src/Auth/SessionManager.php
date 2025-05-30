<?php
/**
 * Session Manager
 * Budget Control System v2
 */

require_once __DIR__ . '/../../config/database.php';

class SessionManager {
    private $db;
    private $conn;
    private $sessionLifetime = 86400; // 24 hours

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        
        // Configure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Create new session for user
     */
    public function createSession($userId) {
        try {
            // Generate session ID
            session_regenerate_id(true);
            $sessionId = session_id();
            
            // Get client info
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Calculate expiry time
            $expiresAt = date('Y-m-d H:i:s', time() + $this->sessionLifetime);
            
            // Clean up old sessions for this user
            $this->cleanupUserSessions($userId);
            
            // Insert new session
            $query = "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) 
                     VALUES (:session_id, :user_id, :ip_address, :user_agent, :expires_at)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->bindParam(':expires_at', $expiresAt);
            
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $userId;
                $_SESSION['session_id'] = $sessionId;
                $_SESSION['login_time'] = time();
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Create session error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate current session
     */
    public function validateSession() {
        try {
            if (!isset($_SESSION['session_id']) || !isset($_SESSION['user_id'])) {
                return false;
            }
            
            $sessionId = $_SESSION['session_id'];
            $userId = $_SESSION['user_id'];
            
            // Check session in database
            $query = "SELECT user_id, expires_at FROM user_sessions 
                     WHERE id = :session_id AND user_id = :user_id AND expires_at > NOW()";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $session = $stmt->fetch();
            
            if ($session) {
                // Update session expiry
                $this->extendSession($sessionId);
                return true;
            } else {
                // Invalid or expired session
                $this->destroySession();
                return false;
            }
        } catch (Exception $e) {
            error_log("Validate session error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extend session expiry time
     */
    private function extendSession($sessionId) {
        try {
            $newExpiresAt = date('Y-m-d H:i:s', time() + $this->sessionLifetime);
            
            $query = "UPDATE user_sessions SET expires_at = :expires_at WHERE id = :session_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':expires_at', $newExpiresAt);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Extend session error: " . $e->getMessage());
        }
    }

    /**
     * Destroy current session
     */
    public function destroySession() {
        try {
            if (isset($_SESSION['session_id'])) {
                // Remove from database
                $query = "DELETE FROM user_sessions WHERE id = :session_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':session_id', $_SESSION['session_id']);
                $stmt->execute();
            }
            
            // Clear session data
            $_SESSION = [];
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
            
            return true;
        } catch (Exception $e) {
            error_log("Destroy session error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $query = "DELETE FROM user_sessions WHERE expires_at < NOW()";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Cleanup expired sessions error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean up old sessions for specific user (keep only latest 5)
     */
    private function cleanupUserSessions($userId) {
        try {
            $query = "DELETE FROM user_sessions 
                     WHERE user_id = :user_id 
                     AND id NOT IN (
                         SELECT id FROM (
                             SELECT id FROM user_sessions 
                             WHERE user_id = :user_id2 
                             ORDER BY created_at DESC 
                             LIMIT 5
                         ) AS recent_sessions
                     )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':user_id2', $userId);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Cleanup user sessions error: " . $e->getMessage());
        }
    }

    /**
     * Get current user ID from session
     */
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->validateSession();
    }

    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get active sessions for user
     */
    public function getUserActiveSessions($userId) {
        try {
            $query = "SELECT id, ip_address, user_agent, created_at, expires_at 
                     FROM user_sessions 
                     WHERE user_id = :user_id AND expires_at > NOW() 
                     ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get user active sessions error: " . $e->getMessage());
            return [];
        }
    }
}
?>