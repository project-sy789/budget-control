<?php

class CategoryService {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all active category types
     */
    public function getAllCategories() {
        $sql = "SELECT * FROM category_types WHERE is_active = 1 ORDER BY category_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all active category types (alias for getAllCategories)
     */
    public function getAllActiveCategories() {
        return $this->getAllCategories();
    }
    
    /**
     * Get category by ID
     */
    public function getCategoryById($id) {
        $sql = "SELECT * FROM category_types WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get category by key
     */
    public function getCategoryByKey($key) {
        $sql = "SELECT * FROM category_types WHERE category_key = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new category
     */
    public function createCategory($data) {
        // Check if category key already exists
        if ($this->getCategoryByKey($data['category_key'])) {
            return false; // Key already exists
        }
        
        $sql = "INSERT INTO category_types (category_key, category_name, description, created_by) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['category_key'],
            $data['category_name'],
            $data['description'] ?? null,
            $data['created_by'] ?? null
        ]);
    }
    
    /**
     * Update category
     */
    public function updateCategory($id, $data) {
        // Check if new key conflicts with existing categories (excluding current one)
        if (isset($data['category_key'])) {
            $existing = $this->getCategoryByKey($data['category_key']);
            if ($existing && $existing['id'] != $id) {
                return false; // Key already exists
            }
        }
        
        $sql = "UPDATE category_types SET 
                category_key = COALESCE(?, category_key),
                category_name = COALESCE(?, category_name),
                description = COALESCE(?, description),
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['category_key'] ?? null,
            $data['category_name'] ?? null,
            $data['description'] ?? null,
            $id
        ]);
    }
    
    /**
     * Soft delete category (set is_active to false)
     */
    public function deleteCategory($id) {
        // Check if category is being used in any projects
        $sql = "SELECT COUNT(*) as count FROM budget_categories 
                WHERE category = (SELECT category_key FROM category_types WHERE id = ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return false; // Category is being used, cannot delete
        }
        
        $sql = "UPDATE category_types SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Restore deleted category
     */
    public function restoreCategory($id) {
        $sql = "UPDATE category_types SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Get categories as key-value array for dropdowns
     */
    public function getCategoriesForDropdown() {
        $categories = $this->getAllCategories();
        $result = [];
        foreach ($categories as $category) {
            $result[$category['category_key']] = $category['category_name'];
        }
        return $result;
    }
    
    /**
     * Check if category key is valid format
     */
    public function isValidCategoryKey($key) {
        return preg_match('/^[A-Z_]+$/', $key) && strlen($key) <= 50;
    }
    
    /**
     * Generate category key from name
     */
    public function generateCategoryKey($name) {
        // Convert Thai/English name to uppercase key
        $key = strtoupper(trim($name));
        $key = preg_replace('/[^A-Z0-9]/', '_', $key);
        $key = preg_replace('/_+/', '_', $key);
        $key = trim($key, '_');
        
        // Ensure uniqueness
        $originalKey = $key;
        $counter = 1;
        while ($this->getCategoryByKey($key)) {
            $key = $originalKey . '_' . $counter;
            $counter++;
        }
        
        return $key;
    }
}