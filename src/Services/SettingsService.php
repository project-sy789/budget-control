<?php
/**
 * Settings Service - Handle system settings
 */

class SettingsService {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Get all system settings
     */
    public function getAllSettings() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM system_settings ORDER BY setting_key");
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row;
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log("Error getting settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a specific setting value
     */
    public function getSetting($key, $default = null) {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (PDOException $e) {
            error_log("Error getting setting {$key}: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Update a setting value
     */
    public function updateSetting($key, $value) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            error_log("Error updating setting {$key}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update multiple settings
     */
    public function updateSettings($settings) {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $this->updateSetting($key, $value);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating multiple settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get site configuration for display
     */
    public function getSiteConfig() {
        $settings = $this->getAllSettings();
        
        return [
            'site_name' => $settings['site_name']['setting_value'] ?? 'ระบบควบคุมงบประมาณ',
            'organization_name' => $settings['organization_name']['setting_value'] ?? 'โรงเรียนซับใหญ่วิทยาคม',
            'site_title' => $settings['site_title']['setting_value'] ?? 'ระบบควบคุมงบประมาณ - โรงเรียนซับใหญ่วิทยาคม',
            'site_icon' => $settings['site_icon']['setting_value'] ?? '',
            'enable_pwa' => ($settings['enable_pwa']['setting_value'] ?? '1') === '1',
            'year_label_type' => $settings['year_label_type']['setting_value'] ?? 'fiscal_year' // fiscal_year, academic_year, budget_year
        ];
    }
    
    /**
     * Handle file upload for site icon
     */
    public function uploadSiteIcon($file) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'ไม่พบไฟล์ที่อัปโหลด'];
        }
        
        $uploadDir = __DIR__ . '/../../public/uploads/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'ไม่สามารถสร้างโฟลเดอร์สำหรับอัปโหลดได้'];
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            return ['success' => false, 'message' => 'ไม่สามารถเขียนไฟล์ในโฟลเดอร์ uploads ได้'];
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง กรุณาอัปโหลดไฟล์รูปภาพ (JPEG, PNG, GIF, WebP, SVG)'];
        }
        
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'ขนาดไฟล์ใหญ่เกินไป (สูงสุด 2MB)'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'site-icon-' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old icon if exists
            $currentIcon = $this->getSetting('site_icon');
            if ($currentIcon && $currentIcon !== 'uploads/site-icon.svg') {
                $oldFilepath = __DIR__ . '/../../public/' . $currentIcon;
                if (file_exists($oldFilepath)) {
                    unlink($oldFilepath);
                }
            }
            
            // Update setting
            $relativePath = 'uploads/' . $filename;
            $this->updateSetting('site_icon', $relativePath);
            
            return ['success' => true, 'filename' => $relativePath];
        } else {
            $error = error_get_last();
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์: ' . ($error['message'] ?? 'ไม่ทราบสาเหตุ')];
        }
    }
    
    /**
     * Delete site icon
     */
    public function deleteSiteIcon() {
        $currentIcon = $this->getSetting('site_icon');
        
        if ($currentIcon) {
            // Don't delete the default SVG icon
            if ($currentIcon !== 'uploads/site-icon.svg') {
                $filepath = __DIR__ . '/../../public/' . $currentIcon;
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
            
            $this->updateSetting('site_icon', '');
        }
        
        return true;
    }
}