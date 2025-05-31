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
    public function getAllProjects($workGroup = null, $status = null) {
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
                
                // Calculate total budget from budget categories
                $totalBudget = 0;
                foreach ($project['budget_categories'] as $category) {
                    $totalBudget += $category['amount'];
                }
                $project['total_budget'] = $totalBudget;
                
                // Calculate used budget from transactions
                $usedBudget = $this->getProjectUsedBudget($project['id']);
                $project['used_budget'] = $usedBudget;
                
                // Calculate remaining budget
                $project['remaining_budget'] = $totalBudget - $usedBudget;
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
                     WHERE p.id = :project_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            
            $project = $stmt->fetch();
            
            if ($project) {
                $project['budget_categories'] = $this->getProjectBudgetCategories($projectId);
                
                // Calculate total budget from budget categories
                $totalBudget = 0;
                foreach ($project['budget_categories'] as $category) {
                    $totalBudget += $category['amount'];
                }
                $project['total_budget'] = $totalBudget;
                
                // Calculate used budget from transactions
                $usedBudget = $this->getProjectUsedBudget($projectId);
                $project['used_budget'] = $usedBudget;
                
                // Calculate remaining budget
                $project['remaining_budget'] = $totalBudget - $usedBudget;
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
            $this->conn->beginTransaction();
            
            // Insert project
            $query = "INSERT INTO projects (name, budget, work_group, responsible_person, description, start_date, end_date, status, created_by) 
                     VALUES (:name, :budget, :work_group, :responsible_person, :description, :start_date, :end_date, :status, :created_by)";
            
            // Debug: Log the budget value being inserted
            error_log("ProjectService DEBUG - Budget value type: " . gettype($projectData['budget']));
            error_log("ProjectService DEBUG - Budget value: " . var_export($projectData['budget'], true));
            error_log("ProjectService DEBUG - Budget as float: " . floatval($projectData['budget']));
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $projectData['name']);
            // Ensure budget is properly cast to float
            $budgetValue = floatval($projectData['budget']);
            $stmt->bindParam(':budget', $budgetValue);
            $stmt->bindParam(':work_group', $projectData['work_group']);
            $stmt->bindParam(':responsible_person', $projectData['responsible_person']);
            $stmt->bindParam(':description', $projectData['description']);
            $stmt->bindParam(':start_date', $projectData['start_date']);
            $stmt->bindParam(':end_date', $projectData['end_date']);
            $stmt->bindParam(':status', $projectData['status']);
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
            $this->conn->beginTransaction();
            
            // Update project
            $query = "UPDATE projects SET 
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
            $stmt->bindParam(':name', $projectData['name']);
            $stmt->bindParam(':budget', $projectData['budget']);
            $stmt->bindParam(':work_group', $projectData['work_group']);
            $stmt->bindParam(':responsible_person', $projectData['responsible_person']);
            $stmt->bindParam(':description', $projectData['description']);
            $stmt->bindParam(':start_date', $projectData['start_date']);
            $stmt->bindParam(':end_date', $projectData['end_date']);
            $stmt->bindParam(':status', $projectData['status']);
            $stmt->bindParam(':project_id', $projectId);
            
            if (!$stmt->execute()) {
                throw new Exception('ไม่สามารถอัปเดตโครงการได้');
            }
            
            // Update budget categories if provided
            if (isset($projectData['budget_categories']) && is_array($projectData['budget_categories'])) {
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
                        'category' => $category['category_key'],
                        'category_name' => $category['category_name'],
                        'amount' => 0
                    ];
                }
                return $formattedCategories;
            }
            
            // Format project categories for JavaScript consumption
            $formattedCategories = [];
            foreach ($projectCategories as $category) {
                $formattedCategories[] = [
                    'id' => $category['id'],
                    'category' => $category['category_key'],
                    'category_name' => $category['category_name'] ?: $category['category_key'],
                    'amount' => floatval($category['budget_amount']) ?: 0
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
                     WHERE project_id = :project_id";
            
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
    public function getProjectSummary() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_projects,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_projects,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_projects,
                        SUM(budget) as total_budget
                     FROM projects";
            
            $stmt = $this->conn->prepare($query);
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
}
?>