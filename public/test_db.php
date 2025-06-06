<?php
/**
 * Database Connection Test
 */

try {
    $host = 'localhost';
    $port = 3306;
    $db_name = 'budget_control';
    $username = 'root';
    $password = '';
    
    echo "Testing database connection...\n";
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "Database: $db_name\n";
    echo "Username: $username\n";
    echo "Password: " . str_repeat('*', strlen($password)) . "\n\n";
    
    $conn = new PDO(
        "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $db_name . ";charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    echo "✅ Database connection successful!\n";
    
    // Test a simple query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM projects");
    $result = $stmt->fetch();
    echo "✅ Found {$result['count']} projects in database\n";
    
    // Test getting categories
    $stmt = $conn->query("SELECT COUNT(*) as count FROM budget_categories");
    $result = $stmt->fetch();
    echo "✅ Found {$result['count']} budget categories in database\n";
    
} catch(PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
?>