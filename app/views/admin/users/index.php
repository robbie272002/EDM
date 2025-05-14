<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

// Check if user is logged in
requireLogin();

// Get current user's role and ID
$currentUserRole = getCurrentUserRole();
$currentUserId = $_SESSION['user_id'];
$isAdmin = isAdmin();

// Check if current user is super admin (assuming ID 1 is super admin)
$isSuperAdmin = ($currentUserId == 1);

// Handle user status toggle
if (isset($_POST['toggle_status']) && $isAdmin) {
    $user_id = $_POST['user_id'];
    $current_status = $_POST['current_status'];
    
    // Check if target user is a super admin or admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $targetUserRole = $stmt->fetchColumn();
    
    // Prevent regular admin from modifying super admin or other admin accounts
    if (($targetUserRole === 'super_admin' || $targetUserRole === 'admin') && !isSuperAdmin()) {
        $_SESSION['error_message'] = "You don't have permission to modify an admin account";
        header('Location: index.php');
        exit();
    }
    
    // Prevent self-deactivation
    if ($user_id == $currentUserId) {
        $_SESSION['error_message'] = "You cannot deactivate your own account";
        header('Location: index.php');
        exit();
    }
    
    try {
        // Check if this is the last active admin
        if ($current_status === 'active') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user['role'] === 'admin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id != ?");
                $stmt->execute([$user_id]);
                $activeAdminCount = $stmt->fetchColumn();
                
                if ($activeAdminCount === 0) {
                    $_SESSION['error_message'] = "Cannot deactivate the last active admin user";
                    header('Location: index.php');
                    exit();
                }
            }
        }
        
        // Get user details before update for logging
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $oldUserData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Toggle status
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);

        // Log the status change
        require_once __DIR__ . '/../../../helpers/logger.php';
        logUserActivity(
            $currentUserId,
            'update_user',
            $user_id,
            ($new_status === 'active' ? "Activated" : "Deactivated") . " user: " . $oldUserData['name'],
            ['status' => $oldUserData['status']],
            ['status' => $new_status]
        );
        
        $_SESSION['success_message'] = "User " . ($new_status === 'active' ? 'activated' : 'deactivated') . " successfully";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating user status: " . $e->getMessage();
    }
    header('Location: index.php');
    exit();
}

// Handle user creation
if (isset($_POST['add_user']) && $isAdmin) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Only super admin can create another super admin
    if ($role === 'super_admin' && $currentUserId != 1) {
        $_SESSION['error_message'] = "Only super admin can create another super admin";
        header('Location: index.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error_message'] = "Username already exists";
            $pdo->rollBack();
        } else {
            // Create new user
            $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$name, $username, $hashedPassword, $role, $status]);
            
            // Get the new user's ID
            $newUserId = $pdo->lastInsertId();

            // Log the user creation
            require_once __DIR__ . '/../../../helpers/logger.php';
            logUserActivity(
                $currentUserId,
                'create_user',
                $newUserId,
                "Created new {$role} user: {$name}",
                null,
                [
                    'name' => $name,
                    'username' => $username,
                    'role' => $role,
                    'status' => $status
                ]
            );

            $pdo->commit();
            $_SESSION['success_message'] = "User created successfully";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error creating user: " . $e->getMessage();
    }
    header('Location: index.php');
    exit();
}

