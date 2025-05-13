<!-- app/views/components/sidebar.php -->
<div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 w-56 bg-white shadow-lg rounded-r-2xl flex flex-col z-40 transition-transform duration-200 md:relative md:translate-x-0 md:flex md:w-56">
    <div class="p-6 flex items-center space-x-3 border-b border-gray-200">
        <div class="bg-indigo-100 p-2 rounded-lg">
            <i class="fas fa-cash-register text-indigo-600 text-2xl"></i>
        </div>
        <h1 class="text-xl font-bold text-indigo-700">POS Cashier</h1>
        <button @click="sidebarOpen = false" class="md:hidden ml-auto text-gray-500 text-2xl">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-2">
        <a href="pos.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-50 transition font-medium text-gray-700 <?php echo basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'bg-indigo-100' : ''; ?>">
            <i class="fas fa-cash-register w-6 text-indigo-600"></i>
            <span>POS</span>
        </a>
        <a href="transaction-history.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-indigo-50 transition font-medium text-gray-700 <?php echo basename($_SERVER['PHP_SELF']) === 'transaction-history.php' ? 'bg-indigo-100' : ''; ?>">
            <i class="fas fa-history w-6 text-indigo-600"></i>
            <span>Audit Logs</span>
        </a>
    </nav>
    <div class="mt-auto w-full p-4 border-t border-gray-100">
        <a href="/NEW/app/views/auth/logout.php" class="w-full flex items-center space-x-3 p-3 rounded-lg hover:bg-red-50 transition text-red-600 font-medium">
            <i class="fas fa-sign-out-alt w-6"></i>
            <span>Logout</span>
        </a>
    </div>
</div> 