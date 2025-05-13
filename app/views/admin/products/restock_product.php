<?php
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = $_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity <= 0) {
            throw new Exception('Quantity must be greater than 0');
        }
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $stmt->execute([$quantity, $product_id]);

        // Log restock activity
        session_start();
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../../../helpers/logger.php';
            logStockActivity(
                $_SESSION['user_id'],
                'add_stock',
                $product_id,
                null, // old_value
                json_encode(['quantity_added' => $quantity])
            );
        }
        
        header('Location: index.php?success=Stock updated successfully');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
} 