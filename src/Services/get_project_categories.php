<?php
/**
 * API endpoint to get project categories with current remaining balances
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/TransactionService.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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
    
    // Get all categories for the project with current balances
    $query = "SELECT DISTINCT 
                ct.category_key as category,
                ct.category_name,
                bc.budget_amount
              FROM budget_categories bc
              JOIN category_types ct ON bc.category_type_id = ct.id
              WHERE bc.project_id = :project_id
              ORDER BY ct.category_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
    $stmt->execute();
    
    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get current remaining balance for this category (excluding transfers)
        $remainingBalance = $transactionService->getProjectCategoryBalanceForSummary($projectId, $row['category']);
        
        $categories[] = [
            'category' => $row['category'],
            'category_name' => $row['category_name'],
            'budget_amount' => floatval($row['budget_amount']),
            'remaining_balance' => $remainingBalance
        ];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>