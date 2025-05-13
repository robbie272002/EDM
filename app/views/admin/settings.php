<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

// Check if user is logged in
requireLogin();

// Get current user's information
$currentUserId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.*, us.* FROM users u LEFT JOIN user_settings us ON u.id = us.user_id WHERE u.id = ?");
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    // Validate current password
    if (!empty($current_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }

    // Validate new password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
    }

    // Check if username already exists
    if ($username !== $user['username']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $currentUserId]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already exists";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Get current user data for logging
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$currentUserId]);
            $oldUserData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!empty($new_password)) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password = ? WHERE id = ?");
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt->execute([$name, $username, $hashedPassword, $currentUserId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ? WHERE id = ?");
                $stmt->execute([$name, $username, $currentUserId]);
            }

            // Log the profile update
            require_once __DIR__ . '/../../helpers/logger.php';
            logUserActivity(
                $currentUserId,
                'update_user',
                $currentUserId,
                "Updated own profile settings",
                [
                    'name' => $oldUserData['name'],
                    'username' => $oldUserData['username']
                ],
                [
                    'name' => $name,
                    'username' => $username,
                    'password_changed' => !empty($new_password)
                ]
            );

            $pdo->commit();
            $_SESSION['success_message'] = "Profile updated successfully";
            header('Location: settings.php');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Handle settings update
if (isset($_POST['update_settings'])) {
    try {
        $pdo->beginTransaction();

        $two_factor = isset($_POST['two_factor']) ? 1 : 0;
        $session_timeout = (int)$_POST['session_timeout'];
        $login_notifications = isset($_POST['login_notifications']) ? 1 : 0;
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $system_notifications = isset($_POST['system_notifications']) ? 1 : 0;
        $sales_alerts = isset($_POST['sales_alerts']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE user_settings SET 
            two_factor_enabled = ?,
            session_timeout = ?,
            login_notifications = ?,
            email_notifications = ?,
            system_notifications = ?,
            sales_alerts = ?
            WHERE user_id = ?");
        
        $stmt->execute([
            $two_factor,
            $session_timeout,
            $login_notifications,
            $email_notifications,
            $system_notifications,
            $sales_alerts,
            $currentUserId
        ]);

        $pdo->commit();
        $_SESSION['success_message'] = "Settings updated successfully";
        header('Location: settings.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating settings: " . $e->getMessage();
    }
}

// Get unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$currentUserId]);
$unreadNotifications = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <?php include 'shared/sidebar.php'; ?>
        
        <div class="flex-1 p-8 overflow-y-auto h-screen">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h1 class="text-2xl font-bold text-gray-800">Settings</h1>
                            <?php if ($unreadNotifications > 0): ?>
                            <div class="relative">
                                <button @click="activeTab = 'notifications'" class="text-gray-600 hover:text-gray-900">
                                    <i class="fas fa-bell text-xl"></i>
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                        <?php echo $unreadNotifications; ?>
                                    </span>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="p-4 bg-green-100 border border-green-400 text-green-700">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="p-4 bg-red-100 border border-red-400 text-red-700">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                    <?php endif; ?>

                    <div x-data="{ activeTab: 'profile' }" class="p-6">
                        <!-- Tabs -->
                        <div class="border-b border-gray-200 mb-6">
                            <nav class="-mb-px flex space-x-8">
                                <button @click="activeTab = 'profile'" 
                                        :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'profile' }"
                                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    <i class="fas fa-user mr-2"></i>Profile Settings
                                </button>
                                <button @click="activeTab = 'system'" 
                                        :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'system' }"
                                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    <i class="fas fa-cogs mr-2"></i>System Settings
                                </button>
                                <button @click="activeTab = 'security'" 
                                        :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'security' }"
                                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    <i class="fas fa-shield-alt mr-2"></i>Security Settings
                                </button>
                                <button @click="activeTab = 'notifications'" 
                                        :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'notifications' }"
                                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    <i class="fas fa-bell mr-2"></i>Notification Settings
                                </button>
                                <button @click="activeTab = 'about'" 
                                        :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'about' }"
                                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    <i class="fas fa-info-circle mr-2"></i>About
                                </button>
                            </nav>
                        </div>

                        <!-- Profile Settings -->
                        <div x-show="activeTab === 'profile'" class="space-y-6">
                            <form method="POST" class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Username</label>
                                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Current Password</label>
                                    <input type="password" name="current_password"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                    <p class="mt-1 text-sm text-gray-500">Leave blank if you don't want to change your password</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">New Password</label>
                                    <input type="password" name="new_password"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                    <input type="password" name="confirm_password"
                                        class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" name="update_profile"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- System Settings -->
                        <div x-show="activeTab === 'system'" class="space-y-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">System Configuration</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">System Name</label>
                                        <input type="text" value="Retail POS System" disabled
                                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Database Version</label>
                                        <input type="text" value="MySQL 8.0" disabled
                                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">PHP Version</label>
                                        <input type="text" value="<?php echo phpversion(); ?>" disabled
                                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Settings -->
                        <div x-show="activeTab === 'security'" class="space-y-6">
                            <form method="POST" class="space-y-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Security Options</h3>
                                    <div class="space-y-4">
                                        <div class="flex items-center">
                                            <input type="checkbox" id="two_factor" name="two_factor" 
                                                <?php echo $user['two_factor_enabled'] ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="two_factor" class="ml-2 block text-sm text-gray-900">
                                                Enable Two-Factor Authentication
                                            </label>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Session Timeout (minutes)</label>
                                            <input type="number" name="session_timeout" 
                                                value="<?php echo $user['session_timeout']; ?>"
                                                min="5" max="120" step="5"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="login_notifications" name="login_notifications"
                                                <?php echo $user['login_notifications'] ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="login_notifications" class="ml-2 block text-sm text-gray-900">
                                                Enable Login Notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" name="update_settings"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Save Security Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Notification Settings -->
                        <div x-show="activeTab === 'notifications'" class="space-y-6">
                            <form method="POST" class="space-y-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Notification Preferences</h3>
                                    <div class="space-y-4">
                                        <div class="flex items-center">
                                            <input type="checkbox" id="email_notifications" name="email_notifications"
                                                <?php echo $user['email_notifications'] ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="email_notifications" class="ml-2 block text-sm text-gray-900">
                                                Email Notifications
                                            </label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="system_notifications" name="system_notifications"
                                                <?php echo $user['system_notifications'] ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="system_notifications" class="ml-2 block text-sm text-gray-900">
                                                System Notifications
                                            </label>
                                        </div>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="sales_alerts" name="sales_alerts"
                                                <?php echo $user['sales_alerts'] ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <label for="sales_alerts" class="ml-2 block text-sm text-gray-900">
                                                Sales Alerts
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" name="update_settings"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- About -->
                        <div x-show="activeTab === 'about'" class="space-y-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">About the System</h3>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Version: 1.0.0</p>
                                        <p class="text-sm text-gray-600">Last Updated: May 11, 2024</p>
                                        <p class="text-sm text-gray-600">Developer: Your Company Name</p>
                                    </div>
                                    <div class="mt-4">
                                        <h4 class="text-sm font-medium text-gray-900">System Requirements</h4>
                                        <ul class="mt-2 text-sm text-gray-600 list-disc list-inside">
                                            <li>PHP 7.4 or higher</li>
                                            <li>MySQL 5.7 or higher</li>
                                            <li>Modern web browser</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 