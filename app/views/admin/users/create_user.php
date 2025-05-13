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

if (!$data || !isset($data['username']) || !isset($data['password']) || !isset($data['name']) || !isset($data['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit();
    }

    // Hash the password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$data['username'], $hashedPassword, $data['name'], $data['role']]);

    if ($result) {
        $userId = $pdo->lastInsertId();
        
        // Log the activity
        logUserActivity(
            $user['id'],
            'create_user',
            $userId,
            "Created new {$data['role']} user: {$data['name']}",
            null,
            [
                'username' => $data['username'],
                'name' => $data['name'],
                'role' => $data['role']
            ]
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'user_id' => $userId,
            'message' => 'User created successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 