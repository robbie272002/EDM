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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Login</title>
    <!-- Replace CDN with proper Tailwind CSS -->
    <link href="/NEW/public/assets/css/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/NEW/public/assets/css/styles.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-lg">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900">POS System</h2>
                <p class="mt-2 text-sm text-gray-600">Please sign in to your account</p>
            </div>
            <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert"></div>
            <form id="loginForm" class="space-y-4">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="username" class="sr-only">Username</label>
                        <input id="username" name="username" type="text" required 
                            class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                            placeholder="Username">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" required 
                            class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                            placeholder="Password">
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-lock"></i>
                        </span>
                        Sign in
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/NEW/public/assets/js/config.js"></script>
    <script src="/NEW/public/assets/js/auth.js"></script>
</body>
</html> 