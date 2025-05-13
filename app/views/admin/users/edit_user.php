<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/auth.php';

// Check if user is admin
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
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
        // Check if username already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username already exists";
        }
    }

    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    if (!in_array($role, ['admin', 'cashier'])) {
        $errors[] = "Invalid role selected";
    }

    // Prevent changing the last admin's role to cashier
    if ($role === 'cashier') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?");
        $stmt->execute([$user_id]);
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount === 0) {
            $errors[] = "Cannot change role to cashier. At least one admin must remain in the system.";
        }
    }

    if (empty($errors)) {
        try {
            // Start building the update query
            $query = "UPDATE users SET name = ?, username = ?, role = ?";
            $params = [$name, $username, $role];

            // Only update password if a new one is provided
            if (!empty($password)) {
                $query .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $query .= " WHERE id = ?";
            $params[] = $user_id;

            // Execute update
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            // Redirect back to users page with success message
            $_SESSION['success_message'] = "User updated successfully";
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