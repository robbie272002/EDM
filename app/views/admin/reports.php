<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

// Check if user is logged in
requireLogin();

// Get current date and first day of current month
$currentDate = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

// Get date range from request or default to current month
$startDate = $_GET['start_date'] ?? $firstDayOfMonth;
$endDate = $_GET['end_date'] ?? $lastDayOfMonth;

// Get report type
$reportType = $_GET['report_type'] ?? 'sales';

// Get sales data
$query = "SELECT 
            DATE(created_at) as sale_date,
            COUNT(*) as total_transactions,
            SUM(total_amount) as total_revenue,
            COUNT(DISTINCT product_id) as unique_products
          FROM sales 
          WHERE created_at BETWEEN ? AND ?
          GROUP BY DATE(created_at)
          ORDER BY sale_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$startDate, $endDate]);
$salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalRevenue = array_sum(array_column($salesData, 'total_revenue'));
$totalTransactions = array_sum(array_column($salesData, 'total_transactions'));

// Get previous period data for comparison
$prevStartDate = date('Y-m-d', strtotime($startDate . ' -1 month'));
$prevEndDate = date('Y-m-d', strtotime($endDate . ' -1 month'));

$stmt->execute([$prevStartDate, $prevEndDate]);
$prevSalesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$prevTotalRevenue = array_sum(array_column($prevSalesData, 'total_revenue'));

// Calculate growth
$growth = $prevTotalRevenue > 0 ? (($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Admin Panel</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg">
            <?php include '../shared/sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 ml-64 overflow-auto">
            <div class="p-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold text-gray-800">Sales Reports</h1>
                            <div class="flex space-x-4">
                                <button onclick="exportToPDF()" class="btn btn-primary">
                                    <i class="fas fa-file-pdf mr-2"></i>
                                    Export PDF
                                </button>
                                <button onclick="exportToCSV()" class="btn btn-primary">
                                    <i class="fas fa-file-csv mr-2"></i>
                                    Export CSV
                                </button>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-6">
                            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Report Type</label>
                                    <select name="report_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                                        <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>Product Performance</option>
                                        <option value="categories" <?php echo $reportType === 'categories' ? 'selected' : ''; ?>>Category Analysis</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="btn btn-primary w-full">
                                        <i class="fas fa-filter mr-2"></i>
                                        Apply Filters
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                        <i class="fas fa-dollar-sign text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                                        <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalRevenue, 2); ?></p>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <span class="text-sm <?php echo $growth >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <i class="fas fa-<?php echo $growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                        <?php echo abs(round($growth, 1)); ?>% vs last period
                                    </span>
                                </div>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i class="fas fa-shopping-cart text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-sm font-medium text-gray-500">Total Transactions</h3>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalTransactions); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                        <i class="fas fa-box text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-sm font-medium text-gray-500">Unique Products Sold</h3>
                                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format(array_sum(array_column($salesData, 'unique_products'))); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i class="fas fa-chart-line text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-sm font-medium text-gray-500">Average Order Value</h3>
                                        <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalRevenue / max($totalTransactions, 1), 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Revenue Trend</h3>
                                <canvas id="revenueChart" height="300"></canvas>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Transaction Volume</h3>
                                <canvas id="transactionsChart" height="300"></canvas>
                            </div>
                        </div>

                        <!-- Detailed Sales Table -->
                        <div class="bg-white rounded-lg shadow border border-gray-200">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Detailed Sales Data</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unique Products</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Order Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($salesData as $sale): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo number_format($sale['total_transactions']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    $<?php echo number_format($sale['total_revenue'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo number_format($sale['unique_products']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    $<?php echo number_format($sale['total_revenue'] / max($sale['total_transactions'], 1), 2); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const transactionsCtx = document.getElementById('transactionsChart').getContext('2d');

        // Revenue Chart
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($sale) { 
                    return date('M d', strtotime($sale['sale_date'])); 
                }, array_reverse($salesData))); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_map(function($sale) { 
                        return $sale['total_revenue']; 
                    }, array_reverse($salesData))); ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Transactions Chart
        new Chart(transactionsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($sale) { 
                    return date('M d', strtotime($sale['sale_date'])); 
                }, array_reverse($salesData))); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode(array_map(function($sale) { 
                        return $sale['total_transactions']; 
                    }, array_reverse($salesData))); ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Export functions
        function exportToPDF() {
            window.location.href = 'get_sale_details.php?export=pdf&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&report_type=<?php echo $reportType; ?>';
        }

        function exportToCSV() {
            window.location.href = 'get_sale_details.php?export=csv&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&report_type=<?php echo $reportType; ?>';
        }
    </script>
</body>
</html> 