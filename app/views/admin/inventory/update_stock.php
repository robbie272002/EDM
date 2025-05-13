<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Debug logging
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in and is admin
try {
    $user = checkAuth('admin');
    error_log("User authenticated: " . print_r($user, true));
} catch (Exception $e) {
    error_log("Authentication error: " . $e->getMessage());
    $_SESSION['error_message'] = "Authentication error: " . $e->getMessage();
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    $_SESSION['error_message'] = "Invalid request method";
    header('Location: index.php');
    exit();
}

// Debug logging
error_log("POST data: " . print_r($_POST, true));

$product_id = $_POST['product_id'] ?? null;
$action = $_POST['action'] ?? null;
$quantity = (int)($_POST['quantity'] ?? 0);
$notes = $_POST['notes'] ?? 'Manual stock adjustment';

// Debug logging
error_log("Stock Update Request - Product ID: $product_id, Action: $action, Quantity: $quantity, Notes: $notes");

// Validate input parameters
if (!$product_id || !$action) {
    $_SESSION['error_message'] = "Invalid input parameters - Product ID: $product_id, Action: $action";
    header('Location: index.php');
    exit();
}

// Validate quantity based on action
if ($action === 'add' && $quantity <= 0) {
    $_SESSION['error_message'] = "Quantity must be greater than 0 for restock";
    header('Location: index.php');
    exit();
} elseif ($action === 'set' && $quantity < 0) {
    $_SESSION['error_message'] = "Stock cannot be negative";
    header('Location: index.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // Get current stock with a lock to prevent race conditions
    $stmt = $pdo->prepare("SELECT stock, name FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception("Product not found or inactive");
    }

    $current_stock = (int)$product['stock'];
    $new_stock = $current_stock;

    // Debug logging
    error_log("Current stock: $current_stock, New stock will be: $new_stock");

    // Calculate new stock based on action
    switch ($action) {
        case 'add':
            $new_stock = $current_stock + $quantity;
            break;
        case 'set':
            if ($quantity < 0) {
                throw new Exception("Stock cannot be negative");
            }
            $new_stock = $quantity;
            break;
        default:
            throw new Exception("Invalid action");
    }

    // Debug logging
    error_log("Final new stock value: $new_stock");

    // Update stock in products table
    $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ? AND status = 'active'");
    $result = $stmt->execute([$new_stock, $product_id]);

    if (!$result) {
        throw new Exception("Failed to update stock");
    }

    // Log stock adjustment activity
    require_once __DIR__ . '/../../../helpers/logger.php';
    $actionType = $action === 'add' ? 'add_stock' : 'adjust_stock';
    $description = $actionType === 'add_stock'
        ? "Restocked $quantity units to Product #$product_id"
        : "Adjusted stock for Product #$product_id from $current_stock to $new_stock ($notes)";
    logStockActivity(
        $_SESSION['user_id'],
        $actionType,
        $product_id,
        $description,
        ['previous_stock' => $current_stock],
        ['new_stock' => $new_stock, 'quantity' => $quantity, 'notes' => $notes]
    );

    // Insert into stock_history table
    $stmt = $pdo->prepare("
        INSERT INTO stock_history (product_id, user_id, action, quantity, previous_stock, new_stock, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $product_id,
        $_SESSION['user_id'],
        $action,
        $quantity,
        $current_stock,
        $new_stock,
        $notes
    ]);

    // Insert into inventory_logs table
    $stmt = $pdo->prepare("
        INSERT INTO inventory_logs (product_id, type, quantity, reason, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $product_id,
        $action === 'add' ? 'restock' : 'adjustment',
        $quantity,
        $notes,
        "Stock updated from {$current_stock} to {$new_stock}"
    ]);

    // Check for low stock alert
    if ($new_stock < 10) {
        $stmt = $pdo->prepare("
            INSERT INTO stock_alerts (product_id, alert_type, message)
            VALUES (?, 'low_stock', ?)
        ");
        $message = "Low stock alert: {$product['name']} has {$new_stock} units remaining";
        $stmt->execute([$product_id, $message]);
    }

    $pdo->commit();
    $_SESSION['success_message'] = "Stock updated successfully from {$current_stock} to {$new_stock}";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Error updating stock: " . $e->getMessage();
}

// Redirect back to inventory page
header('Location: index.php');
exit(); 