// Handle user update
if (isset($_POST['edit_user']) && $isAdmin) {
    $user_id = $_POST['user_id'];
    
    // Check if target user is a super admin or admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $targetUserRole = $stmt->fetchColumn();
    
    // Prevent regular admin from modifying super admin or other admin accounts
    if (($targetUserRole === 'super_admin' || $targetUserRole === 'admin') && !isSuperAdmin()) {
        $_SESSION['error_message'] = "You don't have permission to modify an admin account";
        header('Location: index.php');
        exit();
    }

    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = trim($_POST['password']);
    $status = $_POST['status'];

    // Only super admin can modify super admin roles
    if ($role === 'super_admin' && $currentUserId != 1) {
        $_SESSION['error_message'] = "Only super admin can assign super admin role";
        header('Location: index.php');
        exit();
    }

    // Prevent changing super admin's role if they are already super admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentRole = $stmt->fetchColumn();
    
    if ($currentRole === 'super_admin' && $role !== 'super_admin' && $currentUserId != 1) {
        $_SESSION['error_message'] = "Cannot modify super admin's role";
        header('Location: index.php');
        exit();
    }

    // Get user details before update for logging
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $oldUserData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prevent self-deactivation
    if ($user_id == $currentUserId && $status === 'inactive') {
        $_SESSION['error_message'] = "You cannot deactivate your own account";
        header('Location: index.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Check if username already exists for other users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error_message'] = "Username already exists";
            $pdo->rollBack();
            header('Location: index.php');
            exit();
        }

        // Update user
        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password = ?, role = ?, status = ? WHERE id = ?");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$name, $username, $hashedPassword, $role, $status, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $username, $role, $status, $user_id]);
        }

        // Log the user update
        require_once __DIR__ . '/../../../helpers/logger.php';
        logUserActivity(
            $currentUserId,
            'update_user',
            $user_id,
            "Updated user information for: " . $name,
            [
                'name' => $oldUserData['name'],
                'username' => $oldUserData['username'],
                'role' => $oldUserData['role'],
                'status' => $oldUserData['status']
            ],
            [
                'name' => $name,
                'username' => $username,
                'role' => $role,
                'status' => $status,
                'password_changed' => !empty($password)
            ]
        );

        $pdo->commit();
        $_SESSION['success_message'] = "User updated successfully";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating user: " . $e->getMessage();
    }
    header('Location: index.php');
    exit();
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

