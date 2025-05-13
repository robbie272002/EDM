<?php
require_once __DIR__ . '/app/config/database.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Get count of logs by action type
    $stmt = $pdo->query("SELECT action_type, COUNT(*) as count FROM activity_logs GROUP BY action_type ORDER BY count DESC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Activity Log Summary:\n";
    echo "====================\n";
    foreach ($results as $row) {
        echo "{$row['action_type']}: {$row['count']}\n";
    }
    
    echo "\nRecent Category Logs:\n";
    echo "====================\n";
    $stmt = $pdo->query("
        SELECT al.*, u.name as user_name 
        FROM activity_logs al 
        JOIN users u ON al.user_id = u.id 
        WHERE al.action_type IN ('create_category', 'update_category', 'delete_category')
        ORDER BY al.created_at DESC 
        LIMIT 10
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($logs as $log) {
        echo "\nAction: {$log['action_type']}\n";
        echo "User: {$log['user_name']}\n";
        echo "Description: {$log['description']}\n";
        echo "Created At: {$log['created_at']}\n";
        if ($log['old_value']) echo "Old Value: {$log['old_value']}\n";
        if ($log['new_value']) echo "New Value: {$log['new_value']}\n";
        echo "----------------------------------------\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    print_r($e->errorInfo);
} 