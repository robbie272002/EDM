<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/logger.php';

echo "Starting to add test logs...<br>";

// Add some test logs
$testLogs = [
    [
        'user_id' => 1, // Admin user
        'action_type' => 'login',
        'description' => 'Admin user logged in successfully'
    ],
    [
        'user_id' => 2, // Cashier user
        'action_type' => 'add_stock',
        'description' => 'Added 50 units of Product #123'
    ],
    [
        'user_id' => 1,
        'action_type' => 'edit_product',
        'description' => 'Updated price of Product #456 from $10.99 to $12.99'
    ],
    [
        'user_id' => 2,
        'action_type' => 'create_sale',
        'description' => 'Created new sale #789 for $150.00'
    ],
    [
        'user_id' => 1,
        'action_type' => 'add_user',
        'description' => 'Added new cashier user: John Doe'
    ]
];

$successCount = 0;
$errorCount = 0;

foreach ($testLogs as $log) {
    try {
        $result = logActivity(
            $log['user_id'],
            $log['action_type'],
            $log['description']
        );
        
        if ($result) {
            $successCount++;
            echo "Successfully added log: {$log['description']}<br>";
        } else {
            $errorCount++;
            echo "Failed to add log: {$log['description']}<br>";
        }
    } catch (Exception $e) {
        $errorCount++;
        echo "Error adding log: " . $e->getMessage() . "<br>";
    }
}

echo "<br>Test logs completed!<br>";
echo "Successfully added: $successCount<br>";
echo "Failed to add: $errorCount<br>";
?> 