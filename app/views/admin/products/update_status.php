<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Check if user is logged in and is admin
try {
    $user = checkAuth('admin');
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$product_id = $_POST['product_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$product_id || !in_array($status, ['active', 'inactive'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $product_id]);

    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update product status']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 