<!-- Mobile menu button -->
<button @click="sidebarOpen = !sidebarOpen" class="md:hidden fixed top-4 left-4 z-50 p-2 rounded-md text-gray-600 hover:text-gray-900 focus:outline-none">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- Sidebar -->
<div x-data="{ sidebarOpen: false }" class="flex h-full" x-cloak>
    <div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
         class="fixed inset-y-0 left-0 w-64 bg-indigo-700 text-white flex flex-col z-40 transform transition-transform duration-200 md:relative md:translate-x-0 md:flex md:w-64 md:min-h-screen md:sticky md:top-0">
        <div class="p-4 flex items-center space-x-3 border-b border-indigo-800">
            <div class="bg-white bg-opacity-10 p-2 rounded-lg">
                <i class="fas fa-user-shield text-xl"></i>
            </div>
            <h1 class="text-xl font-bold">Admin Panel</h1>
            <button @click="sidebarOpen = false" class="md:hidden ml-auto text-white text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="flex-1 px-2 space-y-1 overflow-y-auto">
            <a href="/NEW/app/views/admin/dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-chart-line w-6"></i>
                <span>Dashboard</span>
            </a>
            <a href="/NEW/app/views/admin/sales/reports.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-file-invoice-dollar w-6"></i>
                <span>Sales Reports</span>
            </a>
            <a href="/NEW/app/views/admin/inventory/index.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-warehouse w-6"></i>
                <span>Inventory</span>
            </a>
            <a href="/NEW/app/views/admin/products/index.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-box w-6"></i>
                <span>Products</span>
            </a>
            <a href="/NEW/app/views/admin/categories/index.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-tags w-6"></i>
                <span>Categories</span>
            </a>
            <a href="/NEW/app/views/admin/users/index.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-users w-6"></i>
                <span>Users</span>
            </a>
            <a href="/NEW/app/views/admin/activity-logs/index.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-history w-6"></i>
                <span>Activity Logs</span>
            </a>
            <a href="/NEW/app/views/admin/settings.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-600 transition">
                <i class="fas fa-cog w-6"></i>
                <span>Settings</span>
            </a>
        </nav>
        <div class="mt-auto w-full p-4 border-t border-indigo-800">
            <a href="/NEW/app/views/auth/logout.php" class="w-full flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-800 transition">
                <i class="fas fa-sign-out-alt w-6"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    <!-- Overlay for mobile -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black bg-opacity-40 z-30 md:hidden"></div>
</div> 