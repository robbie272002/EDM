<?php
require_once __DIR__ . '/app/config/database.php';

echo "<h1>Database Connection Test</h1>";
echo "<pre>";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo->query("SELECT 1");
    echo "Database connection successful!\n\n";

    // Check if users table exists
    echo "Checking users table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "Users table exists!\n";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE users");
        echo "\nTable structure:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
        
        // Check if admin user exists
        $stmt = $pdo->query("SELECT id, username, role FROM users WHERE username = 'admin'");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "\nAdmin user exists:\n";
            echo "ID: {$row['id']}\n";
            echo "Username: {$row['username']}\n";
            echo "Role: {$row['role']}\n";
        } else {
            echo "\nAdmin user not found!\n";
        }
    } else {
        echo "Users table does not exist!\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>"; 