// Exclude super admin users from the list
$query .= " AND role != 'super_admin'";

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user's role
$currentUserRole = getCurrentUserRole();
$isAdmin = isAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        // Debug information
        console.log('Alpine.js loaded:', typeof Alpine !== 'undefined');
        console.log('Tailwind loaded:', typeof tailwind !== 'undefined');
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary {
            background-color: #4f46e5;
            color: white;
        }
        .btn-primary:hover {
            background-color: #4338ca;
        }
        .btn-edit {
            background-color: #e0e7ff;
            color: #4f46e5;
        }
        .btn-edit:hover {
            background-color: #c7d2fe;
        }
        .btn-delete {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .btn-delete:hover {
            background-color: #fecaca;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div x-data="{ 
        searchQuery: '<?php echo htmlspecialchars($search); ?>',
        roleFilter: '<?php echo htmlspecialchars($role_filter); ?>',
        filterUsers() {
            const searchLower = this.searchQuery.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const username = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const role = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                
                const matchesSearch = name.includes(searchLower) || username.includes(searchLower);
                const matchesRole = this.roleFilter === '' || role.includes(this.roleFilter.toLowerCase());
                
                row.style.display = matchesSearch && matchesRole ? '' : 'none';
            });
        }
    }" class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg">
            <?php include '../shared/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 ml-64 overflow-auto">
            <div class="p-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <!-- Header with Add Button -->
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
                            <?php if ($isAdmin): ?>
                            <button @click="$dispatch('open-modal', 'add-user')" 
                                    class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i>
                                Add New User
                            </button>
                            <?php endif; ?>
                        </div>

                        <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                        <?php endif; ?>

                        <!-- Search and Filter -->
                        <div class="flex space-x-4 mb-6">
                            <div class="relative flex-1">
                                <input type="text" 
                                       x-model="searchQuery"
                                       @input="filterUsers"
                                       placeholder="Search users..." 
                                       class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                            <div class="relative">
                                <select x-model="roleFilter"
                                        @change="filterUsers"
                                        class="pl-4 pr-10 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">All Roles</option>
                                    <option value="admin">Admin</option>
                                    <option value="cashier">Cashier</option>
                                </select>
                            </div>
                        </div>

                        <!-- Users Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                        <?php if ($isAdmin): ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="<?php echo $isAdmin ? '5' : '4'; ?>" class="px-6 py-4 text-center text-gray-500">
                                            No users found
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo ucfirst($user['role']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo ucfirst($user['status']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <?php if ($isAdmin): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-4">
                                                <?php 
                                                // Show action buttons only if:
                                                // 1. Current user is super admin, OR
                                                // 2. Target user is not a super admin or admin
                                                if (isSuperAdmin() || ($user['role'] !== 'super_admin' && $user['role'] !== 'admin')): 
                                                ?>
                                                <button @click="$dispatch('open-modal', 'edit-user-<?php echo $user['id']; ?>')" 
                                                        class="btn btn-edit">
                                                    <i class="fas fa-edit mr-2"></i>
                                                    Edit
                                                </button>
                                                
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">
                                                    <button type="submit" 
                                                            name="toggle_status"
                                                            class="<?php echo $user['status'] === 'active' ? 'btn btn-danger' : 'btn btn-success'; ?>">
                                                        <i class="fas <?php echo $user['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?> mr-2"></i>
                                                        <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <span class="text-sm text-gray-500">Only super admin can modify admin accounts</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- Add User Modal -->
    <div x-data="{ show: false }" 
         x-show="show" 
         x-on:open-modal.window="if ($event.detail === 'add-user') show = true"
         x-on:close-modal.window="show = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
            <div class="relative bg-white rounded-2xl shadow-xl transform transition-all w-full max-w-lg"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                <!-- Modal header -->
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Add New User</h3>
                        <button type="button" 
                                @click="show = false"
                                class="text-gray-400 hover:text-gray-500 focus:outline-none">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal body -->
                <form method="POST" class="bg-white rounded-2xl">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="name" 
                                           required 
                                           class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                           placeholder="Enter user's full name">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-at text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="username" 
                                           required 
                                           class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                           placeholder="Enter username">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" 
                                           name="password" 
                                           required 
                                           class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                           placeholder="Enter password">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user-tag text-gray-400"></i>
                                    </div>
                                    <select name="role" required class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="cashier">Cashier</option>
                                        <option value="admin">Admin</option>
                                        <?php if ($currentUserId == 1): // Only super admin can see and assign super admin role ?>
                                        <option value="super_admin">Super Admin</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-toggle-on text-gray-400"></i>
                                    </div>
                                    <select name="status" required class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="active">Active</option>
                                        <option value="inactive">Deactivated</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse rounded-b-2xl border-t border-gray-200">
                        <button type="submit" 
                                name="add_user"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add User
                        </button>
                        <button type="button"
                                @click="show = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modals -->
    <?php foreach ($users as $user): ?>
    <div x-data="{ show: false }" 
         x-show="show" 
         x-on:open-modal.window="if ($event.detail === 'edit-user-<?php echo $user['id']; ?>') show = true"
         x-on:close-modal.window="show = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75"></div>
            <div class="relative bg-white rounded-2xl shadow-xl transform transition-all w-full max-w-lg"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                <!-- Modal header -->
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-gray-200 rounded-xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Edit User</h3>
                        <button type="button" 
                                @click="show = false"
                                class="text-gray-400 hover:text-gray-500 focus:outline-none">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal body -->
                <form method="POST" class="bg-white rounded-2xl">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                                           required 
                                           class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                           placeholder="Enter user's full name">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-at text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           name="username" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                                           required 
                                           class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                           placeholder="Enter username">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">New Password</label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" 
                                           name="password" 
                                           class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                           placeholder="Leave blank to keep current password">
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Leave blank to keep current password</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user-tag text-gray-400"></i>
                                    </div>
                                    <select name="role" 
                                            required 
                                            class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" 
                                            <?php echo ($user['id'] == 1) ? 'disabled' : ''; ?>>
                                        <option value="cashier" <?php echo $user['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <?php if ($currentUserId == 1): // Only super admin can see and assign super admin role ?>
                                        <option value="super_admin" <?php echo $user['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <?php if ($user['id'] == 1): ?>
                                <input type="hidden" name="role" value="admin">
                                <p class="mt-1 text-sm text-gray-500">Super admin role cannot be changed</p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-toggle-on text-gray-400"></i>
                                    </div>
                                    <select name="status" 
                                            required 
                                            class="block w-full h-12 rounded-md border-2 border-gray-300 pl-10 pr-3 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            <?php echo ($user['id'] == $currentUserId) ? 'disabled' : ''; ?>>
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Deactivated</option>
                                    </select>
                                </div>
                                <?php if ($user['id'] == $currentUserId): ?>
                                <input type="hidden" name="status" value="active">
                                <p class="mt-1 text-sm text-gray-500">You cannot deactivate your own account</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse  border-gray-200 rounded-b-2xl">
                        <button type="submit" 
                                name="edit_user"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Changes
                        </button>
                        <button type="button"
                                @click="show = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</body>
</html> 