<?php
$user = isset($user) ? $user : ['name' => 'User', 'role' => 'user'];
$title = isset($title) ? $title : 'POS System';
?>
<div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($title); ?></h1>
    <div class="flex items-center space-x-4">
        <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
        <a href="../auth/logout.php" class="text-red-600 hover:text-red-700">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div> 