<?php
session_start();

// Log logout activity before clearing session
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    require_once __DIR__ . '/../../helpers/logger.php';
    logActivity(
        $_SESSION['user_id'],
        'logout',
        ucfirst($_SESSION['role']) . ' user logged out',
        null, // affected_table
        null, // affected_id
        null, // old_value
        null  // new_value
    );
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page using relative path
header('Location: login.php');
exit;
?> 