<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/database.php';

try {
    // Get table structure
    echo "Checking activity_logs table structure:<br><br>";
    $stmt = $pdo->query("SHOW CREATE TABLE activity_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<pre>";
        print_r($result);
        echo "</pre>";
    } else {
        echo "Table does not exist!";
    }
    
    // Get table contents
    echo "<br>Recent activity logs:<br><br>";
    $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($logs) {
        echo "<pre>";
        print_r($logs);
        echo "</pre>";
    } else {
        echo "No logs found!";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
