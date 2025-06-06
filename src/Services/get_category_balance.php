<?php
/**
 * API endpoint to get category balance
 */

require_once '../../config/database.php';
require_once 'TransactionService.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $db = new Database();
    $transactionService = new TransactionService();
    
    $projectId = intval($_GET['project_id'] ?? 0);
    $category = $_GET['category'] ?? '';
    
    if ($projectId <= 0 || empty($category)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid parameters'
        ]);
        exit;
    }
    
    // Use getProjectCategoryBalanceForSummary to match the balance displayed in UI (includes transfers)
    $remainingBalance = $transactionService->getProjectCategoryBalanceForSummary($projectId, $category);
    
    echo json_encode([
        'success' => true,
        'remaining_balance' => $remainingBalance
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>