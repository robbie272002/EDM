<?php
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        
        // Update the product stock
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock = stock + ? 
            WHERE id = ?
        ");
        
        $stmt->execute([$quantity, $product_id]);

        // Log the restock activity
        $stmt = $pdo->prepare("
            INSERT INTO stock_logs (product_id, quantity, type, created_at)
            VALUES (?, ?, 'restock', NOW())
        ");
        
        $stmt->execute([$product_id, $quantity]);

        // Redirect back to products page with success message
        header('Location: index.php?success=Product restocked successfully');
        exit;
    } catch (PDOException $e) {
        // Redirect back with error message
        header('Location: index.php?error=Failed to restock product: ' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // If not POST request, redirect to products page
    header('Location: index.php');
    exit;
} 