<?php
/**
 * Transaction Service
 * Budget Control System v2
 */

require_once __DIR__ . '/../../config/database.php';

class TransactionService {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Get all transactions with optional filters
     */
    public function getAllTransactions($projectId = null, $category = null, $limit = null, $offset = 0) {
        try {
            $query = "SELECT t.*, p.name as project_name, u.display_name as created_by_name,
                        tp_to.name as transfer_to_project_name,
                        tp_from.name as transfer_from_project_name,
                        t.budget_category as category_name,
                        CASE 
                            WHEN t.is_transfer = 1 THEN 'transfer'
                            WHEN t.amount > 0 THEN 'income'
                            ELSE 'expense'
                        END as type
                 FROM transactions t 
                 LEFT JOIN projects p ON t.project_id = p.id
                 LEFT JOIN projects tp_to ON t.transfer_to_project_id = tp_to.id
                 LEFT JOIN projects tp_from ON t.transfer_from_project_id = tp_from.id
                 LEFT JOIN users u ON t.created_by = u.id
                 WHERE 1=1";
            
            $params = [];
            
            if ($projectId) {
                $query .= " AND t.project_id = :project_id";
                $params[':project_id'] = $projectId;
            }
            
            if ($category && $category !== 'all') {
                $query .= " AND t.budget_category = :category";
                $params[':category'] = $category;
            }
            
            $query .= " ORDER BY t.date DESC, t.created_at DESC";
            
            if ($limit) {
                $query .= " LIMIT :limit OFFSET :offset";
                $params[':limit'] = $limit;
                $params[':offset'] = $offset;
            }
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':offset') {
                    $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get all transactions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get transaction by ID
     */
    public function getTransactionById($transactionId) {
        try {
            $query = "SELECT t.*, p.name as project_name, u.display_name as created_by_name,
                        tp_to.name as transfer_to_project_name,
                        tp_from.name as transfer_from_project_name,
                        t.budget_category as category_name,
                        CASE 
                            WHEN t.is_transfer = 1 THEN 'transfer'
                            WHEN t.amount > 0 THEN 'income'
                            ELSE 'expense'
                        END as type
                 FROM transactions t 
                 LEFT JOIN projects p ON t.project_id = p.id
                 LEFT JOIN projects tp_to ON t.transfer_to_project_id = tp_to.id
                 LEFT JOIN projects tp_from ON t.transfer_from_project_id = tp_from.id
                 LEFT JOIN users u ON t.created_by = u.id
                 WHERE t.id = :transaction_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':transaction_id', $transactionId);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get transaction by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new transaction
     */
    public function createTransaction($transactionData, $createdBy) {
        try {
            $query = "INSERT INTO transactions (project_id, date, description, amount, budget_category, note, created_by) 
                     VALUES (:project_id, :date, :description, :amount, :budget_category, :note, :created_by)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $transactionData['project_id']);
            $stmt->bindParam(':date', $transactionData['date']);
            $stmt->bindParam(':description', $transactionData['description']);
            $stmt->bindParam(':amount', $transactionData['amount']);
            $stmt->bindParam(':budget_category', $transactionData['budget_category']);
            $stmt->bindParam(':note', $transactionData['note']);
            $stmt->bindParam(':created_by', $createdBy);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'บันทึกรายการสำเร็จ',
                    'transaction_id' => $this->conn->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถบันทึกรายการได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Create transaction error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการบันทึกรายการ'
            ];
        }
    }

    /**
     * Update transaction
     */
    public function updateTransaction($transactionId, $transactionData) {
        try {
            $query = "UPDATE transactions SET 
                     project_id = :project_id,
                     date = :date,
                     description = :description,
                     amount = :amount,
                     budget_category = :budget_category,
                     note = :note
                     WHERE id = :transaction_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $transactionData['project_id']);
            $stmt->bindParam(':date', $transactionData['date']);
            $stmt->bindParam(':description', $transactionData['description']);
            $stmt->bindParam(':amount', $transactionData['amount']);
            $stmt->bindParam(':budget_category', $transactionData['budget_category']);
            $stmt->bindParam(':note', $transactionData['note']);
            $stmt->bindParam(':transaction_id', $transactionId);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'อัปเดตรายการสำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถอัปเดตรายการได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Update transaction error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการอัปเดตรายการ'
            ];
        }
    }

    /**
     * Delete transaction
     */
    public function deleteTransaction($transactionId) {
        try {
            $query = "DELETE FROM transactions WHERE id = :transaction_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':transaction_id', $transactionId);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'ลบรายการสำเร็จ'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถลบรายการได้'
                ];
            }
        } catch (Exception $e) {
            error_log("Delete transaction error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการลบรายการ'
            ];
        }
    }

    /**
     * Create budget transfer between projects
     */
    public function createBudgetTransfer($transferData, $createdBy) {
        try {
            $this->conn->beginTransaction();
            
            // Validate transfer amount
            if ($transferData['amount'] <= 0) {
                throw new Exception('จำนวนเงินที่โอนต้องมากกว่า 0');
            }
            
            // Check if source project has enough budget
            $sourceBalance = $this->getProjectCategoryBalance(
                $transferData['from_project_id'], 
                $transferData['from_category']
            );
            
            if ($sourceBalance < $transferData['amount']) {
                throw new Exception('งบประมาณในหมวดนี้ไม่เพียงพอสำหรับการโอน');
            }
            
            // Create outgoing transaction (negative amount)
            $outgoingData = [
                'project_id' => $transferData['from_project_id'],
                'date' => $transferData['date'],
                'description' => 'โอนงบประมาณไปยัง: ' . $transferData['to_project_name'],
                'amount' => -abs($transferData['amount']), // Negative for outgoing
                'budget_category' => $transferData['from_category'],
                'note' => $transferData['note'] ?? '',
                'is_transfer' => true,
                'is_transfer_in' => false,
                'transfer_to_project_id' => $transferData['to_project_id'],
                'transfer_to_category' => $transferData['to_category']
            ];
            
            $outgoingQuery = "INSERT INTO transactions 
                            (project_id, date, description, amount, budget_category, note, 
                             is_transfer, is_transfer_in, transfer_to_project_id, transfer_to_category, created_by) 
                            VALUES 
                            (:project_id, :date, :description, :amount, :budget_category, :note, 
                             :is_transfer, :is_transfer_in, :transfer_to_project_id, :transfer_to_category, :created_by)";
            
            $outgoingStmt = $this->conn->prepare($outgoingQuery);
            $outgoingStmt->bindParam(':project_id', $outgoingData['project_id']);
            $outgoingStmt->bindParam(':date', $outgoingData['date']);
            $outgoingStmt->bindParam(':description', $outgoingData['description']);
            $outgoingStmt->bindParam(':amount', $outgoingData['amount']);
            $outgoingStmt->bindParam(':budget_category', $outgoingData['budget_category']);
            $outgoingStmt->bindParam(':note', $outgoingData['note']);
            $outgoingStmt->bindParam(':is_transfer', $outgoingData['is_transfer']);
            $outgoingStmt->bindParam(':is_transfer_in', $outgoingData['is_transfer_in']);
            $outgoingStmt->bindParam(':transfer_to_project_id', $outgoingData['transfer_to_project_id']);
            $outgoingStmt->bindParam(':transfer_to_category', $outgoingData['transfer_to_category']);
            $outgoingStmt->bindParam(':created_by', $createdBy);
            
            if (!$outgoingStmt->execute()) {
                throw new Exception('ไม่สามารถสร้างรายการโอนออกได้');
            }
            
            // Create incoming transaction (positive amount)
            $incomingData = [
                'project_id' => $transferData['to_project_id'],
                'date' => $transferData['date'],
                'description' => 'รับโอนงบประมาณจาก: ' . $transferData['from_project_name'],
                'amount' => abs($transferData['amount']), // Positive for incoming
                'budget_category' => $transferData['to_category'],
                'note' => $transferData['note'] ?? '',
                'is_transfer' => true,
                'is_transfer_in' => true,
                'transfer_from_project_id' => $transferData['from_project_id'],
                'transfer_from_category' => $transferData['from_category']
            ];
            
            $incomingQuery = "INSERT INTO transactions 
                            (project_id, date, description, amount, budget_category, note, 
                             is_transfer, is_transfer_in, transfer_from_project_id, transfer_from_category, created_by) 
                            VALUES 
                            (:project_id, :date, :description, :amount, :budget_category, :note, 
                             :is_transfer, :is_transfer_in, :transfer_from_project_id, :transfer_from_category, :created_by)";
            
            $incomingStmt = $this->conn->prepare($incomingQuery);
            $incomingStmt->bindParam(':project_id', $incomingData['project_id']);
            $incomingStmt->bindParam(':date', $incomingData['date']);
            $incomingStmt->bindParam(':description', $incomingData['description']);
            $incomingStmt->bindParam(':amount', $incomingData['amount']);
            $incomingStmt->bindParam(':budget_category', $incomingData['budget_category']);
            $incomingStmt->bindParam(':note', $incomingData['note']);
            $incomingStmt->bindParam(':is_transfer', $incomingData['is_transfer']);
            $incomingStmt->bindParam(':is_transfer_in', $incomingData['is_transfer_in']);
            $incomingStmt->bindParam(':transfer_from_project_id', $incomingData['transfer_from_project_id']);
            $incomingStmt->bindParam(':transfer_from_category', $incomingData['transfer_from_category']);
            $incomingStmt->bindParam(':created_by', $createdBy);
            
            if (!$incomingStmt->execute()) {
                throw new Exception('ไม่สามารถสร้างรายการรับโอนได้');
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'โอนงบประมาณสำเร็จ'
            ];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Create budget transfer error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการโอนงบประมาณ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get project remaining budget (total budget - used budget)
     */
    private function getProjectRemainingBudget($projectId) {
        try {
            // Get total budget from budget categories
            $budgetQuery = "SELECT COALESCE(SUM(amount), 0) as total_budget 
                          FROM budget_categories 
                          WHERE project_id = :project_id";
            $budgetStmt = $this->conn->prepare($budgetQuery);
            $budgetStmt->bindParam(':project_id', $projectId);
            $budgetStmt->execute();
            $budgetResult = $budgetStmt->fetch();
            
            $totalBudget = $budgetResult ? $budgetResult['total_budget'] : 0;
            
            // Get total used budget from transactions
            $usedQuery = "SELECT COALESCE(SUM(amount), 0) as used_budget 
                         FROM transactions 
                         WHERE project_id = :project_id";
            $usedStmt = $this->conn->prepare($usedQuery);
            $usedStmt->bindParam(':project_id', $projectId);
            $usedStmt->execute();
            $usedResult = $usedStmt->fetch();
            
            $usedBudget = $usedResult ? $usedResult['used_budget'] : 0;
            
            return $totalBudget - $usedBudget;
        } catch (Exception $e) {
            error_log("Get project remaining budget error: " . $e->getMessage());
            return 0;
        }
    }



    /**
     * Get transaction summary statistics
     */
    public function getTransactionSummary($filters = []) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_transactions,
                        COUNT(CASE WHEN amount > 0 THEN 1 END) as income_transactions,
                        COUNT(CASE WHEN amount < 0 THEN 1 END) as expense_transactions,
                        COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_income,
                        COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_expense
                     FROM transactions";
            
            $params = [];
            $whereConditions = [];
            
            // Handle legacy single projectId parameter
            if (is_numeric($filters)) {
                $whereConditions[] = "project_id = :project_id";
                $params[':project_id'] = $filters;
            } else if (is_array($filters)) {
                // Handle filters array
                if (isset($filters['project_id']) && $filters['project_id'] > 0) {
                    $whereConditions[] = "project_id = :project_id";
                    $params[':project_id'] = $filters['project_id'];
                }
                
                if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                    $whereConditions[] = "date >= :date_from";
                    $params[':date_from'] = $filters['date_from'];
                }
                
                if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                    $whereConditions[] = "date <= :date_to";
                    $params[':date_to'] = $filters['date_to'];
                }
            }
            
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get transaction summary error: " . $e->getMessage());
            return [
                'total_transactions' => 0,
                'income_transactions' => 0,
                'expense_transactions' => 0,
                'total_income' => 0,
                'total_expense' => 0
            ];
        }
    }

    /**
     * Get transactions count for pagination
     */
    public function getTransactionsCount($projectId = null, $category = null) {
        try {
            $query = "SELECT COUNT(*) as count FROM transactions WHERE 1=1";
            $params = [];
            
            if ($projectId) {
                $query .= " AND project_id = :project_id";
                $params[':project_id'] = $projectId;
            }
            
            if ($category && $category !== 'all') {
                $query .= " AND budget_category = :category";
                $params[':category'] = $category;
            }
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'];
        } catch (Exception $e) {
            error_log("Get transactions count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Transfer budget between projects
     */
    public function transferBudget($fromProjectId, $toProjectId, $amount, $description, $transferDate, $userId) {
        try {
            $this->conn->beginTransaction();
            
            // Check if source project has sufficient budget
            $sourceBalance = $this->getProjectRemainingBudget($fromProjectId);
            if ($sourceBalance < $amount) {
                throw new Exception('งบประมาณคงเหลือไม่เพียงพอสำหรับการโอน');
            }
            
            // Create transfer out transaction for source project
            $transferOutQuery = "INSERT INTO transactions (project_id, budget_category, amount, description, date, created_by, is_transfer, transfer_to_project_id) 
                               VALUES (:project_id, 'SUBSIDY', :amount, :description, :date, :created_by, 1, :transfer_to_project_id)";
            
            $stmt = $this->conn->prepare($transferOutQuery);
            $stmt->bindParam(':project_id', $fromProjectId);
            $stmt->bindValue(':amount', -$amount); // Negative amount for outgoing transfer
            $stmt->bindValue(':description', 'โอนออก: ' . $description);
            $stmt->bindParam(':date', $transferDate);
            $stmt->bindParam(':created_by', $userId);
            $stmt->bindParam(':transfer_to_project_id', $toProjectId);
            $stmt->execute();
            
            // Create transfer in transaction for destination project
            $transferInQuery = "INSERT INTO transactions (project_id, budget_category, amount, description, date, created_by, is_transfer, is_transfer_in, transfer_from_project_id) 
                              VALUES (:project_id, 'SUBSIDY', :amount, :description, :date, :created_by, 1, 1, :transfer_from_project_id)";
            
            $stmt = $this->conn->prepare($transferInQuery);
            $stmt->bindParam(':project_id', $toProjectId);
            $stmt->bindValue(':amount', $amount); // Positive amount for incoming transfer
            $stmt->bindValue(':description', 'รับโอน: ' . $description);
            $stmt->bindParam(':date', $transferDate);
            $stmt->bindParam(':created_by', $userId);
            $stmt->bindParam(':transfer_from_project_id', $fromProjectId);
            $stmt->execute();
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Transfer budget error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Transfer budget between projects with specific categories
     */
    public function transferBudgetWithCategory($fromProjectId, $toProjectId, $fromCategory, $toCategory, $amount, $description, $transferDate, $userId) {
        try {
            $this->conn->beginTransaction();
            
            // Check if source project has sufficient budget in the specified category
            $sourceBalance = $this->getProjectCategoryBalance($fromProjectId, $fromCategory);
            if ($sourceBalance < $amount) {
                throw new Exception('งบประมาณในหมวด ' . $fromCategory . ' ไม่เพียงพอสำหรับการโอน');
            }
            
            // Get project names for descriptions
            $fromProjectName = $this->getProjectName($fromProjectId);
            $toProjectName = $this->getProjectName($toProjectId);
            
            // Create transfer out transaction for source project
            $transferOutQuery = "INSERT INTO transactions (project_id, budget_category, amount, description, date, created_by, is_transfer, transfer_to_project_id, transfer_to_category) 
                               VALUES (:project_id, :budget_category, :amount, :description, :date, :created_by, 1, :transfer_to_project_id, :transfer_to_category)";
            
            $stmt = $this->conn->prepare($transferOutQuery);
            $stmt->bindParam(':project_id', $fromProjectId);
            $stmt->bindParam(':budget_category', $fromCategory);
            $stmt->bindValue(':amount', -$amount); // Negative amount for outgoing transfer
            $stmt->bindValue(':description', 'โอนออก (' . $fromCategory . ' → ' . $toCategory . '): ' . $description);
            $stmt->bindParam(':date', $transferDate);
            $stmt->bindParam(':created_by', $userId);
            $stmt->bindParam(':transfer_to_project_id', $toProjectId);
            $stmt->bindParam(':transfer_to_category', $toCategory);
            $stmt->execute();
            
            // Create transfer in transaction for destination project
            $transferInQuery = "INSERT INTO transactions (project_id, budget_category, amount, description, date, created_by, is_transfer, is_transfer_in, transfer_from_project_id, transfer_from_category) 
                              VALUES (:project_id, :budget_category, :amount, :description, :date, :created_by, 1, 1, :transfer_from_project_id, :transfer_from_category)";
            
            $stmt = $this->conn->prepare($transferInQuery);
            $stmt->bindParam(':project_id', $toProjectId);
            $stmt->bindParam(':budget_category', $toCategory);
            $stmt->bindValue(':amount', $amount); // Positive amount for incoming transfer
            $stmt->bindValue(':description', 'รับโอน (' . $fromCategory . ' → ' . $toCategory . '): ' . $description);
            $stmt->bindParam(':date', $transferDate);
            $stmt->bindParam(':created_by', $userId);
            $stmt->bindParam(':transfer_from_project_id', $fromProjectId);
            $stmt->bindParam(':transfer_from_category', $fromCategory);
            $stmt->execute();
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Transfer budget with category error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get project category balance
     */
    public function getProjectCategoryBalance($projectId, $category) {
        try {
            $query = "SELECT COALESCE(SUM(amount), 0) as balance 
                     FROM transactions 
                     WHERE project_id = :project_id AND budget_category = :category";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->bindParam(':category', $category);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return floatval($result['balance']);
            
        } catch (Exception $e) {
            error_log("Get project category balance error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get project name by ID
     */
    private function getProjectName($projectId) {
        try {
            $query = "SELECT name FROM projects WHERE id = :project_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['name'] : 'ไม่ระบุ';
            
        } catch (Exception $e) {
            error_log("Get project name error: " . $e->getMessage());
            return 'ไม่ระบุ';
        }
    }

    /**
     * Get transfer history
     */
    public function getTransferHistory($limit = 10, $offset = 0) {
        try {
            $query = "SELECT t.*, p.name as project_name, u.display_name as created_by_name,
                            tp_to.name as transfer_to_project_name,
                            tp_from.name as transfer_from_project_name
                     FROM transactions t 
                     LEFT JOIN projects p ON t.project_id = p.id
                     LEFT JOIN projects tp_to ON t.transfer_to_project_id = tp_to.id
                     LEFT JOIN projects tp_from ON t.transfer_from_project_id = tp_from.id
                     LEFT JOIN users u ON t.created_by = u.id 
                     WHERE t.is_transfer = 1
                     ORDER BY t.date DESC, t.created_at DESC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get transfer history error: " . $e->getMessage());
            return [];
        }
    }
}
?>