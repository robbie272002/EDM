<?php
namespace app\middleware;

class AuthMiddleware {
    public function handle() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
    
    public function checkRole($role) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            header('Location: /login');
            exit;
        }
    }
} 