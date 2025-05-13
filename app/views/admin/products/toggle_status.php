<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/../../../helpers/logger.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && isset($_POST['current_status'])) {
    try {
        $product_id = $_POST['product_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        // Fetch product data for logging
        $stmt = $pdo->prepare("SELECT name, image FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get just the filename from the image path
        $image_filename = basename($product['image']);
        
        // Update product status
        $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
        $result = $stmt->execute([$new_status, $product_id]);
        
        if ($result) {
            // Create a more readable description
            $action = $new_status === 'active' ? 'activated' : 'deactivated';
            $desc = "The product '{$product['name']}' has been {$action}";
            
            // Create more readable old and new values
            $oldValue = [
                'status' => "The product was previously {$current_status}",
                'image' => $image_filename
            ];
            $newValue = [
                'status' => "The product is now {$new_status}",
                'image' => $image_filename
            ];
            
            // Log the activity with readable values
            $logResult = logActivity(
                $_SESSION['user_id'],
                'update_product',
                $desc,
                'products',
                $product_id,
                $oldValue,
                $newValue
            );
            
            if (!$logResult) {
                error_log("Failed to log product status change");
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $new_status === 'active' ? 'Product activated successfully' : 'Product deactivated successfully'
            ]);
        } else {
            throw new Exception("Failed to update product status");
        }
    } catch (Exception $e) {
        error_log("Error in toggle_status.php: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit; 