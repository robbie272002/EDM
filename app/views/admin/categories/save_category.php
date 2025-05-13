<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session state
error_log("=== Session Debug ===");
error_log("Session ID: " . session_id());
error_log("Session Data: " . print_r($_SESSION, true));
error_log("===================");

// Prevent any output before headers
ob_start();

// Enable error display in output for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../../logs/php_errors.log');

error_log("=== Starting Category Save Operation ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
error_log("Raw POST data: " . file_get_contents('php://input'));

// Function to send JSON response and exit
function sendJsonResponse($success, $message = null, $data = null) {
    $response = ['success' => $success];
    if ($message !== null) $response['message'] = $message;
    if ($data !== null) $response['data'] = $data;
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("Sending response: " . json_encode($response));
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/../../../helpers/logger.php';

// Check if user is logged in and is admin
try {
    $user = checkAuth('admin');
    error_log("User authenticated: " . print_r($user, true));
} catch (Exception $e) {
    error_log("Authentication failed: " . $e->getMessage());
    sendJsonResponse(false, 'Authentication error: ' . $e->getMessage());
}

// Get JSON data from request
$input = file_get_contents('php://input');
error_log("Raw input data: " . $input);

$data = json_decode($input, true);
error_log("Decoded data: " . print_r($data, true));

if (!$data) {
    error_log("Invalid JSON data: " . json_last_error_msg());
    sendJsonResponse(false, 'Invalid input data: ' . json_last_error_msg());
}

$name = trim($data['name'] ?? '');
$description = trim($data['description'] ?? '');
$id = $data['id'] ?? null;
$status = $data['status'] ?? 'active';

error_log("Processed input data:");
error_log("Name: " . $name);
error_log("Description: " . $description);
error_log("ID: " . ($id ?? 'null'));
error_log("Status: " . $status);

if (empty($name)) {
    error_log("Validation failed: Empty category name");
    sendJsonResponse(false, 'Category name is required');
}

try {
    // Start transaction
    error_log("Starting database transaction");
    $pdo->beginTransaction();

    // Check if category name already exists (for new categories)
    if (!$id) {
        error_log("Checking for duplicate category name");
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            error_log("Duplicate category name found");
            $pdo->rollBack();
            sendJsonResponse(false, 'Category name already exists');
        }
    }

    if ($id) {
        // Update existing category
        error_log("Updating existing category (ID: $id)");
        
        // Fetch old category data for logging
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $oldCategory = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Old category data: " . print_r($oldCategory, true));

        if (!$oldCategory) {
            error_log("Category not found for update");
            $pdo->rollBack();
            sendJsonResponse(false, 'Category not found');
        }

        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
        $result = $stmt->execute([$name, $description, $status, $id]);
        error_log("Update query executed. Result: " . ($result ? 'success' : 'failed'));
        
        if (!$result) {
            error_log("Update error info: " . print_r($stmt->errorInfo(), true));
        }

        // Log category update activity
        if ($result) {
            error_log("Attempting to log category update");
            $desc = "Category '{$name}' has been updated";
            $logResult = logCategoryActivity(
                $user['id'],
                'update_category',
                $id,
                $desc,
                $oldCategory,
                [
                    'name' => $name,
                    'description' => $description,
                    'status' => $status
                ]
            );
            error_log("Logging result: " . ($logResult ? 'success' : 'failed'));
            
            if (!$logResult) {
                error_log("Failed to log update activity, rolling back");
                $pdo->rollBack();
                sendJsonResponse(false, 'Failed to log category update');
            }
        }
    } else {
        // Insert new category
        error_log("Creating new category");
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, ?)");
        $result = $stmt->execute([$name, $description, $status]);
        error_log("Insert query executed. Result: " . ($result ? 'success' : 'failed'));
        
        if (!$result) {
            error_log("Insert error info: " . print_r($stmt->errorInfo(), true));
        }

        if ($result) {
            $newId = $pdo->lastInsertId();
            error_log("New category ID: $newId");
            
            // Log category creation activity
            error_log("Attempting to log category creation");
            $desc = "New category '{$name}' has been created";
            $logResult = logCategoryActivity(
                $user['id'],
                'create_category',
                $newId,
                $desc,
                null,
                [
                    'name' => $name,
                    'description' => $description,
                    'status' => $status
                ]
            );
            error_log("Logging result: " . ($logResult ? 'success' : 'failed'));
            
            if (!$logResult) {
                error_log("Failed to log creation activity, rolling back");
                $pdo->rollBack();
                sendJsonResponse(false, 'Failed to log category creation');
            }
        }
    }

    if ($result) {
        error_log("Operation successful, committing transaction");
        $pdo->commit();
        sendJsonResponse(true, 'Category ' . ($id ? 'updated' : 'created') . ' successfully');
    } else {
        error_log("Operation failed, rolling back transaction");
        $pdo->rollBack();
        error_log("Database error info: " . print_r($stmt->errorInfo(), true));
        sendJsonResponse(false, 'Failed to ' . ($id ? 'update' : 'create') . ' category');
    }
} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Info: " . print_r($e->errorInfo, true));
    if ($pdo->inTransaction()) {
        error_log("Rolling back transaction due to error");
        $pdo->rollBack();
    }
    sendJsonResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Unexpected error: " . $e->getMessage());
    if ($pdo->inTransaction()) {
        error_log("Rolling back transaction due to error");
        $pdo->rollBack();
    }
    sendJsonResponse(false, 'An unexpected error occurred');
} finally {
    error_log("=== End Category Save Operation ===");
} 