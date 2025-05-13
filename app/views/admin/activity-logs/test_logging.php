<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/logger.php';

// Test database connection
echo "Testing database connection...<br>";
try {
    $pdo->query("SELECT 1");
    echo "Database connection successful!<br>";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Check if activity_logs table exists
echo "Checking activity_logs table...<br>";
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'activity_logs'")->fetchAll();
    if (empty($tables)) {
        echo "activity_logs table does not exist!<br>";
        echo "Attempting to create activity_logs table...<br>";
        
        // Create the activity_logs table
        $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action_type ENUM(
                'login', 'logout', 
                'create_product', 'read_product', 'update_product', 'delete_product',
                'create_category', 'read_category', 'update_category', 'delete_category',
                'create_user', 'read_user', 'update_user', 'delete_user',
                'add_stock', 'remove_stock', 'adjust_stock',
                'create_sale', 'void_sale', 'refund_sale',
                'edit_settings', 'view_reports',
                'cashier_login', 'cashier_logout',
                'cashier_sale', 'cashier_void', 'cashier_refund'
            ) NOT NULL,
            description TEXT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            affected_table VARCHAR(50),
            affected_id INT,
            old_value TEXT,
            new_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $pdo->exec($sql);
        echo "activity_logs table created successfully!<br>";
    } else {
        echo "activity_logs table exists!<br>";
    }
} catch (PDOException $e) {
    echo "Error checking/creating activity_logs table: " . $e->getMessage() . "<br>";
    exit;
}

// Test logging functionality
echo "Testing logging functionality...<br>";
try {
    $result = logActivity(
        1, // admin user id
        'create_category',
        'Test category creation',
        'categories',
        1,
        null,
        ['name' => 'Test Category', 'description' => 'Test Description']
    );
    
    if ($result) {
        echo "Test log entry created successfully!<br>";
    } else {
        echo "Failed to create test log entry<br>";
    }
    
    // Check if the log entry exists
    $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 1");
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($log) {
        echo "Latest log entry:<br>";
        echo "<pre>";
        print_r($log);
        echo "</pre>";
    } else {
        echo "No log entries found<br>";
    }
    
} catch (PDOException $e) {
    echo "Error testing logging functionality: " . $e->getMessage() . "<br>";
}
