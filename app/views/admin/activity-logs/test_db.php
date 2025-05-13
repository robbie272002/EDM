<?php
require_once __DIR__ . '/../../../config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test database connection
    echo "Testing database connection...<br>";
    $pdo->query("SELECT 1");
    echo "Database connection successful!<br><br>";

    // Check if activity_logs table exists
    echo "Checking activity_logs table...<br>";
    $result = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
    if ($result->rowCount() > 0) {
        echo "activity_logs table exists!<br><br>";
        
        // Show table structure
        echo "Table structure:<br>";
        $result = $pdo->query("DESCRIBE activity_logs");
        echo "<pre>";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre><br>";
        
        // Count records
        $result = $pdo->query("SELECT COUNT(*) as count FROM activity_logs");
        $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Number of records in activity_logs: " . $count . "<br>";
        
        // Show sample records
        if ($count > 0) {
            echo "<br>Sample records:<br>";
            $result = $pdo->query("SELECT * FROM activity_logs LIMIT 5");
            echo "<pre>";
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                print_r($row);
            }
            echo "</pre>";
        }
    } else {
        echo "activity_logs table does not exist!<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 