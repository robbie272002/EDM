<?php
require_once __DIR__ . '/../../../config/database.php';

echo "<h2>Setting up activity_logs table</h2>";

try {
    // Create activity_logs table
    $query = "CREATE TABLE IF NOT EXISTS activity_logs (
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
    
    $pdo->exec($query);
    echo "activity_logs table created successfully!<br>";
    
    // Clear existing test data
    $pdo->exec("TRUNCATE TABLE activity_logs");
    echo "Cleared existing test data<br>";
    
    // Add comprehensive test data
    $testLogs = [
        // Admin actions
        [
            'user_id' => 1,
            'action_type' => 'login',
            'description' => 'Admin user logged in successfully',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'user_id' => 1,
            'action_type' => 'create_product',
            'description' => 'Created new product: iPhone 13 Pro',
            'affected_table' => 'products',
            'affected_id' => 123,
            'new_value' => json_encode(['name' => 'iPhone 13 Pro', 'price' => 999.99]),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 55 minutes'))
        ],
        [
            'user_id' => 1,
            'action_type' => 'create_category',
            'description' => 'Created new category: Electronics',
            'affected_table' => 'categories',
            'affected_id' => 45,
            'new_value' => json_encode(['name' => 'Electronics']),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 50 minutes'))
        ],
        [
            'user_id' => 1,
            'action_type' => 'update_product',
            'description' => 'Updated product: iPhone 13 Pro price from $999.99 to $899.99',
            'affected_table' => 'products',
            'affected_id' => 123,
            'old_value' => json_encode(['price' => 999.99]),
            'new_value' => json_encode(['price' => 899.99]),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 45 minutes'))
        ],
        [
            'user_id' => 1,
            'action_type' => 'add_stock',
            'description' => 'Added 50 units of iPhone 13 Pro',
            'affected_table' => 'products',
            'affected_id' => 123,
            'new_value' => json_encode(['quantity' => 50]),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 40 minutes'))
        ],
        [
            'user_id' => 1,
            'action_type' => 'create_user',
            'description' => 'Created new cashier user: John Doe',
            'affected_table' => 'users',
            'affected_id' => 2,
            'new_value' => json_encode(['name' => 'John Doe', 'role' => 'cashier']),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 35 minutes'))
        ],
        
        // Cashier actions
        [
            'user_id' => 2,
            'action_type' => 'cashier_login',
            'description' => 'Cashier John Doe logged in',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes'))
        ],
        [
            'user_id' => 2,
            'action_type' => 'cashier_sale',
            'description' => 'Created new sale #789 for $150.00',
            'affected_table' => 'sales',
            'affected_id' => 789,
            'new_value' => json_encode(['total' => 150.00, 'items' => ['iPhone 13 Pro', 'AirPods']]),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 25 minutes'))
        ],
        [
            'user_id' => 2,
            'action_type' => 'cashier_void',
            'description' => 'Voided sale #789 due to customer request',
            'affected_table' => 'sales',
            'affected_id' => 789,
            'old_value' => json_encode(['status' => 'completed']),
            'new_value' => json_encode(['status' => 'voided']),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 20 minutes'))
        ],
        [
            'user_id' => 2,
            'action_type' => 'cashier_logout',
            'description' => 'Cashier John Doe logged out',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 15 minutes'))
        ],
        
        // Admin actions continued
        [
            'user_id' => 1,
            'action_type' => 'view_reports',
            'description' => 'Viewed sales report for January 2024',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 10 minutes'))
        ],
        [
            'user_id' => 1,
            'action_type' => 'edit_settings',
            'description' => 'Updated system settings: Changed tax rate to 8%',
            'affected_table' => 'settings',
            'affected_id' => 1,
            'old_value' => json_encode(['tax_rate' => 7]),
            'new_value' => json_encode(['tax_rate' => 8]),
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour 5 minutes'))
        ],
        [
            'user_id' => 1,
            'action_type' => 'logout',
            'description' => 'Admin user logged out',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]
    ];
    
    $insertQuery = "INSERT INTO activity_logs (
        user_id, 
        action_type, 
        description, 
        ip_address, 
        created_at,
        affected_table,
        affected_id,
        old_value,
        new_value
    ) VALUES (
        :user_id,
        :action_type,
        :description,
        :ip_address,
        :created_at,
        :affected_table,
        :affected_id,
        :old_value,
        :new_value
    )";
    $stmt = $pdo->prepare($insertQuery);
    
    foreach ($testLogs as $log) {
        $stmt->execute([
            'user_id' => $log['user_id'],
            'action_type' => $log['action_type'],
            'description' => $log['description'],
            'ip_address' => $log['ip_address'],
            'created_at' => $log['created_at'],
            'affected_table' => $log['affected_table'] ?? null,
            'affected_id' => $log['affected_id'] ?? null,
            'old_value' => $log['old_value'] ?? null,
            'new_value' => $log['new_value'] ?? null
        ]);
    }
    
    echo "Test data inserted successfully!<br>";
    echo "Added " . count($testLogs) . " test logs with timestamps spread over the last 2 hours.<br>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 