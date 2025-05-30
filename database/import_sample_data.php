<?php
/**
 * Import Sample Data Script
 * Budget Control System v2
 * 
 * This script imports sample data into the database for testing and demonstration purposes.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Create database connection
    $database = new Database();
    
    // Create PDO connection without specifying database name first
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $username = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASS'] ?? '';
    
    $dsn = "mysql:host={$host};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo "Connected to database successfully.\n";
    
    // Read and execute schema.sql first
    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        echo "Importing database schema...\n";
        $schemaSql = file_get_contents($schemaFile);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore errors for existing tables/data
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo "Warning: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        echo "Schema imported successfully.\n";
    }
    
    // Read and execute sample_data.sql
    $sampleDataFile = __DIR__ . '/sample_data.sql';
    if (file_exists($sampleDataFile)) {
        echo "Importing sample data...\n";
        $sampleSql = file_get_contents($sampleDataFile);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sampleSql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore duplicate entry errors
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo "Warning: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        echo "Sample data imported successfully.\n";
    } else {
        echo "Sample data file not found: $sampleDataFile\n";
        exit(1);
    }
    
    // Verify data import
    $pdo->exec("USE budget_control");
    
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $projectCount = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $transactionCount = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM budget_categories")->fetchColumn();
    
    echo "\n=== Import Summary ===\n";
    echo "Users: $userCount\n";
    echo "Projects: $projectCount\n";
    echo "Budget Categories: $categoryCount\n";
    echo "Transactions: $transactionCount\n";
    echo "\n=== Sample Login Credentials ===\n";
    echo "Admin User:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "\nRegular Users:\n";
    echo "  Username: user1, Password: user123\n";
    echo "  Username: teacher1, Password: user123\n";
    echo "  Username: budget1, Password: user123\n";
    echo "  Username: hr1, Password: user123\n";
    echo "  Username: general1, Password: user123\n";
    echo "\nSample data imported successfully!\n";
    echo "You can now test the Budget Control System with realistic data.\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}