<?php
require_once __DIR__ . '/../auth/check_session.php';
$user = checkAuth('admin');

require_once __DIR__ . '/../../config/database.php';

// Fetch dashboard statistics
try {
    // Get total active users (excluding super admin and inactive users)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id != 1 AND status = 'active'");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();

    // Get total active products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $stmt->execute();
    $totalProducts = $stmt->fetchColumn();

    // Get low stock products (only active products, stock < 10 and > 0)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE stock < 10 AND stock > 0 AND status = 'active'");
    $stmt->execute();
    $lowStockCount = $stmt->fetchColumn();

    // Get total sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM sales");
    $stmt->execute();
    $totalSales = $stmt->fetchColumn();

    // Get total revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales");
    $stmt->execute();
    $totalRevenue = $stmt->fetchColumn();

    // Get monthly sales data
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_amount
        FROM sales 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly user registrations (excluding super admin and inactive users)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as new_users
        FROM users 
        WHERE id != 1 
        AND status = 'active'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top products (only active products)
    $stmt = $pdo->prepare("
        SELECT 
            p.name, 
            COUNT(si.id) as sale_count,
            SUM(si.quantity) as total_quantity
        FROM products p 
        LEFT JOIN sale_items si ON p.id = si.product_id 
        WHERE p.status = 'active'
        AND si.id IS NOT NULL
        GROUP BY p.id, p.name 
        ORDER BY sale_count DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get category distribution (only active products)
    $stmt = $pdo->prepare("
        SELECT 
            c.name as category,
            COUNT(p.id) as count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id 
        WHERE p.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY count DESC
    ");
    $stmt->execute();
    $categoryDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get daily sales for the last 30 days
    $stmt = $pdo->prepare("
        WITH RECURSIVE dates AS (
            SELECT CURDATE() - INTERVAL 29 DAY as date
            UNION ALL
            SELECT date + INTERVAL 1 DAY
            FROM dates
            WHERE date < CURDATE()
        )
        SELECT 
            DATE(d.date) as date,
            COALESCE(COUNT(s.id), 0) as transactions,
            COALESCE(SUM(s.subtotal), 0) as amount
        FROM dates d
        LEFT JOIN sales s ON DATE(s.created_at) = d.date
        GROUP BY d.date
        ORDER BY d.date ASC
    ");
    $stmt->execute();
    $dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates for chart labels
    $dailySalesLabels = array_map(function($sale) {
        return date('M d', strtotime($sale['date']));
    }, $dailySales);

    // Get top payment methods
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count
        FROM sales 
        GROUP BY payment_method
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Sales
    $stmt = $pdo->query("
        SELECT s.*, u.name as cashier_name 
        FROM sales s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unique Visitors (Based on total unique customers from sales)
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT user_id) as current_month,
            (SELECT COUNT(DISTINCT user_id) 
             FROM sales 
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
             AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as last_month
        FROM sales 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $visitorData = $stmt->fetch(PDO::FETCH_ASSOC);
    $uniqueVisitors = $visitorData['current_month'];
    $visitorChange = $visitorData['last_month'] > 0 
        ? round((($visitorData['current_month'] - $visitorData['last_month']) / $visitorData['last_month']) * 100, 2)
        : 0;

    // Total Transactions
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as current_month,
            (SELECT COUNT(*) 
             FROM sales 
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
             AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as last_month
        FROM sales 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $transactionData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTransactions = $transactionData['current_month'];
    $transactionChange = $transactionData['last_month'] > 0 
        ? round((($transactionData['current_month'] - $transactionData['last_month']) / $transactionData['last_month']) * 100, 2)
        : 0;

    // Sales Conversion Rate
    $stmt = $pdo->query("
        SELECT 
            (COUNT(DISTINCT sale_items.sale_id) * 100.0 / COUNT(DISTINCT products.id)) as current_rate,
            (SELECT (COUNT(DISTINCT si.sale_id) * 100.0 / COUNT(DISTINCT p.id))
             FROM products p
             LEFT JOIN sale_items si ON p.id = si.product_id
             WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
             AND p.created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as last_rate
        FROM products 
        LEFT JOIN sale_items ON products.id = sale_items.product_id
        WHERE products.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $conversionData = $stmt->fetch(PDO::FETCH_ASSOC);
    $conversionRate = round($conversionData['current_rate'], 2);
    $conversionChange = $conversionData['last_rate'] > 0 
        ? round($conversionData['current_rate'] - $conversionData['last_rate'], 2)
        : 0;

    // Average Transaction Time
    $stmt = $pdo->query("
        SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, s1.created_at, s2.created_at)) as current_avg,
            (SELECT AVG(TIMESTAMPDIFF(MINUTE, s1.created_at, s2.created_at))
             FROM sales s1
             JOIN sales s2 ON s1.user_id = s2.user_id 
             AND s1.created_at < s2.created_at
             WHERE s1.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
             AND s1.created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as last_avg
        FROM sales s1
        JOIN sales s2 ON s1.user_id = s2.user_id 
        AND s1.created_at < s2.created_at
        WHERE s1.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $timeData = $stmt->fetch(PDO::FETCH_ASSOC);
    $avgTransactionTime = round($timeData['current_avg'], 2);
    $timeChange = $timeData['last_avg'] > 0 
        ? round((($timeData['current_avg'] - $timeData['last_avg']) / $timeData['last_avg']) * 100, 2)
        : 0;

} catch(PDOException $e) {
    die("Error fetching dashboard data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        /* Add new styles for fixed sidebar */
        .sidebar-fixed {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            overflow-y: auto;
            z-index: 50;
        }
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            width: calc(100% - 280px);
        }
        @media (max-width: 768px) {
            .sidebar-fixed {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar-fixed.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div x-data="{ sidebarOpen: false }" class="min-h-screen">
        <!-- Sidebar -->
        <div class="sidebar-fixed" :class="{ 'open': sidebarOpen }">
        <?php include __DIR__ . '/shared/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="p-8">
                <!-- Welcome Section -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                    <p class="text-gray-600">Here's what's happening with your store today.</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                    <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-50 text-blue-600">
                            <i class="fas fa-shopping-cart text-2xl"></i>
                        </div>
                        <div class="ml-4">
                                <h3 class="text-gray-500 text-sm font-medium">Total Sales</h3>
                                <p class="text-2xl font-bold text-gray-800">$<?php echo number_format($totalSales, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-50 text-green-600">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm font-medium">Active Users</h3>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $totalUsers; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-50 text-yellow-600">
                                <i class="fas fa-box text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm font-medium">Products</h3>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $totalProducts; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-50 text-red-600">
                                <i class="fas fa-exclamation-triangle text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm font-medium">Low Stock</h3>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $lowStockCount; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Unique Visitors</h3>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-800"><?php echo number_format($uniqueVisitors); ?></span>
                            <span class="ml-2 text-sm <?php echo $visitorChange >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                <?php echo $visitorChange >= 0 ? '+' : ''; ?><?php echo $visitorChange; ?>% 
                                <span class="text-gray-500">vs last month</span>
                            </span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Transactions</h3>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-800"><?php echo number_format($totalTransactions); ?></span>
                            <span class="ml-2 text-sm <?php echo $transactionChange >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                <?php echo $transactionChange >= 0 ? '+' : ''; ?><?php echo $transactionChange; ?>% 
                                <span class="text-gray-500">vs last month</span>
                            </span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Conversion Rate</h3>
                    <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-800"><?php echo $conversionRate; ?>%</span>
                            <span class="ml-2 text-sm <?php echo $conversionChange >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                <?php echo $conversionChange >= 0 ? '+' : ''; ?><?php echo $conversionChange; ?>% 
                                <span class="text-gray-500">vs last month</span>
                            </span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6 card-hover border border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Avg. Transaction Time</h3>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-800"><?php echo number_format($avgTransactionTime, 2); ?> mins</span>
                            <span class="ml-2 text-sm <?php echo $timeChange >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                <?php echo $timeChange >= 0 ? '+' : ''; ?><?php echo $timeChange; ?>% 
                                <span class="text-gray-500">vs last month</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Sales Trend Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Sales Trend</h2>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- User Growth Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">User Growth</h2>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <canvas id="userChart"></canvas>
                        </div>
                    </div>

                    <!-- Top Products Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Top Products</h2>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <canvas id="productsChart"></canvas>
                        </div>
                    </div>

                    <!-- Payment Methods Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Payment Methods</h2>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Products and Categories -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Top Products List</h2>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                        <div class="space-y-4">
                            <?php if (!empty($topProducts)): ?>
                                <?php foreach ($topProducts as $product): ?>
                                <div class="flex justify-between items-center p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                    <span class="text-gray-700 font-medium"><?php echo htmlspecialchars($product['name']); ?></span>
                                    <span class="font-semibold text-blue-600"><?php echo $product['sale_count']; ?> Sales</span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-gray-500 text-center py-4">No product data available</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sales by Category Chart -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Sales by Category</h2>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Sales Trend -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Daily Sales Trend</h2>
                        <button class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="dailySalesChart"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Sales</h2>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cashier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentSales as $sale): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    #<?php echo htmlspecialchars($sale['transaction_id']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    $<?php echo number_format($sale['total_amount'], 2); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($sale['cashier_name']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    <?php echo date('M d, Y H:i', strtotime($sale['created_at'])); ?>
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

    <!-- Initialize chart contexts -->
    <script>
    // Sales Trend Chart
    new Chart(document.getElementById('salesChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthlySales, 'month')); ?>,
                    datasets: [{
                        label: 'Monthly Sales ($)',
                data: <?php echo json_encode(array_column($monthlySales, 'total_amount')); ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
            maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => '$' + value
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });

            // User Growth Chart
    new Chart(document.getElementById('userChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($monthlyUsers, 'month')); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode(array_column($monthlyUsers, 'new_users')); ?>,
                        backgroundColor: 'rgb(34, 197, 94)',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
            maintainAspectRatio: false,
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

            // Top Products Chart
    new Chart(document.getElementById('productsChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($topProducts, 'name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($topProducts, 'sale_count')); ?>,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
            maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
            }
                }
            });

    // Payment Methods Chart
    new Chart(document.getElementById('paymentChart').getContext('2d'), {
                type: 'bar',
                data: {
            labels: <?php echo json_encode(array_column($topChannels, 'payment_method')); ?>,
                    datasets: [{
                label: 'Transactions',
                data: <?php echo json_encode(array_column($topChannels, 'count')); ?>,
                        backgroundColor: 'rgb(37, 99, 235)'
            }]
                },
                options: {
                    responsive: true,
            maintainAspectRatio: false,
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

            // Sales by Category Chart
    new Chart(document.getElementById('categoryChart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($categoryDistribution, 'category')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($categoryDistribution, 'count')); ?>,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
            maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
            }
                }
            });

    // Daily Sales Trend Chart
    new Chart(document.getElementById('dailySalesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dailySalesLabels); ?>,
            datasets: [{
                label: 'Daily Sales',
                data: <?php echo json_encode(array_column($dailySales, 'amount')); ?>,
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$' + context.raw.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    </script>
</body>
</html>