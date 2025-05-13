<?php
require_once __DIR__ . '/../config/database.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

/**
 * Log an activity in the system
 * 
 * @param int $userId The ID of the user performing the action
 * @param string $actionType The type of action (must match ENUM in activity_logs table)
 * @param string $description A human-readable description of the action
 * @param string|null $affectedTable The database table affected by the action
 * @param int|null $affectedId The ID of the affected record
 * @param array|null $oldValue The previous value(s) before the action
 * @param array|null $newValue The new value(s) after the action
 * @return bool True if logging was successful, false otherwise
 */
function logActivity($userId, $actionType, $description, $affectedTable = null, $affectedId = null, $oldValue = null, $newValue = null) {
    global $pdo;
    
    try {
        // Debug information
        error_log("=== Starting Activity Log ===");
        error_log("User ID: " . $userId);
        error_log("Action Type: " . $actionType);
        error_log("Description: " . $description);
        error_log("Affected Table: " . $affectedTable);
        error_log("Affected ID: " . $affectedId);
        error_log("Old Value: " . ($oldValue ? json_encode($oldValue) : 'null'));
        error_log("New Value: " . ($newValue ? json_encode($newValue) : 'null'));

        // Verify action_type is valid
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns 
            WHERE table_schema = DATABASE()
            AND table_name = 'activity_logs' 
            AND column_name = 'action_type' 
            AND FIND_IN_SET(?, REPLACE(SUBSTRING(column_type, 6, LENGTH(column_type) - 6), '\\'', ''))");
        $stmt->execute([$actionType]);
        if ($stmt->fetchColumn() == 0) {
            error_log("Invalid action_type: " . $actionType);
            error_log("Action type not found in ENUM values");
            return false;
        }

        $query = "INSERT INTO activity_logs (
            user_id, 
            action_type, 
            description, 
            ip_address, 
            user_agent,
            affected_table,
            affected_id,
            old_value,
            new_value
        ) VALUES (
            :user_id,
            :action_type,
            :description,
            :ip_address,
            :user_agent,
            :affected_table,
            :affected_id,
            :old_value,
            :new_value
        )";

        $stmt = $pdo->prepare($query);
        
        $params = [
            'user_id' => $userId,
            'action_type' => $actionType,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'affected_table' => $affectedTable,
            'affected_id' => $affectedId,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null
        ];

        error_log("SQL Query: " . $query);
        error_log("SQL Parameters: " . print_r($params, true));
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            error_log("Failed to execute log query");
            error_log("Error Info: " . print_r($stmt->errorInfo(), true));
            return false;
        }

        error_log("Activity logged successfully");
        error_log("=== End Activity Log ===");
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        error_log("Error Info: " . print_r($e->errorInfo, true));
        error_log("=== End Activity Log with Error ===");
        return false;
    }
}

/**
 * Log a product-related activity
 */
function logProductActivity($userId, $action, $productId, $description, $oldValue = null, $newValue = null) {
    return logActivity(
        $userId,
        $action,
        $description,
        'products',
        $productId,
        $oldValue,
        $newValue
    );
}

/**
 * Log a category-related activity
 */
function logCategoryActivity($userId, $action, $categoryId, $description, $oldValue = null, $newValue = null) {
    error_log("=== Starting Category Activity Log ===");
    error_log("Action: " . $action);
    error_log("Category ID: " . $categoryId);
    error_log("Description: " . $description);
    error_log("Old Value: " . ($oldValue ? json_encode($oldValue) : 'null'));
    error_log("New Value: " . ($newValue ? json_encode($newValue) : 'null'));
    
    $result = logActivity(
        $userId,
        $action,
        $description,
        'categories',
        $categoryId,
        $oldValue,
        $newValue
    );
    
    error_log("Category Activity Log Result: " . ($result ? 'success' : 'failed'));
    error_log("=== End Category Activity Log ===");
    return $result;
}

/**
 * Log a user-related activity
 */
function logUserActivity($userId, $action, $targetUserId, $description, $oldValue = null, $newValue = null) {
    return logActivity(
        $userId,
        $action,
        $description,
        'users',
        $targetUserId,
        $oldValue,
        $newValue
    );
}

/**
 * Log a sale-related activity
 */
function logSaleActivity($userId, $action, $saleId, $description, $oldValue = null, $newValue = null) {
    return logActivity(
        $userId,
        $action,
        $description,
        'sales',
        $saleId,
        $oldValue,
        $newValue
    );
}

/**
 * Log a stock-related activity
 */
function logStockActivity($userId, $action, $productId, $description, $oldValue = null, $newValue = null) {
    return logActivity(
        $userId,
        $action,
        $description,
        'products',
        $productId,
        $oldValue,
        $newValue
    );
}

/**
 * Log a cashier-related activity
 */
function logCashierActivity($userId, $action, $description, $oldValue = null, $newValue = null) {
    return logActivity(
        $userId,
        $action,
        $description,
        null,
        null,
        $oldValue,
        $newValue
    );
}

/**
 * Log a system settings activity
 */
function logSettingsActivity($userId, $action, $description, $oldValue = null, $newValue = null) {
    return logActivity(
        $userId,
        $action,
        $description,
        'settings',
        null,
        $oldValue,
        $newValue
    );
} 