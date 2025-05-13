<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/logger.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Insert a test category
    $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, ?)");
    $result = $stmt->execute(['Test Category', 'This is a test category', 'active']);
    
    if ($result) {
        $categoryId = $pdo->lastInsertId();
        echo "Created category with ID: $categoryId\n";
        
        // Log the activity
        $logResult = logCategoryActivity(
            1, // Admin user ID
            'create_category',
            $categoryId,
            "Created test category",
            null,
            [
                'name' => 'Test Category',
                'description' => 'This is a test category',
                'status' => 'active'
            ]
        );
        
        echo $logResult ? "Activity logged successfully\n" : "Failed to log activity\n";
        
        // Update the category
        $stmt = $pdo->prepare("UPDATE categories SET description = ? WHERE id = ?");
        $result = $stmt->execute(['Updated test description', $categoryId]);
        
        if ($result) {
            echo "Updated category\n";
            
            // Log the update
            $logResult = logCategoryActivity(
                1,
                'update_category',
                $categoryId,
                "Updated test category",
                ['description' => 'This is a test category'],
                ['description' => 'Updated test description']
            );
            
            echo $logResult ? "Update activity logged successfully\n" : "Failed to log update activity\n";
        }
        
        // Delete the category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $result = $stmt->execute([$categoryId]);
        
        if ($result) {
            echo "Deleted category\n";
            
            // Log the deletion
            $logResult = logCategoryActivity(
                1,
                'delete_category',
                $categoryId,
                "Deleted test category",
                [
                    'name' => 'Test Category',
                    'description' => 'Updated test description',
                    'status' => 'active'
                ],
                null
            );
            
            echo $logResult ? "Delete activity logged successfully\n" : "Failed to log delete activity\n";
        }
    }
    
    // Check the logs
    echo "\nActivity Logs:\n";
    $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    while ($log = $stmt->fetch()) {
        echo "\nAction: {$log['action_type']}\n";
        echo "Description: {$log['description']}\n";
        echo "Created At: {$log['created_at']}\n";
        echo "----------------------------------------\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    print_r($e->errorInfo);
} 