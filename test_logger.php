<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/logger.php';

echo "Attempting to log a test category creation...\n";

// Simulate a user ID (e.g., admin user ID 1)
$testUserId = 1;
$testAction = 'create_category';
$testCategoryId = 999;
$testDescription = "Test: Admin created a dummy category via test script.";
$testNewValue = ['name' => 'Test Category', 'description' => 'A test', 'status' => 'active'];

$success = logCategoryActivity($testUserId, $testAction, $testCategoryId, $testDescription, null, $testNewValue);

if ($success) {
    echo "Test log entry SUCCESSFUL.\n";
} else {
    echo "Test log entry FAILED. Check PHP error log and logger.php's error_log messages.\n";
} 