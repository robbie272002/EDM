<?php
session_start();

// If user is already logged in, redirect to appropriate page
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'cashier') {
        header('Location: /NEW/app/views/cashier/pos.php');
    } else {
        header('Location: /NEW/app/views/admin/dashboard.php');
    }
    exit;
}

// Handle AJAX login requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../../config/database.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Please enter both username and password']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, name, role, status FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if user is deactivated
                if ($user['status'] === 'inactive') {
                    echo json_encode(['success' => false, 'message' => 'This account was deactivated by the admin']);
                    exit;
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                // Log login activity
                require_once __DIR__ . '/../../helpers/logger.php';
                logActivity(
                    $user['id'],
                    'login',
                    ucfirst($user['role']) . ' user logged in',
                    null, // affected_table
                    null, // affected_id
                    null, // old_value
                    null  // new_value
                );
                
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'username' => $user['username'],
                        'name' => $user['name'],
                        'role' => $user['role']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred during login']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Retail POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
        }
        .input-group input {
            padding-left: 40px;
        }
        .btn-login {
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .btn-login:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-100 to-white h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-8">
            <div class="mb-4">
                <i class="fas fa-store text-4xl text-indigo-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Retail POS System</h1>
            <p class="text-gray-600">Welcome back! Please login to continue</p>
        </div>
        
        <form id="loginForm" class="space-y-6">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" 
                       id="username" 
                       name="username" 
                       required
                       placeholder="Enter your username"
                       class="w-full h-12 rounded-lg border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors duration-200">
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required
                       placeholder="Enter your password"
                       class="w-full h-12 rounded-lg border-2 border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors duration-200">
            </div>
            
            <div id="error-message" class="hidden p-4 mb-4 text-sm text-red-700 bg-red-50 rounded-lg border border-red-200" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
                    <span></span>
                </div>
            </div>
            
            <button type="submit"
                    class="btn-login w-full h-12 flex justify-center items-center rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                <span class="mr-2">Login</span>
                <i class="fas fa-sign-in-alt"></i>
            </button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const errorDiv = document.getElementById('error-message');
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnContent = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Signing in...
            `;
            submitBtn.disabled = true;
            
            fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add success animation before redirect
                    submitBtn.innerHTML = `
                        <i class="fas fa-check mr-2"></i>
                        Success!
                    `;
                    submitBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                    submitBtn.classList.add('bg-green-600');
                    
                    // Redirect after a short delay
                    setTimeout(() => {
                        if (data.user.role === 'cashier') {
                            window.location.href = '../cashier/pos.php';
                        } else {
                            window.location.href = '../admin/dashboard.php';
                        }
                    }, 500);
                } else {
                    errorDiv.querySelector('span').textContent = data.message;
                    errorDiv.classList.remove('hidden');
                    // Reset button state
                    submitBtn.innerHTML = originalBtnContent;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                errorDiv.querySelector('span').textContent = 'An error occurred. Please try again.';
                errorDiv.classList.remove('hidden');
                // Reset button state
                submitBtn.innerHTML = originalBtnContent;
                submitBtn.disabled = false;
                console.error('Login error:', error);
            });
        });
    </script>
</body>
</html> 