<?php
$user = isset($user) ? $user : ['name' => 'User', 'role' => 'user'];
?>
<div class="w-64 bg-gray-800 text-white">
    <div class="p-4">
        <h2 class="text-2xl font-bold"><?php echo $user['role'] === 'admin' ? 'Admin Panel' : 'POS System'; ?></h2>
        <p class="text-gray-400 text-sm">Welcome, <?php echo htmlspecialchars($user['name']); ?></p>
    </div>
    <nav class="mt-8">
        <?php if ($user['role'] === 'admin'): ?>
            <a href="dashboard.php" class="block py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-chart-line mr-2"></i> Dashboard
            </a>
            <a href="products.php" class="block py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-box mr-2"></i> Products
            </a>
            <a href="users.php" class="block py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-users mr-2"></i> Users
            </a>
            <a href="sales.php" class="block py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'sales.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-shopping-cart mr-2"></i> Sales
            </a>
            <a href="settings.php" class="block py-2 px-4 <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?>">
                <i class="fas fa-cog mr-2"></i> Settings
            </a>
        <?php endif; ?>
        <a href="../auth/logout.php" class="block py-2 px-4 hover:bg-gray-700">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
        </a>
    </nav>
</div> 