<?php
$title = $title ?? 'POS System';
$showDrawerStatus = $showDrawerStatus ?? false;
?>
<div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($title); ?></h1>
    <div class="flex items-center space-x-4">
        <?php if ($showDrawerStatus): ?>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-600">Drawer Status:</span>
            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                Open
            </span>
        </div>
        <?php endif; ?>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-600">Cashier:</span>
            <span class="font-medium"><?php echo htmlspecialchars($user['name']); ?></span>
        </div>
        <button onclick="window.location.href='/NEW/app/views/auth/logout.php'" 
                class="px-3 py-1 text-sm text-red-600 hover:text-red-800">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</div> 