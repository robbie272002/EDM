<?php
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = $_POST['product_id'];
        
        // Get product image path before deleting
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $image_path = $stmt->fetchColumn();
        
        // Delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        // Delete the product image if it exists
        if ($image_path && file_exists('C:/xampp/htdocs/NEW' . $image_path)) {
            unlink('C:/xampp/htdocs/NEW' . $image_path);
        }
        
        header('Location: index.php?success=Product deleted successfully');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
} 