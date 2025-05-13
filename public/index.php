<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header('Location: /NEW/app/views/admin/dashboard.php');
    } else {
        header('Location: /NEW/app/views/cashier/pos.php');
    }
    exit;
}

// If not logged in, redirect to the main login page
header('Location: /NEW/app/views/auth/login.php');
exit;
?> 