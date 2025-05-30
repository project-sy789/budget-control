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
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $projectData['name']);
            $stmt->bindParam(':budget', $projectData['budget']);
            $stmt->bindParam(':work_group', $projectData['work_group']);
            $stmt->bindParam(':responsible_person', $projectData['responsible_person']);
            $stmt->bindParam(':description', $projectData['description']);
            $stmt->bindParam(':start_date', $projectData['start_date']);
            $stmt->bindParam(':end_date', $projectData['end_date']);
            $stmt->bindParam(':status', $projectData['status']);
            $stmt->bindParam(':created_by', $createdBy);
            
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
                // Delete existing categories
                $deleteQuery = "DELETE FROM budget_categories WHERE project_id = :project_id";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->bindParam(':project_id', $projectId);
                $deleteStmt->execute();
                
                // Insert new categories
                foreach ($projectData['budget_categories'] as $category) {
                    $this->addBudgetCategory($projectId, $category);
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
            $query = "SELECT * FROM budget_categories WHERE project_id = :project_id ORDER BY category";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            
            return $stmt->fetchAll();
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
            $query = "INSERT INTO budget_categories (project_id, category, amount, description) 
                     VALUES (:project_id, :category, :amount, :description)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->bindParam(':category', $categoryData['category']);
            $stmt->bindParam(':amount', $categoryData['amount']);
            $stmt->bindParam(':description', $categoryData['description']);
            
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
                        bc.category,
                        bc.amount as budget_amount,
                        COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0) as spent_amount,
                        (bc.amount - COALESCE(SUM(CASE WHEN t.amount > 0 THEN t.amount ELSE 0 END), 0)) as remaining_amount
                     FROM budget_categories bc
                     LEFT JOIN transactions t ON bc.project_id = t.project_id AND bc.category = t.budget_category
                     WHERE bc.project_id = :project_id
                     GROUP BY bc.category, bc.amount";
            
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