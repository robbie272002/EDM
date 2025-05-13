<?php
$user = isset($user) ? $user : ['name' => 'User', 'role' => 'user'];
$title = isset($title) ? $title : 'POS System';
$showDrawerStatus = isset($showDrawerStatus) ? $showDrawerStatus : true;
?>
<div class="flex justify-between items-center mb-4">
    <div class="flex items-center space-x-4">
        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($title); ?></h1>
        <?php if ($showDrawerStatus): ?>
        <div class="flex items-center space-x-2">
            <span class="w-3 h-3 rounded-full bg-green-500"></span>
            <span class="text-sm text-gray-600">Drawer Open</span>
        </div>
        <?php endif; ?>
    </div>
    <div class="flex items-center space-x-4">
        <div class="text-right">
            <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
            <div class="text-sm text-gray-500"><?php echo date('M d, Y H:i'); ?></div>
        </div>
        <div class="flex items-center space-x-2">
            <button class="p-2 text-gray-600 hover:text-gray-800" onclick="toggleDrawer()">
                <i class="fas fa-cash-register"></i>
            </button>
            <button class="p-2 text-gray-600 hover:text-gray-800" onclick="showReports()">
                <i class="fas fa-chart-bar"></i>
            </button>
            <a href="../auth/logout.php" class="p-2 text-red-600 hover:text-red-700">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</div> 