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
    public function getAllTransactions($projectId = null, $category = null, $limit = null, $offset = 0, $filters = []) {
        try {
            $query = "SELECT t.*, p.name as project_name, u.display_name as created_by_name,
                        ct.category_name,
                        t.transaction_type as type
                 FROM transactions t 
                 LEFT JOIN projects p ON t.project_id = p.id
                 LEFT JOIN category_types ct ON t.category_type_id = ct.id
                 LEFT JOIN users u ON t.created_by = u.id
                 WHERE 1=1";
            
            $params = [];
            
            if ($projectId) {
                $query .= " AND t.project_id = :project_id";
                $params[':project_id'] = $projectId;
            }
            
            if ($category && $category !== 'all') {
                $query .= " AND ct.category_key = :category";
                $params[':category'] = $category;
            }
            
            // Handle additional filters
            if (!empty($filters)) {
                if (isset($filters['type']) && !empty($filters['type'])) {
                    if ($filters['type'] === 'transfer') {
                        $query .= " AND t.transaction_type IN ('transfer_in', 'transfer_out')";
                    } else {
                        $query .= " AND t.transaction_type = :type_filter";
                        $params[':type_filter'] = $filters['type'];
                    }
                }
                
                if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                    $query .= " AND t.transaction_date >= :date_from";
                    $params[':date_from'] = $filters['date_from'];
                }
                
                if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                    $query .= " AND t.transaction_date <= :date_to";
                    $params[':date_to'] = $filters['date_to'];
                }
            }
            
            $query .= " ORDER BY t.transaction_date DESC, t.created_at DESC";
            
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
                        ct.category_name,
                        t.transaction_type as type
                 FROM transactions t 
                 LEFT JOIN projects p ON t.project_id = p.id
                 LEFT JOIN category_types ct ON t.category_type_id = ct.id
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
            $query = "INSERT INTO transactions (project_id, category_type_id, amount, transaction_type, description, transaction_date, reference_number, created_by) 
                     VALUES (:project_id, :category_type_id, :amount, :transaction_type, :description, :transaction_date, :reference_number, :created_by)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $transactionData['project_id']);
            $stmt->bindParam(':category_type_id', $transactionData['category_type_id']);
            $stmt->bindParam(':amount', $transactionData['amount']);
            $stmt->bindParam(':transaction_type', $transactionData['transaction_type']);
            $stmt->bindParam(':description', $transactionData['description']);
            $stmt->bindParam(':transaction_date', $transactionData['transaction_date']);
            $stmt->bindParam(':reference_number', $transactionData['reference_number']);
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
            // First, get the current transaction data to compare changes
            $currentTransaction = $this->getTransactionById($transactionId);
            if (!$currentTransaction) {
                return [
                    'success' => false,
                    'message' => 'ไม่พบรายการที่ต้องการอัปเดต'
                ];
            }
            
            // Update the transaction
            $query = "UPDATE transactions SET 
                     project_id = :project_id,
                     category_type_id = :category_type_id,
                     amount = :amount,
                     transaction_type = :transaction_type,
                     description = :description,
                     transaction_date = :transaction_date,
                     reference_number = :reference_number
                     WHERE id = :transaction_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $transactionData['project_id']);
            $stmt->bindParam(':category_type_id', $transactionData['category_type_id']);
            $stmt->bindParam(':amount', $transactionData['amount']);
            $stmt->bindParam(':transaction_type', $transactionData['transaction_type']);
            $stmt->bindParam(':description', $transactionData['description']);
            $stmt->bindParam(':transaction_date', $transactionData['transaction_date']);
            $stmt->bindParam(':reference_number', $transactionData['reference_number']);
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
            $this->conn->beginTransaction();
            
            // First, get the transaction details to check if it's a transfer
            $getQuery = "SELECT * FROM transactions WHERE id = :transaction_id";
            $getStmt = $this->conn->prepare($getQuery);
            $getStmt->bindParam(':transaction_id', $transactionId);
            $getStmt->execute();
            $transaction = $getStmt->fetch();
            
            if (!$transaction) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'message' => 'ไม่พบรายการที่ต้องการลบ'
                ];
            }
            
            // If it's a transfer transaction, delete both transfer_in and transfer_out records
            if ($transaction['transaction_type'] === 'transfer_in' || $transaction['transaction_type'] === 'transfer_out') {
                $deletedCount = 0;
                
                if ($transaction['transaction_type'] === 'transfer_out') {
                    // For transfer_out, find the corresponding transfer_in using transfer_to_project_id
                    $deleteQuery = "DELETE FROM transactions WHERE 
                        (id = :transaction_id) OR 
                        (transaction_type = 'transfer_in' AND 
                         transfer_from_project_id = :project_id AND 
                         project_id = :transfer_to_project_id AND 
                         amount = :amount AND 
                         transaction_date = :transaction_date)";
                    
                    $deleteStmt = $this->conn->prepare($deleteQuery);
                    $deleteStmt->bindParam(':transaction_id', $transactionId);
                    $deleteStmt->bindParam(':project_id', $transaction['project_id']);
                    $deleteStmt->bindParam(':transfer_to_project_id', $transaction['transfer_to_project_id']);
                    $deleteStmt->bindValue(':amount', abs($transaction['amount'])); // Convert negative to positive for transfer_in
                    $deleteStmt->bindParam(':transaction_date', $transaction['transaction_date']);
                    
                } else {
                    // For transfer_in, find the corresponding transfer_out using transfer_from_project_id
                    $deleteQuery = "DELETE FROM transactions WHERE 
                        (id = :transaction_id) OR 
                        (transaction_type = 'transfer_out' AND 
                         transfer_to_project_id = :project_id AND 
                         project_id = :transfer_from_project_id AND 
                         amount = :amount AND 
                         transaction_date = :transaction_date)";
                    
                    $deleteStmt = $this->conn->prepare($deleteQuery);
                    $deleteStmt->bindParam(':transaction_id', $transactionId);
                    $deleteStmt->bindParam(':project_id', $transaction['project_id']);
                    $deleteStmt->bindParam(':transfer_from_project_id', $transaction['transfer_from_project_id']);
                    $deleteStmt->bindValue(':amount', -$transaction['amount']); // Convert positive to negative for transfer_out
                    $deleteStmt->bindParam(':transaction_date', $transaction['transaction_date']);
                }
                
                if (!$deleteStmt->execute()) {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'message' => 'ไม่สามารถลบรายการโอนได้'
                    ];
                }
                
                $deletedCount = $deleteStmt->rowCount();
                
                $this->conn->commit();
                return [
                    'success' => true,
                    'message' => 'ลบรายการโอนสำเร็จ (ลบ ' . $deletedCount . ' รายการ)'
                ];
            } else {
                // For regular transactions, delete only the specific record
                $deleteQuery = "DELETE FROM transactions WHERE id = :transaction_id";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->bindParam(':transaction_id', $transactionId);
                
                if ($deleteStmt->execute()) {
                    $this->conn->commit();
                    return [
                        'success' => true,
                        'message' => 'ลบรายการสำเร็จ'
                    ];
                } else {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'message' => 'ไม่สามารถลบรายการได้'
                    ];
                }
            }
        } catch (Exception $e) {
            $this->conn->rollback();
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
            // Use getProjectCategoryBalanceForSummary to match the balance displayed in UI (includes transfers)
            $sourceBalance = $this->getProjectCategoryBalanceForSummary(
                $transferData['from_project_id'], 
                $transferData['from_category']
            );
            
            if ($sourceBalance < $transferData['amount']) {
                throw new Exception('งบประมาณในหมวดนี้ไม่เพียงพอสำหรับการโอน');
            }
            
            // Create outgoing transaction (negative amount)
            $outgoingData = [
                'project_id' => $transferData['from_project_id'],
                'transaction_date' => $transferData['date'],
                'description' => 'โอนงบประมาณไปยัง: ' . $transferData['to_project_name'],
                'amount' => -abs($transferData['amount']), // Negative for outgoing
                'category_type_id' => $transferData['from_category'],
                'reference_number' => $transferData['note'] ?? '',
                'is_transfer' => true,
                'is_transfer_in' => false,
                'transfer_to_project_id' => $transferData['to_project_id'],
                'transfer_to_category' => $transferData['to_category']
            ];
            
            $outgoingQuery = "INSERT INTO transactions 
                            (project_id, transaction_date, description, amount, category_type_id, reference_number, 
                             is_transfer, is_transfer_in, transfer_to_project_id, transfer_to_category, created_by) 
                            VALUES 
                            (:project_id, :transaction_date, :description, :amount, :category_type_id, :reference_number, 
                             :is_transfer, :is_transfer_in, :transfer_to_project_id, :transfer_to_category, :created_by)";
            
            $outgoingStmt = $this->conn->prepare($outgoingQuery);
            $outgoingStmt->bindParam(':project_id', $outgoingData['project_id']);
            $outgoingStmt->bindParam(':transaction_date', $outgoingData['transaction_date']);
            $outgoingStmt->bindParam(':description', $outgoingData['description']);
            $outgoingStmt->bindParam(':amount', $outgoingData['amount']);
            $outgoingStmt->bindParam(':category_type_id', $outgoingData['category_type_id']);
            $outgoingStmt->bindParam(':reference_number', $outgoingData['reference_number']);
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
                'transaction_date' => $transferData['date'],
                'description' => 'รับโอนงบประมาณจาก: ' . $transferData['from_project_name'],
                'amount' => abs($transferData['amount']), // Positive for incoming
                'category_type_id' => $transferData['to_category'],
                'reference_number' => $transferData['note'] ?? '',
                'is_transfer' => true,
                'is_transfer_in' => true,
                'transfer_from_project_id' => $transferData['from_project_id'],
                'transfer_from_category' => $transferData['from_category']
            ];
            
            $incomingQuery = "INSERT INTO transactions 
                            (project_id, transaction_date, description, amount, category_type_id, reference_number, 
                             is_transfer, is_transfer_in, transfer_from_project_id, transfer_from_category, created_by) 
                            VALUES 
                            (:project_id, :transaction_date, :description, :amount, :category_type_id, :reference_number, 
                             :is_transfer, :is_transfer_in, :transfer_from_project_id, :transfer_from_category, :created_by)";
            
            $incomingStmt = $this->conn->prepare($incomingQuery);
            $incomingStmt->bindParam(':project_id', $incomingData['project_id']);
            $incomingStmt->bindParam(':transaction_date', $incomingData['transaction_date']);
            $incomingStmt->bindParam(':description', $incomingData['description']);
            $incomingStmt->bindParam(':amount', $incomingData['amount']);
            $incomingStmt->bindParam(':category_type_id', $incomingData['category_type_id']);
            $incomingStmt->bindParam(':reference_number', $incomingData['reference_number']);
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
            
            // Get total used budget from transactions (excluding transfers)
            $usedQuery = "SELECT COALESCE(SUM(amount), 0) as used_budget 
                         FROM transactions 
                         WHERE project_id = :project_id 
                         AND transaction_type NOT IN ('transfer_in', 'transfer_out')";
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
                        COUNT(CASE WHEN transaction_type = 'income' THEN 1 END) as income_transactions,
                        COUNT(CASE WHEN transaction_type = 'expense' THEN 1 END) as expense_transactions,
                        COUNT(CASE WHEN transaction_type IN ('transfer_in', 'transfer_out') THEN 1 END) as transfer_transactions,
                        COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
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
                    $whereConditions[] = "transaction_date >= :date_from";
                    $params[':date_from'] = $filters['date_from'];
                }
                
                if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                    $whereConditions[] = "transaction_date <= :date_to";
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
                'transfer_transactions' => 0,
                'total_income' => 0,
                'total_expense' => 0
            ];
        }
    }

    /**
     * Get transactions count for pagination
     */
    public function getTransactionsCount($projectId = null, $category = null, $filters = []) {
        try {
            $query = "SELECT COUNT(*) as count FROM transactions t LEFT JOIN category_types ct ON t.category_type_id = ct.id WHERE 1=1";
            $params = [];
            
            if ($projectId) {
                $query .= " AND t.project_id = :project_id";
                $params[':project_id'] = $projectId;
            }
            
            if ($category && $category !== 'all') {
                $query .= " AND ct.category_key = :category";
                $params[':category'] = $category;
            }
            
            // Handle additional filters
            if (!empty($filters)) {
                if (isset($filters['type']) && !empty($filters['type'])) {
                    if ($filters['type'] === 'transfer') {
                        $query .= " AND t.transaction_type IN ('transfer_in', 'transfer_out')";
                    } else {
                        $query .= " AND t.transaction_type = :type_filter";
                        $params[':type_filter'] = $filters['type'];
                    }
                }
                
                if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                    $query .= " AND t.transaction_date >= :date_from";
                    $params[':date_from'] = $filters['date_from'];
                }
                
                if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                    $query .= " AND t.transaction_date <= :date_to";
                    $params[':date_to'] = $filters['date_to'];
                }
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
            
            // Get SUBSIDY category_type_id
            $categoryQuery = "SELECT id FROM category_types WHERE category_key = 'SUBSIDY'";
            $categoryStmt = $this->conn->prepare($categoryQuery);
            $categoryStmt->execute();
            $categoryResult = $categoryStmt->fetch(PDO::FETCH_ASSOC);
            $subsidyCategoryId = $categoryResult ? $categoryResult['id'] : null;
            
            if (!$subsidyCategoryId) {
                throw new Exception('ไม่พบหมวดงบประมาณ SUBSIDY ในระบบ');
            }
            
            // Create transfer out transaction for source project
            $transferOutQuery = "INSERT INTO transactions (project_id, category_type_id, amount, transaction_type, description, transaction_date, created_by, is_transfer, transfer_to_project_id) 
                               VALUES (:project_id, :category_type_id, :amount, 'transfer_out', :description, :transaction_date, :created_by, 1, :transfer_to_project_id)";
            
            $stmt = $this->conn->prepare($transferOutQuery);
            $stmt->bindParam(':project_id', $fromProjectId);
            $stmt->bindParam(':category_type_id', $subsidyCategoryId);
            $stmt->bindValue(':amount', -$amount); // Negative amount for outgoing transfer
            $stmt->bindValue(':description', 'โอนออก: ' . $description);
            $stmt->bindParam(':transaction_date', $transferDate);
            $stmt->bindParam(':created_by', $userId);
            $stmt->bindParam(':transfer_to_project_id', $toProjectId);
            $stmt->execute();
            
            // Create transfer in transaction for destination project
            $transferInQuery = "INSERT INTO transactions (project_id, category_type_id, amount, transaction_type, description, transaction_date, created_by, is_transfer, is_transfer_in, transfer_from_project_id) 
                              VALUES (:project_id, :category_type_id, :amount, 'transfer_in', :description, :transaction_date, :created_by, 1, 1, :transfer_from_project_id)";
            
            $stmt = $this->conn->prepare($transferInQuery);
            $stmt->bindParam(':project_id', $toProjectId);
            $stmt->bindParam(':category_type_id', $subsidyCategoryId);
            $stmt->bindValue(':amount', $amount); // Positive amount for incoming transfer
            $stmt->bindValue(':description', 'รับโอน: ' . $description);
            $stmt->bindParam(':transaction_date', $transferDate);
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
            
            // Convert category keys to category_type_ids
            $fromCategoryId = $this->getCategoryTypeId($fromCategory);
            $toCategoryId = $this->getCategoryTypeId($toCategory);
            
            if (!$fromCategoryId) {
                throw new Exception('ไม่พบหมวดหมู่ต้นทาง: ' . $fromCategory);
            }
            if (!$toCategoryId) {
                throw new Exception('ไม่พบหมวดหมู่ปลายทาง: ' . $toCategory);
            }
            
            // Check if source project has sufficient budget in the specified category
            // Use getProjectCategoryBalanceForSummary to match the balance displayed in UI (includes transfers)
            $sourceBalance = $this->getProjectCategoryBalanceForSummary($fromProjectId, $fromCategory);
            if ($sourceBalance < $amount) {
                throw new Exception('งบประมาณในหมวด ' . $fromCategory . ' ไม่เพียงพอสำหรับการโอน');
            }
            
            // Get project names for descriptions
            $fromProjectName = $this->getProjectName($fromProjectId);
            $toProjectName = $this->getProjectName($toProjectId);
            
            // Get category names for descriptions
            $fromCategoryName = $this->getCategoryTypeName($fromCategory);
            $toCategoryName = $this->getCategoryTypeName($toCategory);
            
            // Create transfer out transaction for source project
            $transferOutQuery = "INSERT INTO transactions (project_id, category_type_id, amount, transaction_type, description, transaction_date, created_by, is_transfer, transfer_to_project_id, transfer_to_category) 
                               VALUES (:project_id, :category_type_id, :amount, 'transfer_out', :description, :transaction_date, :created_by, 1, :transfer_to_project_id, :transfer_to_category)";
            
            $stmt = $this->conn->prepare($transferOutQuery);
            $stmt->bindParam(':project_id', $fromProjectId);
            $stmt->bindParam(':category_type_id', $fromCategoryId);
            $stmt->bindValue(':amount', -$amount); // Negative amount for outgoing transfer
            $stmt->bindValue(':description', 'โอนออก (' . $fromCategoryName . ' → ' . $toCategoryName . '): ' . $description);
            $stmt->bindParam(':transaction_date', $transferDate);
            $stmt->bindParam(':created_by', $userId);
            $stmt->bindParam(':transfer_to_project_id', $toProjectId);
            $stmt->bindParam(':transfer_to_category', $toCategory);
            $stmt->execute();
            
            // Create transfer in transaction for destination project
            $transferInQuery = "INSERT INTO transactions (project_id, category_type_id, amount, transaction_type, description, transaction_date, created_by, is_transfer, is_transfer_in, transfer_from_project_id, transfer_from_category) 
                              VALUES (:project_id, :category_type_id, :amount, 'transfer_in', :description, :transaction_date, :created_by, 1, 1, :transfer_from_project_id, :transfer_from_category)";
            
            $stmt = $this->conn->prepare($transferInQuery);
            $stmt->bindParam(':project_id', $toProjectId);
            $stmt->bindParam(':category_type_id', $toCategoryId);
            $stmt->bindValue(':amount', $amount); // Positive amount for incoming transfer
            $stmt->bindValue(':description', 'รับโอน (' . $fromCategoryName . ' → ' . $toCategoryName . '): ' . $description);
            $stmt->bindParam(':transaction_date', $transferDate);
            $stmt->bindParam(':created_by', $userId);
            $stmt->bindParam(':transfer_from_project_id', $fromProjectId);
            $stmt->bindParam(':transfer_from_category', $fromCategory);
            $stmt->execute();
            
            // Ensure destination project has a budget category entry (with 0 budget if new)
            // This is needed so the category appears in the project's category list
            $ensureCategoryQuery = "INSERT IGNORE INTO budget_categories (project_id, category_type_id, budget_amount) 
                                  VALUES (:project_id, :category_type_id, 0)";
            $stmt = $this->conn->prepare($ensureCategoryQuery);
            $stmt->bindParam(':project_id', $toProjectId);
            $stmt->bindParam(':category_type_id', $toCategoryId);
            $stmt->execute();
            
            // Note: We don't modify budget_amount in budget_categories table for transfers
            // The remaining balance is calculated from transactions: budget_amount + income + transfer_in - expense - transfer_out
            // This way, budget_amount represents the original allocated budget, and transfers are tracked via transactions
            
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
            // If category is a string (category_key), convert to category_type_id
            if (is_string($category)) {
                $categoryQuery = "SELECT id FROM category_types WHERE category_key = :category_key";
                $categoryStmt = $this->conn->prepare($categoryQuery);
                $categoryStmt->bindParam(':category_key', $category);
                $categoryStmt->execute();
                $categoryResult = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                $categoryTypeId = $categoryResult ? $categoryResult['id'] : null;
                
                if (!$categoryTypeId) {
                    return 0; // Category not found
                }
            } else {
                $categoryTypeId = $category;
            }
            
            // Calculate net amount for this category
            // Note: transfer_out reduces balance, transfer_in increases balance
            // Note: transfer_out amounts are stored as negative values in DB
            $query = "SELECT 
                        COALESCE(SUM(CASE WHEN transaction_type = 'income' OR transaction_type = 'transfer_in' THEN amount ELSE 0 END), 0) as total_income,
                        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                        COALESCE(SUM(CASE WHEN transaction_type = 'transfer_out' THEN amount ELSE 0 END), 0) as total_transfer_out
                     FROM transactions 
                     WHERE project_id = :project_id AND category_type_id = :category_type_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->bindParam(':category_type_id', $categoryTypeId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalIncome = floatval($result['total_income']);
            $totalExpense = floatval($result['total_expense']);
            $totalTransferOut = floatval($result['total_transfer_out']); // Already negative
            
            // Get the budget amount for this category
            $budgetQuery = "SELECT budget_amount FROM budget_categories 
                          WHERE project_id = :project_id AND category_type_id = :category_type_id";
            
            $budgetStmt = $this->conn->prepare($budgetQuery);
            $budgetStmt->bindParam(':project_id', $projectId);
            $budgetStmt->bindParam(':category_type_id', $categoryTypeId);
            $budgetStmt->execute();
            
            $budgetResult = $budgetStmt->fetch(PDO::FETCH_ASSOC);
            $budgetAmount = $budgetResult ? floatval($budgetResult['budget_amount']) : 0;
            
            // Return remaining balance: budget + income - expenses - transfer_out
            // Note: totalTransferOut is already negative, so we add it instead of subtracting
            return $budgetAmount + $totalIncome - $totalExpense + $totalTransferOut;
            
        } catch (Exception $e) {
            error_log("Get project category balance error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get project category balance for budget summary (includes transfers)
     */
    public function getProjectCategoryBalanceForSummary($projectId, $category) {
        try {
            // If category is a string (category_key), convert to category_type_id
            if (is_string($category)) {
                $categoryQuery = "SELECT id FROM category_types WHERE category_key = :category_key";
                $categoryStmt = $this->conn->prepare($categoryQuery);
                $categoryStmt->bindParam(':category_key', $category);
                $categoryStmt->execute();
                $categoryResult = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                $categoryTypeId = $categoryResult ? $categoryResult['id'] : null;
                
                if (!$categoryTypeId) {
                    return 0; // Category not found
                }
            } else {
                $categoryTypeId = $category;
            }
            
            // Calculate net amount for this category (income + transfer_in - expense - transfer_out)
            // Note: transfer_out amounts are stored as negative values in DB
            $query = "SELECT 
                        COALESCE(SUM(CASE WHEN transaction_type = 'income' OR transaction_type = 'transfer_in' THEN amount ELSE 0 END), 0) as total_income,
                        COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                        COALESCE(SUM(CASE WHEN transaction_type = 'transfer_out' THEN amount ELSE 0 END), 0) as total_transfer_out
                     FROM transactions 
                     WHERE project_id = :project_id AND category_type_id = :category_type_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':project_id', $projectId);
            $stmt->bindParam(':category_type_id', $categoryTypeId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalIncome = floatval($result['total_income']);
            $totalExpense = floatval($result['total_expense']);
            $totalTransferOut = floatval($result['total_transfer_out']); // Already negative
            
            // Get the budget amount for this category
            $budgetQuery = "SELECT budget_amount FROM budget_categories 
                          WHERE project_id = :project_id AND category_type_id = :category_type_id";
            
            $budgetStmt = $this->conn->prepare($budgetQuery);
            $budgetStmt->bindParam(':project_id', $projectId);
            $budgetStmt->bindParam(':category_type_id', $categoryTypeId);
            $budgetStmt->execute();
            
            $budgetResult = $budgetStmt->fetch(PDO::FETCH_ASSOC);
            $budgetAmount = $budgetResult ? floatval($budgetResult['budget_amount']) : 0;
            
            // Return remaining balance: budget + income + transfer_in - expenses - transfer_out
            // Note: totalTransferOut is already negative, so we add it instead of subtracting
            return $budgetAmount + $totalIncome - $totalExpense + $totalTransferOut;
            
        } catch (Exception $e) {
            error_log("Get project category balance for summary error: " . $e->getMessage());
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
                     WHERE t.transaction_type IN ('transfer_in', 'transfer_out')
                     ORDER BY t.transaction_date DESC, t.created_at DESC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process descriptions to replace category codes with names
            foreach ($transfers as &$transfer) {
                if (isset($transfer['description'])) {
                    // Replace category codes in descriptions like "โอนออก (ACTIVITY_FUNDS → ACTIVITY_FUNDS):"
                    $description = $transfer['description'];
                    
                    // Pattern to match category codes in parentheses with optional colon
                    $pattern = '/\(([A-Z_]+)\s*→\s*([A-Z_]+)\):?/';
                    
                    if (preg_match($pattern, $description, $matches)) {
                        $fromCategoryCode = $matches[1];
                        $toCategoryCode = $matches[2];
                        
                        // Get category names
                        $fromCategoryName = $this->getCategoryTypeName($fromCategoryCode);
                        $toCategoryName = $this->getCategoryTypeName($toCategoryCode);
                        
                        // Replace the category codes with names, preserving the colon if it exists
                        $hasColon = strpos($matches[0], ':') !== false;
                        $newCategoryPart = "($fromCategoryName → $toCategoryName)" . ($hasColon ? ':' : '');
                        $transfer['description'] = preg_replace($pattern, $newCategoryPart, $description);
                    }
                }
            }
            
            return $transfers;
            
        } catch (Exception $e) {
            error_log("Get transfer history error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get category type ID from category key
     */
    private function getCategoryTypeId($categoryKey) {
        try {
            $query = "SELECT id FROM category_types WHERE category_key = :category_key";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':category_key', $categoryKey);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;
            
        } catch (Exception $e) {
            error_log("Get category type ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get category type name from category key
     */
    private function getCategoryTypeName($categoryKey) {
        try {
            $query = "SELECT name FROM category_types WHERE category_key = :category_key";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':category_key', $categoryKey);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['name'] : $categoryKey;
            
        } catch (Exception $e) {
            error_log("Get category type name error: " . $e->getMessage());
            return $categoryKey;
        }
    }
}
?>