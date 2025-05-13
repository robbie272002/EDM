<?php
require_once __DIR__ . '/../../../../config/database.php';

// Fetch dashboard statistics
try {
    // Today's sales
    $todaySales = $pdo->query("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM sales 
        WHERE DATE(created_at) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC)['total'];

    // Today's transactions count
    $todayTransactions = $pdo->query("
        SELECT COUNT(*) as count 
        FROM sales 
        WHERE DATE(created_at) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC)['count'];

    // Low stock items (less than 10 units)
    $lowStockItems = $pdo->query("
        SELECT COUNT(*) as count 
        FROM products 
        WHERE stock < 10
    ")->fetch(PDO::FETCH_ASSOC)['count'];

    // Active users
    $activeUsers = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM sales 
        WHERE DATE(created_at) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC)['count'];

    // Recent transactions
    $recentTransactions = $pdo->query("
        SELECT s.*, u.name as cashier_name, 
               (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
        FROM sales s
        JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Handle error appropriately
    error_log("Dashboard Error: " . $e->getMessage());
    $todaySales = 0;
    $todayTransactions = 0;
    $lowStockItems = 0;
    $activeUsers = 0;
    $recentTransactions = [];
}
?>

<!-- Dashboard Section -->
<div id="dashboard" class="section-content p-6">
    <!-- Date Range Selector -->
    <div class="mb-6">
        <div class="flex items-center space-x-4">
            <select name="date_range" class="border border-gray-300 rounded px-3 py-2">
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="quarter">This Quarter</option>
                <option value="year">This Year</option>
                <option value="custom">Custom Range</option>
            </select>
            <div class="custom-date-inputs hidden">
                <input type="date" name="start_date" class="border border-gray-300 rounded px-3 py-2">
                <input type="date" name="end_date" class="border border-gray-300 rounded px-3 py-2">
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Card 1: Total Sales -->
        <div class="total-sales card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Sales</p>
                    <h3 class="text-2xl font-bold mt-1 value">₱<?= number_format($todaySales, 2) ?></h3>
                    <p class="text-sm mt-2 flex items-center growth">
                        <i class="fas fa-arrow-up mr-1"></i>
                        <span>0% vs last period</span>
                    </p>
                </div>
                <div class="bg-blue-100 p-3 rounded-lg">
                    <i class="fas fa-dollar-sign text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Card 2: Total Discount -->
        <div class="total-discount card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Discount</p>
                    <h3 class="text-2xl font-bold mt-1 value">₱0.00</h3>
                    <p class="text-sm mt-2 flex items-center growth">
                        <i class="fas fa-arrow-up mr-1"></i>
                        <span>0% vs last period</span>
                    </p>
                </div>
                <div class="bg-green-100 p-3 rounded-lg">
                    <i class="fas fa-tags text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Card 3: Total Revenue -->
        <div class="total-revenue card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                    <h3 class="text-2xl font-bold mt-1 value">₱0.00</h3>
                    <p class="text-sm mt-2 flex items-center growth">
                        <i class="fas fa-arrow-up mr-1"></i>
                        <span>0% vs last period</span>
                    </p>
                </div>
                <div class="bg-yellow-100 p-3 rounded-lg">
                    <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Card 4: Total Transactions -->
        <div class="total-transactions card-hover bg-white rounded-lg shadow p-6 transition-all duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Transactions</p>
                    <h3 class="text-2xl font-bold mt-1 value"><?= $todayTransactions ?></h3>
                    <p class="text-sm mt-2 flex items-center growth">
                        <i class="fas fa-arrow-up mr-1"></i>
                        <span>0% vs last period</span>
                    </p>
                </div>
                <div class="bg-purple-100 p-3 rounded-lg">
                    <i class="fas fa-receipt text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden inputs for date range -->
    <input type="hidden" id="hiddenStartDate" value="">
    <input type="hidden" id="hiddenEndDate" value="">
    
    <!-- Error message div -->
    <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6"></div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Sales Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold">Sales Overview</h3>
                <select id="salesPeriod" class="text-sm border border-gray-300 rounded px-3 py-1">
                    <option value="7">Last 7 Days</option>
                    <option value="30">Last 30 Days</option>
                    <option value="month" selected>This Month</option>
                    <option value="year">This Year</option>
                </select>
            </div>
            <canvas id="salesChart" height="250"></canvas>
        </div>
        
        <!-- Top Products Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold">Top Selling Products</h3>
                <select id="productsPeriod" class="text-sm border border-gray-300 rounded px-3 py-1">
                    <option value="today">Today</option>
                    <option value="week" selected>This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>
            <canvas id="productsChart" height="250"></canvas>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="font-bold">Recent Transactions</h3>
            <a href="transactions.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
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
                    <?php foreach ($recentTransactions as $transaction): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4"><?= $transaction['transaction_id'] ?></td>
                        <td class="px-6 py-4"><?= date('M d, h:i A', strtotime($transaction['created_at'])) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($transaction['cashier_name']) ?></td>
                        <td class="px-6 py-4"><?= $transaction['item_count'] ?></td>
                        <td class="px-6 py-4"><?= number_format($transaction['total_amount'], 2) ?> <?= CONFIG['CURRENCY'] ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Completed</span>
                        </td>
                        <td class="px-6 py-4">
                            <button onclick="viewTransaction('<?= $transaction['id'] ?>')" class="text-blue-600 hover:text-blue-800 mr-2">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="printReceipt('<?= $transaction['id'] ?>')" class="text-gray-600 hover:text-gray-800">
                                <i class="fas fa-print"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Initialize charts when the dashboard is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeSalesChart();
    initializeProductsChart();
});

// Sales Chart
function initializeSalesChart() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Sales',
                data: [],
                borderColor: '#4F46E5',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Update chart when period changes
    document.getElementById('salesPeriod').addEventListener('change', function() {
        updateSalesChart(this.value);
    });

    // Initial load
    updateSalesChart('month');
}

// Products Chart
function initializeProductsChart() {
    const ctx = document.getElementById('productsChart').getContext('2d');
    const productsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Units Sold',
                data: [],
                backgroundColor: '#10B981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Update chart when period changes
    document.getElementById('productsPeriod').addEventListener('change', function() {
        updateProductsChart(this.value);
    });

    // Initial load
    updateProductsChart('week');
}

// Update sales chart data
async function updateSalesChart(period) {
    try {
        const response = await fetch(`/routes/dashboard/sales.php?period=${period}`);
        const data = await response.json();
        
        if (data.success) {
            const chart = Chart.getChart('salesChart');
            chart.data.labels = data.labels;
            chart.data.datasets[0].data = data.values;
            chart.update();
        }
    } catch (error) {
        console.error('Error updating sales chart:', error);
    }
}

// Update products chart data
async function updateProductsChart(period) {
    try {
        const response = await fetch(`/routes/dashboard/products.php?period=${period}`);
        const data = await response.json();
        
        if (data.success) {
            const chart = Chart.getChart('productsChart');
            chart.data.labels = data.labels;
            chart.data.datasets[0].data = data.values;
            chart.update();
        }
    } catch (error) {
        console.error('Error updating products chart:', error);
    }
}

// View transaction details
function viewTransaction(id) {
    window.location.href = `transactions.php?id=${id}`;
}

// Print receipt
function printReceipt(id) {
    window.open(`receipt.php?id=${id}`, '_blank');
}
</script> 