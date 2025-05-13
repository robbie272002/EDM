<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/database.php';

try {
    // Drop the existing table
    $pdo->exec("DROP TABLE IF EXISTS activity_logs");
    echo "Dropped existing activity_logs table<br>";
    
    // Create the table again with proper ENUM values
    $sql = "CREATE TABLE activity_logs (
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
    echo "Created new activity_logs table with proper ENUM values<br>";
    
    // Add a test log entry
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description, affected_table, affected_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([1, 'create_category', 'Test log entry', 'categories', 1]);
    echo "Added test log entry<br>";
    
    // Verify the table structure
    $stmt = $pdo->query("SHOW CREATE TABLE activity_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    echo "Table reset complete!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "SQL State: " . $e->getCode() . "<br>";
    echo "Error Info: ";
    print_r($e->errorInfo);
}
