<?php
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/../../../helpers/logger.php';

// Check if user is logged in and is admin
try {
    $user = checkAuth('admin');
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication error']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

$id = $data['id'];

try {
    // First, check if there are any products in this category
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$id]);
    $productCount = $stmt->fetchColumn();

    if ($productCount > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Cannot delete category with associated products']);
        exit();
    }

    // Get category info for logging
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no products are associated, delete the category
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $result = $stmt->execute([$id]);

    if ($result) {
        // Log the activity
        error_log('About to log category delete activity');
        logCategoryActivity(
            $user['id'],
            'delete_category',
            $id,
            "Deleted category: {$category['name']}",
            $category,
            null
        );
        error_log('Logged category delete activity');

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to delete category']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 