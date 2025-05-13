<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: /NEW/app/views/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $errors = [];

    // Validate input
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    if (empty($username)) {
        $errors[] = "Username is required";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username already exists";
        }
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    if (!in_array($role, ['admin', 'cashier'])) {
        $errors[] = "Invalid role selected";
    }

    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $username, $hashed_password, $role]);

            // Redirect back to users page with success message
            $_SESSION['success_message'] = "User created successfully";
            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        header('Location: index.php');
        exit();
    }
} else {
    // If not POST request, redirect to users page
    header('Location: index.php');
    exit();
} 