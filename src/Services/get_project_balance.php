<?php
/**
 * API endpoint to get project total remaining balance
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/TransactionService.php';

header('Content-Type: application/json');

try {
    // Get project ID from query parameter
    $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    
    if (!$projectId) {
        echo json_encode([
            'success' => false,
            'message' => 'Project ID is required'
        ]);
        exit;
    }
    
    // Initialize database connection and service
    $database = new Database();
    $db = $database->getConnection();
    $transactionService = new TransactionService();
    
    // Calculate total project balance using the same logic as ProjectService
    // Get initial budget from budget categories
    $query = "SELECT COALESCE(SUM(budget_amount), 0) as initial_budget
              FROM budget_categories 
              WHERE project_id = :project_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $initialBudget = floatval($result['initial_budget']);
    
    // Calculate net transfers for this project
    $transferQuery = "SELECT 
        COALESCE(SUM(CASE WHEN transaction_type = 'transfer_in' THEN amount ELSE 0 END), 0) as transfer_in,
        COALESCE(SUM(CASE WHEN transaction_type = 'transfer_out' THEN amount ELSE 0 END), 0) as transfer_out
        FROM transactions WHERE project_id = :project_id";
    
    $transferStmt = $db->prepare($transferQuery);
    $transferStmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
    $transferStmt->execute();
    $transferResult = $transferStmt->fetch(PDO::FETCH_ASSOC);
    
    $transferIn = floatval($transferResult['transfer_in']);
    $transferOut = floatval($transferResult['transfer_out']); // Already negative
    $netTransfer = $transferIn + $transferOut; // Add because transfer_out is already negative
    
    // Calculate actual total budget including net transfers
    $actualTotalBudget = $initialBudget + $netTransfer;
    
    // Calculate used budget from transactions (expenses only, excluding transfers)
    $usedQuery = "SELECT COALESCE(SUM(amount), 0) as used_budget 
                 FROM transactions 
                 WHERE project_id = :project_id 
                 AND transaction_type NOT IN ('transfer_in', 'transfer_out')";
    
    $usedStmt = $db->prepare($usedQuery);
    $usedStmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
    $usedStmt->execute();
    
    $usedResult = $usedStmt->fetch(PDO::FETCH_ASSOC);
    $usedBudget = floatval($usedResult['used_budget']);
    
    // Calculate remaining balance
    $totalBalance = $actualTotalBudget - $usedBudget;
    
    echo json_encode([
        'success' => true,
        'balance' => $totalBalance
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>