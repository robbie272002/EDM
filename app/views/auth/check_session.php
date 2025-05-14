<?php
session_start();

function checkAuth($requiredRole = null) {
    // Check if it's an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    error_log("=== Auth Check ===");
    error_log("Session ID: " . session_id());
    error_log("Session Data: " . print_r($_SESSION, true));
    error_log("Is AJAX: " . ($isAjax ? 'yes' : 'no'));
    error_log("Required Role: " . ($requiredRole ?? 'none'));
    
    if (!isset($_SESSION['user_id'])) {
        error_log("No user_id in session");
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        } else {
            header('Location: /index.php');
            exit;
        }
    }
    
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        // Allow super_admin to access admin pages
        if (!($requiredRole === 'admin' && $_SESSION['role'] === 'super_admin')) {
            error_log("Role mismatch. User role: {$_SESSION['role']}, Required: $requiredRole");
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                exit;
            } else {
                header('Location: /index.php');
                exit;
            }
        }
    }
    
    $user = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'name' => $_SESSION['name'],
        'role' => $_SESSION['role']
    ];
    
    error_log("Auth check passed. User: " . print_r($user, true));
    error_log("=================");
    
    return $user;
}
?> 