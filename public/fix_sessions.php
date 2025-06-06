<?php
// Fix user_sessions table issue
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if user_sessions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Creating user_sessions table...\n";
        
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
        echo "user_sessions table created successfully!\n";
    } else {
        echo "user_sessions table already exists.\n";
    }
    
    // Check if there are any users in the database
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Total users in database: " . $userCount . "\n";
    
    if ($userCount == 0) {
        echo "No users found. Creating default admin user...\n";
        
        $insertUserSQL = "
        INSERT INTO users (username, email, password_hash, display_name, role, approved) 
        VALUES ('admin', 'admin@example.com', :password_hash, 'Administrator', 'admin', 1)
        ";
        
        $stmt = $pdo->prepare($insertUserSQL);
        $stmt->execute([
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT)
        ]);
        
        echo "Default admin user created!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        echo "Existing users found:\n";
        $stmt = $pdo->query("SELECT id, username, email, role, approved FROM users");
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}, Approved: " . ($user['approved'] ? 'Yes' : 'No') . "\n";
        }
    }
    
    echo "\nDatabase fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>