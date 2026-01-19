<?php
/**
 * Project Service
 * Budget Control System v2
 */

require_once __DIR__ . '/../../config/database.php';

class ProjectService {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Get all projects with optional filters
     */
    public function getAllProjects($workGroup = null, $status = null, $fiscalYearId = null) {
        try {
            $query = "SELECT p.*, u.display_name as created_by_name 
                     FROM projects p 
                     LEFT JOIN users u ON p.created_by = u.id 
                     WHERE 1=1";
            
            $params = [];
            
            if ($workGroup && $workGroup !== 'all') {
                $query .= " AND p.work_group = :work_group";
                $params[':work_group'] = $workGroup;
            }
            
            if ($status && $status !== 'all') {
                $query .= " AND p.status = :status";
                $params[':status'] = $status;
            }

            if ($fiscalYearId && $fiscalYearId !== 'all') {
                $query .= " AND p.fiscal_year_id = :fiscal_year_id";
                $params[':fiscal_year_id'] = $fiscalYearId;
            }
            
            $query .= " ORDER BY p.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $projects = $stmt->fetchAll();
            
            // Calculate budget usage for each project
            foreach ($projects as &$project) {
                $project['budget_categories'] = $this->getProjectBudgetCategories($project['id']);
                
                // Calculate initial total budget from budget categories using initial_amount
                $initialTotalBudget = 0;
                foreach ($project['budget_categories'] as $category) {
                    // Use initial_amount for total budget calculation, not the remaining amount
                    $initialTotalBudget += $category['initial_amount'];
                }
                
                // Include TransactionService for transfer calculation
                require_once __DIR__ . '/TransactionService.php';
                $transactionService = new TransactionService();
                
                // Calculate net transfers for this project
                // Note: transfer_out amounts are stored as negative values in DB
                $transferQuery = "SELECT 
                    COALESCE(SUM(CASE WHEN transaction_type = 'transfer_in' THEN amount ELSE 0 END), 0) as transfer_in,
                    COALESCE(SUM(CASE WHEN transaction_type = 'transfer_out' THEN amount ELSE 0 END), 0) as transfer_out
                    FROM transactions WHERE project_id = :project_id";
                
                $transferStmt = $this->conn->prepare($transferQuery);
                $transferStmt->bindParam(':project_id', $project['id']);
                $transferStmt->execute();
                $transferResult = $transferStmt->fetch(PDO::FETCH_ASSOC);
                
                $transferIn = floatval($transferResult['transfer_in']);
                $transferOut = floatval($transferResult['transfer_out']); // Already negative
                $netTransfer = $transferIn + $transferOut; // Add because transfer_out is already negative
                
                // Calculate actual total budget including net transfers
                $actualTotalBudget = $initialTotalBudget + $netTransfer;
                $project['total_budget'] = $actualTotalBudget;
                
                // Calculate used budget from transactions (expenses only)
                $usedBudget = $this->getProjectUsedBudget($project['id']);
                $project['used_budget'] = $usedBudget;
                
                // Calculate remaining budget
                $project['remaining_budget'] = $actualTotalBudget - $usedBudget;
            }
            
            return $projects;
        } catch (Exception $e) {
            error_log("Get all projects error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get project by ID
     */
    public function getProjectById($projectId) {
        try {
            $query = "SELECT p.*, u.display_name as created_by_name 
                     FROM projects p 
                     LEFT JOIN users u ON p.created_by = u.id 
                     LEFT JOIN fiscal_years fy ON p.fiscal_year_id = fy.id
                     WHERE p.id = :project_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            
            $project = $stmt->fetch();
            
            if ($project) {
                $project['budget_categories'] = $this->getProjectBudgetCategories($projectId);
                
                // Calculate initial total budget from budget categories
                $initialTotalBudget = 0;
                foreach ($project['budget_categories'] as $category) {
                    $initialTotalBudget += $category['initial_amount'];
                }
                
                // Include TransactionService for transfer calculation
                require_once __DIR__ . '/TransactionService.php';
                $transactionService = new TransactionService();
                
                // Calculate net transfers for this project
                // Note: transfer_out amounts are stored as negative values in DB
                $transferQuery = "SELECT 
                    COALESCE(SUM(CASE WHEN transaction_type = 'transfer_in' THEN amount ELSE 0 END), 0) as transfer_in,
                    COALESCE(SUM(CASE WHEN transaction_type = 'transfer_out' THEN amount ELSE 0 END), 0) as transfer_out
                    FROM transactions WHERE project_id = :project_id";
                
                $transferStmt = $this->conn->prepare($transferQuery);
                $transferStmt->bindParam(':project_id', $projectId);
                $transferStmt->execute();
                $transferResult = $transferStmt->fetch(PDO::FETCH_ASSOC);
                
                $transferIn = floatval($transferResult['transfer_in']);
                $transferOut = floatval($transferResult['transfer_out']); // Already negative
                $netTransfer = $transferIn + $transferOut; // Add because transfer_out is already negative
                
                // Calculate actual total budget including net transfers
                $actualTotalBudget = $initialTotalBudget + $netTransfer;
                $project['total_budget'] = $actualTotalBudget;
                
                // Calculate used budget from transactions (expenses only)
                $usedBudget = $this->getProjectUsedBudget($projectId);
                $project['used_budget'] = $usedBudget;
                
                // Calculate remaining budget
                $project['remaining_budget'] = $actualTotalBudget - $usedBudget;
            }
            
            return $project;
        } catch (Exception $e) {
            error_log("Get project by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new project
     */
    public function createProject($projectData, $createdBy) {
        try {
            // Validate input data
            $this->validateProjectData($projectData);
            
            $this->conn->beginTransaction();
            
            // Sanitize input data
            $sanitizedData = $this->sanitizeProjectData($projectData);
            
            // Insert project
            $query = "INSERT INTO projects (fiscal_year_id, name, budget, work_group, responsible_person, description, start_date, end_date, status, created_by) 
                     VALUES (:fiscal_year_id, :name, :budget, :work_group, :responsible_person, :description, :start_date, :end_date, :status, :created_by)";
            
            // Debug: Log the budget value being inserted
            error_log("ProjectService DEBUG - Budget value type: " . gettype($sanitizedData['budget']));
            error_log("ProjectService DEBUG - Budget value: " . var_export($sanitizedData['budget'], true));
            error_log("ProjectService DEBUG - Budget as float: " . floatval($sanitizedData['budget']));
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':fiscal_year_id', $sanitizedData['fiscal_year_id']);
            $stmt->bindParam(':name', $sanitizedData['name']);
            // Ensure budget is properly cast to float
            $budgetValue = floatval($sanitizedData['budget']);
            $stmt->bindParam(':budget', $budgetValue);
            $stmt->bindParam(':work_group', $sanitizedData['work_group']);
            $stmt->bindParam(':responsible_person', $sanitizedData['responsible_person']);
            $stmt->bindParam(':description', $sanitizedData['description']);
            $stmt->bindParam(':start_date', $sanitizedData['start_date']);
            $stmt->bindParam(':end_date', $sanitizedData['end_date']);
            $stmt->bindParam(':status', $sanitizedData['status']);
            $stmt->bindParam(':created_by', $createdBy);
            
            error_log("ProjectService DEBUG - About to execute INSERT with budget: " . $budgetValue);
            
            if (!$stmt->execute()) {
                throw new Exception('ไม่สามารถสร้างโครงการได้');
            }
            
            $projectId = $this->conn->lastInsertId();
            
            // Insert budget categories
            if (isset($projectData['budget_categories']) && is_array($projectData['budget_categories'])) {
                foreach ($projectData['budget_categories'] as $category) {
                    $this->addBudgetCategory($projectId, $category);
                }
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'สร้างโครงการสำเร็จ',
                'project_id' => $projectId
            ];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Create project error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการสร้างโครงการ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update project
     */
    public function updateProject($projectId, $projectData) {
        try {
            // Check if this is a status-only update
            $isStatusOnlyUpdate = (count($projectData) === 1 && isset($projectData['status']));
            
            if (!$isStatusOnlyUpdate) {
                // Validate input data for full update
                $this->validateProjectData($projectData, $projectId);
            } else {
                // For status-only update, just validate status
                if (empty($projectData['status'])) {
                    throw new Exception('กรุณาเลือกสถานะโครงการ');
                }
                $validStatuses = ['active', 'completed', 'suspended'];
                if (!in_array($projectData['status'], $validStatuses)) {
                    throw new Exception('สถานะโครงการไม่ถูกต้อง');
                }
            }
            
            // Sanitize input data
            $sanitizedData = $this->sanitizeProjectData($projectData);
            
            $this->conn->beginTransaction();
            
            // Update project
            if ($isStatusOnlyUpdate) {
                // Update only status
                $query = "UPDATE projects SET status = :status WHERE id = :project_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':status', $sanitizedData['status']);
                $stmt->bindParam(':project_id', $projectId);
            } else {
                // Update all fields
                $query = "UPDATE projects SET 
                         fiscal_year_id = :fiscal_year_id,
                         name = :name, 
                         budget = :budget, 
                         work_group = :work_group, 
                         responsible_person = :responsible_person, 
                         description = :description, 
                         start_date = :start_date, 
                         end_date = :end_date, 
                         status = :status 
                         WHERE id = :project_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':fiscal_year_id', $sanitizedData['fiscal_year_id']);
                $stmt->bindParam(':name', $sanitizedData['name']);
                $stmt->bindParam(':budget', $sanitizedData['budget']);
                $stmt->bindParam(':work_group', $sanitizedData['work_group']);
                $stmt->bindParam(':responsible_person', $sanitizedData['responsible_person']);
                $stmt->bindParam(':description', $sanitizedData['description']);
                $stmt->bindParam(':start_date', $sanitizedData['start_date']);
                $stmt->bindParam(':end_date', $sanitizedData['end_date']);
                $stmt->bindParam(':status', $sanitizedData['status']);
                $stmt->bindParam(':project_id', $projectId);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('ไม่สามารถอัปเดตโครงการได้');
            }
            
            // Update budget categories if provided (skip for status-only updates)
            if (!$isStatusOnlyUpdate && isset($projectData['budget_categories']) && is_array($projectData['budget_categories'])) {
                // First, delete all existing budget categories for this project
                // (only those without transactions to prevent data loss)
                $deleteQuery = "DELETE bc FROM budget_categories bc 
                               LEFT JOIN transactions t ON bc.project_id = t.project_id AND bc.category_type_id = t.category_type_id 
                               WHERE bc.project_id = :project_id AND t.id IS NULL";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->bindParam(':project_id', $projectId);
                $deleteStmt->execute();
                
                // Insert or update budget categories using ON DUPLICATE KEY UPDATE
                foreach ($projectData['budget_categories'] as $category) {
                    $categoryKey = $category['category'];
                    $amount = floatval($category['amount']);
                    
                    // Get category_type_id from category_key
                    $categoryQuery = "SELECT id FROM category_types WHERE category_key = :category_key";
                    $categoryStmt = $this->conn->prepare($categoryQuery);
                    $categoryStmt->bindParam(':category_key', $categoryKey);
                    $categoryStmt->execute();
                    $categoryResult = $categoryStmt->fetch();
                    
                    if (!$categoryResult) {
                        throw new Exception('ไม่พบหมวดหมู่งบประมาณ: ' . $categoryKey);
                    }
                    
                    $categoryTypeId = $categoryResult['id'];
                    
                    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing categories
                    $upsertQuery = "INSERT INTO budget_categories (project_id, category_type_id, budget_amount) 
                                   VALUES (:project_id, :category_type_id, :budget_amount)
                                   ON DUPLICATE KEY UPDATE 
                                   budget_amount = VALUES(budget_amount)";
                    
                    $upsertStmt = $this->conn->prepare($upsertQuery);
                    $upsertStmt->bindParam(':project_id', $projectId);
                    $upsertStmt->bindParam(':category_type_id', $categoryTypeId);
                    $upsertStmt->bindParam(':budget_amount', $amount);
                    
                    if (!$upsertStmt->execute()) {
                        throw new Exception('ไม่สามารถอัปเดตหมวดหมู่งบประมาณได้: ' . $categoryKey);
                    }
                }
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'อัปเดตโครงการสำเร็จ'
            ];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Update project error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการอัปเดตโครงการ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete project
     */
    public function deleteProject($projectId) {
        try {
            // Check if project has transactions
            $checkQuery = "SELECT COUNT(*) as count FROM transactions WHERE project_id = :project_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':project_id', $projectId);
            $checkStmt->execute();
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถลบโครงการที่มีรายการธุรกรรมได้'
                ];
            }
            
            // Delete project (budget_categories will be deleted by CASCADE)
            $query = "DELETE FROM projects WHERE id = :project_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'ลบโครงการสำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถลบโครงการได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Delete project error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการลบโครงการ'
            ];
        }
    }

    /**
     * Get project budget categories
     */
    public function getProjectBudgetCategories($projectId) {
        try {
            // Get project-specific budget categories with amounts
            $query = "SELECT bc.*, ct.category_key, ct.category_name 
                     FROM budget_categories bc 
                     LEFT JOIN category_types ct ON bc.category_type_id = ct.id 
                     WHERE bc.project_id = :project_id 
                     ORDER BY ct.category_key";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            $projectCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no project-specific categories, get all active categories with 0 amounts
            if (empty($projectCategories)) {
                require_once __DIR__ . '/CategoryService.php';
                $categoryService = new CategoryService($this->conn);
                $allCategories = $categoryService->getAllActiveCategories();
                
                $formattedCategories = [];
                foreach ($allCategories as $category) {
                    $formattedCategories[] = [
                        'id' => $category['id'],
                        'category_type_id' => $category['id'], // Use category_types.id as category_type_id
                        'category' => $category['category_key'],
                        'category_name' => $category['category_name'],
                        'amount' => 0
                    ];
                }
                return $formattedCategories;
            }
            
            // Include TransactionService for balance calculation
            require_once __DIR__ . '/TransactionService.php';
            $transactionService = new TransactionService();
            
            // Format project categories for JavaScript consumption
            $formattedCategories = [];
            foreach ($projectCategories as $category) {
                $initialAmount = floatval($category['budget_amount']) ?: 0;
                
                // Calculate remaining balance for this category
                $remainingBalance = $transactionService->getProjectCategoryBalance($projectId, $category['category_key']);
                
                $formattedCategories[] = [
                    'id' => $category['id'],
                    'category_type_id' => $category['category_type_id'], // Add category_type_id for transactions
                    'category' => $category['category_key'],
                    'category_name' => $category['category_name'] ?: $category['category_key'],
                    'amount' => $initialAmount, // Use initial budget amount for budget summary
                    'remaining_balance' => $remainingBalance, // Keep remaining balance for reference
                    'initial_amount' => $initialAmount // Keep initial amount for reference
                ];
            }
            
            return $formattedCategories;
        } catch (Exception $e) {
            error_log("Get project budget categories error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add budget category to project
     */
    private function addBudgetCategory($projectId, $categoryData) {
        try {
            // First, get the category_type_id from category_key
            $categoryQuery = "SELECT id FROM category_types WHERE category_key = :category_key";
            $categoryStmt = $this->conn->prepare($categoryQuery);
            $categoryStmt->bindParam(':category_key', $categoryData['category']);
            $categoryStmt->execute();
            $categoryResult = $categoryStmt->fetch();
            
            if (!$categoryResult) {
                throw new Exception('ไม่พบหมวดหมู่งบประมาณ: ' . $categoryData['category']);
            }
            
            $categoryTypeId = $categoryResult['id'];
            
            $query = "INSERT INTO budget_categories (project_id, category_type_id, budget_amount) 
                     VALUES (:project_id, :category_type_id, :budget_amount)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->bindParam(':category_type_id', $categoryTypeId);
            $stmt->bindParam(':budget_amount', $categoryData['amount']);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Add budget category error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get project balance by category
     */
    public function getProjectBalance($projectId) {
        try {
            $query = "SELECT 
                        ct.category_key as category,
                        bc.budget_amount,
                        COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) as spent_amount,
                        (bc.budget_amount - COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0)) as remaining_amount
                     FROM budget_categories bc
                     JOIN category_types ct ON bc.category_type_id = ct.id
                     LEFT JOIN transactions t ON bc.project_id = t.project_id AND bc.category_type_id = t.category_type_id
                     WHERE bc.project_id = :project_id
                     GROUP BY ct.category_key, bc.budget_amount";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get project balance error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get used budget for a project
     */
    private function getProjectUsedBudget($projectId) {
        try {
            $query = "SELECT COALESCE(SUM(amount), 0) as used_budget 
                     FROM transactions 
                     WHERE project_id = :project_id 
                     AND transaction_type NOT IN ('transfer_in', 'transfer_out')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ? $result['used_budget'] : 0;
        } catch (Exception $e) {
            error_log("Get project used budget error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get project summary statistics
     */
    public function getProjectSummary($fiscalYearId = null) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_projects,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_projects,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_projects,
                        SUM(budget) as total_budget
                     FROM projects";
            
            $params = [];
            if ($fiscalYearId && $fiscalYearId !== 'all') {
                $query .= " WHERE fiscal_year_id = :fiscal_year_id";
                $params[':fiscal_year_id'] = $fiscalYearId;
            }
            
            $stmt = $this->conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get project summary error: " . $e->getMessage());
            return [
                'total_projects' => 0,
                'active_projects' => 0,
                'completed_projects' => 0,
                'total_budget' => 0
            ];
        }
    }

    /**
     * Validate project data
     */
    private function validateProjectData($projectData, $projectId = null) {
        // Required fields validation
        $requiredFields = ['name', 'work_group', 'responsible_person', 'start_date', 'end_date', 'status'];
        
        foreach ($requiredFields as $field) {
            if (empty($projectData[$field])) {
                throw new Exception("กรุณากรอก{$this->getFieldLabel($field)}");
            }
        }
        
        // Length validation
        if (strlen($projectData['name']) > 255) {
            throw new Exception('ชื่อโครงการต้องไม่เกิน 255 ตัวอักษร');
        }
        
        if (strlen($projectData['work_group']) > 100) {
            throw new Exception('หน่วยงานต้องไม่เกิน 100 ตัวอักษร');
        }
        
        if (strlen($projectData['responsible_person']) > 100) {
            throw new Exception('ผู้รับผิดชอบต้องไม่เกิน 100 ตัวอักษร');
        }
        
        if (!empty($projectData['description']) && strlen($projectData['description']) > 1000) {
            throw new Exception('คำอธิบายต้องไม่เกิน 1000 ตัวอักษร');
        }

        // Fiscal Year validation
        if (empty($projectData['fiscal_year_id'])) {
            throw new Exception('กรุณาเลือกปี');
        }
        
        // Date validation
        if (strtotime($projectData['start_date']) === false) {
            throw new Exception('รูปแบบวันที่เริ่มต้นไม่ถูกต้อง');
        }
        
        if (strtotime($projectData['end_date']) === false) {
            throw new Exception('รูปแบบวันที่สิ้นสุดไม่ถูกต้อง');
        }
        
        if (strtotime($projectData['start_date']) >= strtotime($projectData['end_date'])) {
            throw new Exception('วันที่เริ่มต้นต้องน้อยกว่าวันที่สิ้นสุด');
        }
        
        // Status validation
        $validStatuses = ['active', 'completed', 'suspended'];
        if (!in_array($projectData['status'], $validStatuses)) {
            throw new Exception('สถานะโครงการไม่ถูกต้อง');
        }
        
        // Budget validation
        if (isset($projectData['budget']) && (!is_numeric($projectData['budget']) || $projectData['budget'] < 0)) {
            throw new Exception('งบประมาณต้องเป็นตัวเลขที่มากกว่าหรือเท่ากับ 0');
        }
        
        // Check for duplicate project name (excluding current project if updating)
        $checkQuery = "SELECT id FROM projects WHERE name = :name";
        $params = [':name' => $projectData['name']];
        
        if ($projectId) {
            $checkQuery .= " AND id != :project_id";
            $params[':project_id'] = $projectId;
        }
        
        $checkStmt = $this->conn->prepare($checkQuery);
        foreach ($params as $key => $value) {
            $checkStmt->bindValue($key, $value);
        }
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            throw new Exception('ชื่อโครงการนี้มีอยู่แล้วในระบบ');
        }
    }
    
    /**
     * Sanitize project data
     */
    private function sanitizeProjectData($projectData) {
        $sanitized = [];
        
        // Sanitize string fields
        $stringFields = ['name', 'work_group', 'responsible_person', 'description'];
        foreach ($stringFields as $field) {
            if (isset($projectData[$field])) {
                $sanitized[$field] = trim(strip_tags($projectData[$field]));
            }
        }
        
        // Sanitize date fields
        $dateFields = ['start_date', 'end_date'];
        foreach ($dateFields as $field) {
            if (isset($projectData[$field])) {
                $sanitized[$field] = date('Y-m-d', strtotime($projectData[$field]));
            }
        }
        
        // Sanitize numeric fields
        if (isset($projectData['budget'])) {
            $sanitized['budget'] = floatval($projectData['budget']);
        }
        
        // Sanitize status
        if (isset($projectData['status'])) {
            $sanitized['status'] = trim($projectData['status']);
        }

        // Sanitize fiscal_year_id
        if (isset($projectData['fiscal_year_id'])) {
            $sanitized['fiscal_year_id'] = intval($projectData['fiscal_year_id']);
        }
        
        // Sanitize created_by
        if (isset($projectData['created_by'])) {
            $sanitized['created_by'] = intval($projectData['created_by']);
        }
        
        return $sanitized;
    }
    
    /**
     * Get field label for validation messages
     */
    private function getFieldLabel($field) {
        $labels = [
            'name' => 'ชื่อโครงการ',
            'work_group' => 'หน่วยงาน',
            'responsible_person' => 'ผู้รับผิดชอบ',
            'start_date' => 'วันที่เริ่มต้น',
            'end_date' => 'วันที่สิ้นสุด',
            'status' => 'สถานะ'
        ];
        
        return $labels[$field] ?? $field;
    }
}
?>