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

if (!$data || !isset($data['id']) || !isset($data['name'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    // Get old values for logging
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$data['id']]);
    $oldValues = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
    $result = $stmt->execute([$data['name'], $data['description'] ?? null, $data['id']]);

    if ($result) {
        // Log the activity
        logCategoryActivity(
            $user['id'],
            'update_category',
            $data['id'],
            "Updated category: {$data['name']}",
            $oldValues,
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null
            ]
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Category updated successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 