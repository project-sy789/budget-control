<?php
// Debug login issues
echo "<h2>Debug Login System</h2>";
echo "<pre>";

try {
    // Include database configuration
    require_once __DIR__ . '/../config/database.php';
    
    echo "1. Testing database connection...\n";
    $database = new Database();
    $pdo = $database->getConnection();
    echo "✓ Database connection successful!\n\n";
    
    // Check if user_sessions table exists
    echo "2. Checking user_sessions table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "✗ user_sessions table does not exist. Creating...\n";
        
        $createTableSQL = "
        CREATE TABLE user_sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_expires (user_id, expires_at)
        )
        ";
        
        $pdo->exec($createTableSQL);
        echo "✓ user_sessions table created successfully!\n";
    } else {
        echo "✓ user_sessions table exists.\n";
    }
    
    // Check users in database
    echo "\n3. Checking users in database...\n";
    $stmt = $pdo->query("SELECT id, username, email, role, approved FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "✗ No users found in database. Creating default admin user...\n";
        
        $insertUserSQL = "
        INSERT INTO users (username, email, password_hash, display_name, role, approved) 
        VALUES ('admin', 'admin@example.com', :password_hash, 'Administrator', 'admin', 1)
        ";
        
        $stmt = $pdo->prepare($insertUserSQL);
        $stmt->execute([
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT)
        ]);
        
        echo "✓ Default admin user created!\n";
        echo "  Username: admin\n";
        echo "  Password: admin123\n";
    } else {
        echo "✓ Found " . count($users) . " user(s) in database:\n";
        foreach ($users as $user) {
            $approvedText = $user['approved'] ? 'Yes' : 'No';
            echo "  - ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}, Approved: {$approvedText}\n";
        }
    }
    
    // Test authentication service
    echo "\n4. Testing authentication service...\n";
    require_once __DIR__ . '/../src/Auth/AuthService.php';
    require_once __DIR__ . '/../src/Auth/SessionManager.php';
    
    $sessionManager = new SessionManager($pdo);
    $authService = new AuthService($pdo, $sessionManager);
    
    echo "✓ Authentication service loaded successfully!\n";
    
    // Check session configuration
    echo "\n5. Checking session configuration...\n";
    echo "Session status: " . session_status() . "\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session name: " . session_name() . "\n";
    
    if (isset($_SESSION)) {
        echo "Session variables: " . json_encode($_SESSION) . "\n";
    } else {
        echo "No session variables set.\n";
    }
    
    echo "\n✓ All checks completed successfully!\n";
    echo "\nYou can now try logging in with the credentials shown above.\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<p><a href='index.php?page=login'>Go to Login Page</a></p>";
?>