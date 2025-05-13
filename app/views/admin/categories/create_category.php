<?php
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

if (!$data || !isset($data['name'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    $result = $stmt->execute([$data['name'], $data['description'] ?? null]);

    if ($result) {
        $categoryId = $pdo->lastInsertId();
        
        // Log the activity
        logCategoryActivity(
            $user['id'],
            'create_category',
            $categoryId,
            "Created new category: {$data['name']}",
            null,
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null
            ]
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'category_id' => $categoryId,
            'message' => 'Category created successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create category']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 