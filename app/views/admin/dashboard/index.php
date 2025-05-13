<!-- Dashboard Section -->
<div id="dashboard" class="section-content p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Card 1 -->
        <div class="card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Today's Sales</p>
                    <h3 class="text-2xl font-bold mt-1">$1,842.50</h3>
                    <p class="text-sm text-green-500 mt-2 flex items-center">
                        <i class="fas fa-arrow-up mr-1"></i> 12.5% from yesterday
                    </p>
                </div>
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-dollar-sign text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Card 2 -->
        <div class="card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Transactions</p>
                    <h3 class="text-2xl font-bold mt-1">24</h3>
                    <p class="text-sm text-green-500 mt-2 flex items-center">
                        <i class="fas fa-arrow-up mr-1"></i> 3 more than yesterday
                    </p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-receipt text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Card 3 -->
        <div class="card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Low Stock Items</p>
                    <h3 class="text-2xl font-bold mt-1">7</h3>
                    <p class="text-sm text-red-500 mt-2 flex items-center">
                        <i class="fas fa-exclamation-circle mr-1"></i> Needs attention
                    </p>
                </div>
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <i class="fas fa-box-open text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Card 4 -->
        <div class="card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Active Users</p>
                    <h3 class="text-2xl font-bold mt-1">3</h3>
                    <p class="text-sm text-gray-500 mt-2 flex items-center">
                        <i class="fas fa-user-clock mr-1"></i> Currently logged in
                    </p>
                </div>
                <div class="bg-purple-100 p-3 rounded-lg">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Sales Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold">Sales Overview</h3>
                <select class="text-sm border border-gray-300 rounded px-3 py-1">
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option selected>This Month</option>
                    <option>This Year</option>
                </select>
            </div>
            <canvas id="salesChart" height="250"></canvas>
        </div>
        
        <!-- Top Products Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold">Top Selling Products</h3>
                <select class="text-sm border border-gray-300 rounded px-3 py-1">
                    <option>Today</option>
                    <option selected>This Week</option>
                    <option>This Month</option>
                </select>
            </div>
            <canvas id="productsChart" height="250"></canvas>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="font-bold">Recent Transactions</h3>
            <button class="text-sm text-blue-600 hover:text-blue-800">View All</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full data-table">
                <thead>
                    <tr class="text-left text-sm font-medium text-gray-500">
                        <th class="px-6 py-3">Transaction ID</th>
                        <th class="px-6 py-3">Date/Time</th>
                        <th class="px-6 py-3">Cashier</th>
                        <th class="px-6 py-3">Items</th>
                        <th class="px-6 py-3">Amount</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">TR-2023-042-001</td>
                        <td class="px-6 py-4">Nov 15, 11:45 AM</td>
                        <td class="px-6 py-4">John D.</td>
                        <td class="px-6 py-4">3</td>
                        <td class="px-6 py-4">$127.47</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Completed</span>
                        </td>
                        <td class="px-6 py-4">
                            <button class="text-blue-600 hover:text-blue-800 mr-2">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="text-gray-600 hover:text-gray-800">
                                <i class="fas fa-print"></i>
                            </button>
                        </td>
                    </tr>
                    <!-- Add more transaction rows here -->
                </tbody>
            </table>
        </div>
    </div>
</div> 