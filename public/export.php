<?php
/**
 * Export functionality for generating Excel reports
 * Updated to use PhpSpreadsheet for proper Excel format
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../vendor/autoload.php';
require_once '../config/database.php';
require_once '../src/Services/TransactionService.php';
require_once '../src/Services/ProjectService.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

session_start();

// Check if user is logged in
// Temporarily disabled for testing
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

try {
    $database = new Database();
    $db = $database->getConnection();
    $transactionService = new TransactionService();
    $projectService = new ProjectService($db);
    
    // Get export parameters
    $exportType = $_GET['export'] ?? 'excel';
    $format = $_GET['format'] ?? 'excel';
    $type = $_GET['type'] ?? 'transactions';
    
    // Handle legacy parameter mapping
    if ($exportType === 'summary') {
        $type = 'summary';
    }
    
    // Get filter parameters
    $projectId = $_GET['project_id'] ?? $_GET['project_filter'] ?? null;
    $categoryId = $_GET['category_id'] ?? null;
    $startDate = $_GET['start_date'] ?? $_GET['date_from'] ?? null;
    $endDate = $_GET['end_date'] ?? $_GET['date_to'] ?? null;
    $transactionType = $_GET['transaction_type'] ?? null;
    $fiscalYearId = $_GET['fiscal_year_filter'] ?? null;
    
    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    if ($type === 'transactions') {
        exportTransactions($sheet, $transactionService, $projectService, $projectId, $categoryId, $startDate, $endDate, $transactionType, $fiscalYearId);
    } elseif ($type === 'projects') {
        exportProjects($sheet, $projectService, $fiscalYearId);
    } elseif ($type === 'summary') {
        exportSummary($sheet, $transactionService, $projectService, $projectId, $startDate, $endDate, $fiscalYearId);
    }
    
    // Set output format and headers
    if ($format === 'csv') {
        $writer = new Csv($spreadsheet);
        $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv');
    } else {
        $writer = new Xlsx($spreadsheet);
        $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
    
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "เกิดข้อผิดพลาดในการส่งออกข้อมูล: " . $e->getMessage();
    exit;
}

function exportTransactions($sheet, $transactionService, $projectService, $projectId, $categoryId, $startDate, $endDate, $transactionType, $fiscalYearId) {
    // Set title
    $sheet->setTitle('รายการธุรกรรม');
    
    // Set headers
    $headers = ['วันที่', 'โครงการ', 'หมวดหมู่งบประมาณ', 'รายการ', 'ประเภท', 'จำนวนเงิน (บาท)', 'หมายเหตุ'];
    $sheet->fromArray($headers, null, 'A1');
    
    // Style headers
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
    
    // Build filters for service
    $filters = [];
    if ($transactionType) $filters['type'] = $transactionType;
    if ($startDate) $filters['date_from'] = $startDate;
    if ($endDate) $filters['date_to'] = $endDate;
    if ($fiscalYearId) $filters['fiscal_year_id'] = $fiscalYearId;

    // Get transactions data
    $transactions = $transactionService->getAllTransactions($projectId, $categoryId, null, 0, $filters);
    
    // Get projects for name lookup (filtered by fiscal year if provided)
    $projects = $projectService->getAllProjects(null, null, $fiscalYearId);
    $projectNames = [];
    foreach ($projects as $project) {
        $projectNames[$project['id']] = $project['name'];
    }
    
    // Add data rows
    $row = 2;
    $totalIncome = 0;
    $totalExpense = 0;
    
    foreach ($transactions as $transaction) {
        $projectName = $projectNames[$transaction['project_id']] ?? $transaction['project_name'] ?? 'ไม่ระบุ';
        $amount = number_format($transaction['amount'], 2);
        $type = $transaction['type'] === 'income' ? 'รายรับ' : 'รายจ่าย';
        
        if ($transaction['type'] === 'income') {
            $totalIncome += $transaction['amount'];
        } else {
            $totalExpense += $transaction['amount'];
        }
        
        $rowData = [
            $transaction['transaction_date'],
            $projectName,
            $transaction['category_name'] ?? 'ไม่ระบุ',
            $transaction['description'],
            $type,
            $amount,
            $transaction['notes'] ?? '' // transactions table doesn't have notes column in schema provided, but kept for compatibility if added later
        ];
        
        $sheet->fromArray($rowData, null, 'A' . $row);
        
        // Color code by transaction type
        if ($transaction['type'] === 'income') {
            $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D4EDDA');
        } else {
            $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8D7DA');
        }
        
        $row++;
    }
    
    // Add summary
    $row += 2;
    $sheet->setCellValue('E' . $row, 'สรุป');
    $sheet->getStyle('E' . $row)->getFont()->setBold(true);
    
    $row++;
    $sheet->setCellValue('E' . $row, 'รายรับรวม:');
    $sheet->setCellValue('F' . $row, number_format($totalIncome, 2));
    $sheet->getStyle('F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D4EDDA');
    
    $row++;
    $sheet->setCellValue('E' . $row, 'รายจ่ายรวม:');
    $sheet->setCellValue('F' . $row, number_format($totalExpense, 2));
    $sheet->getStyle('F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8D7DA');
    
    $row++;
    $sheet->setCellValue('E' . $row, 'ยอดคงเหลือ:');
    $balance = $totalIncome - $totalExpense;
    $sheet->setCellValue('F' . $row, number_format($balance, 2));
    $sheet->getStyle('E' . $row . ':F' . $row)->getFont()->setBold(true);
    
    if ($balance >= 0) {
        $sheet->getStyle('F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D4EDDA');
    } else {
        $sheet->getStyle('F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8D7DA');
    }
    
    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add borders to data
    $dataRange = 'A1:G' . ($row);
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

function exportProjects($sheet, $projectService, $fiscalYearId) {
    $sheet->setTitle('โครงการ');
    
    // Set headers
    $headers = ['ชื่อโครงการ', 'คำอธิบาย', 'งบประมาณรวม (บาท)', 'วันที่เริ่ม', 'วันที่สิ้นสุด', 'สถานะ'];
    $sheet->fromArray($headers, null, 'A1');
    
    // Style headers
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
    
    // Get projects data filtered by fiscal year
    $projects = $projectService->getAllProjects(null, null, $fiscalYearId);
    
    $row = 2;
    foreach ($projects as $project) {
        $status = $project['status'] === 'active' ? 'ใช้งาน' : 'ไม่ใช้งาน';
        
        $rowData = [
            $project['name'],
            $project['description'] ?? '',
            number_format($project['total_budget'], 2),
            $project['start_date'] ?? '',
            $project['end_date'] ?? '',
            $status
        ];
        
        $sheet->fromArray($rowData, null, 'A' . $row);
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add borders
    $dataRange = 'A1:F' . ($row - 1);
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

function exportSummary($sheet, $transactionService, $projectService, $projectId, $startDate, $endDate, $fiscalYearId) {
    $sheet->setTitle('สรุปรายงาน');
    
    // Title
    $sheet->setCellValue('A1', 'รายงานสรุปการเงิน');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->mergeCells('A1:D1');
    
    // Date range
    $row = 3;
    if ($startDate && $endDate) {
        $sheet->setCellValue('A' . $row, 'ช่วงวันที่: ' . $startDate . ' ถึง ' . $endDate);
        $row += 2;
    }
    
    // Build filters for service
    $filters = [];
    if ($startDate) $filters['date_from'] = $startDate;
    if ($endDate) $filters['date_to'] = $endDate;
    if ($fiscalYearId) $filters['fiscal_year_id'] = $fiscalYearId;

    // Get summary data
    $transactions = $transactionService->getAllTransactions($projectId, null, null, 0, $filters);
    
    // Get projects for name lookup (filtered by fiscal year)
    $projects = $projectService->getAllProjects(null, null, $fiscalYearId);
    
    // Calculate totals by project
    $projectSummary = [];
    foreach ($transactions as $transaction) {
        $pid = $transaction['project_id'];
        if (!isset($projectSummary[$pid])) {
            $projectSummary[$pid] = [
                'name' => '',
                'income' => 0,
                'expense' => 0
            ];
        }
        
        if ($transaction['type'] === 'income') {
            $projectSummary[$pid]['income'] += $transaction['amount'];
        } else {
            $projectSummary[$pid]['expense'] += $transaction['amount'];
        }
    }
    
    // Add project names
    foreach ($projects as $project) {
        if (isset($projectSummary[$project['id']])) {
            $projectSummary[$project['id']]['name'] = $project['name'];
        }
    }
    
    // Headers for summary table
    $headers = ['โครงการ', 'รายรับ (บาท)', 'รายจ่าย (บาท)', 'ยอดคงเหลือ (บาท)'];
    $sheet->fromArray($headers, null, 'A' . $row);
    
    // Style headers
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($headerStyle);
    
    $row++;
    $totalIncome = 0;
    $totalExpense = 0;
    
    foreach ($projectSummary as $summary) {
        $balance = $summary['income'] - $summary['expense'];
        $totalIncome += $summary['income'];
        $totalExpense += $summary['expense'];
        
        $rowData = [
            $summary['name'],
            number_format($summary['income'], 2),
            number_format($summary['expense'], 2),
            number_format($balance, 2)
        ];
        
        $sheet->fromArray($rowData, null, 'A' . $row);
        
        // Color code balance
        if ($balance >= 0) {
            $sheet->getStyle('D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D4EDDA');
        } else {
            $sheet->getStyle('D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8D7DA');
        }
        
        $row++;
    }
    
    // Grand totals
    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->setCellValue('B' . $row, number_format($totalIncome, 2));
    $sheet->setCellValue('C' . $row, number_format($totalExpense, 2));
    $sheet->setCellValue('D' . $row, number_format($totalIncome - $totalExpense, 2));
    $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
    
    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add borders
    $dataRange = 'A' . ($row - count($projectSummary) - 1) . ':D' . $row;
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
?>