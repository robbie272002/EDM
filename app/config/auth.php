<?php
session_start();

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is cashier
 */
function isCashier() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'cashier';
}

/**
 * Get current user's ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user's name
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Require admin access
 * Redirects to login if not admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /NEW/app/views/auth/login.php');
        exit();
    }
}

/**
 * Require login
 * Redirects to login if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /NEW/app/views/auth/login.php');
        exit();
    }
}

/**
 * Set user session data
 */
function setUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
}

/**
 * Clear user session data
 */
function clearUserSession() {
    unset($_SESSION['user_id']);
    unset($_SESSION['role']);
    unset($_SESSION['name']);
    session_destroy();
} 