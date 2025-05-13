<?php
require_once __DIR__ . '/../../auth/check_session.php';
$user = checkAuth('admin');

require_once __DIR__ . '/../../../config/database.php';

// Get product ID from query string
$product_id = $_GET['product_id'] ?? null;

// Build query for stock history
$query = "
    SELECT sh.*, p.name as product_name, u.name as user_name
    FROM stock_history sh
    JOIN products p ON sh.product_id = p.id
    JOIN users u ON sh.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($product_id) {
    $query .= " AND sh.product_id = ?";
    $params[] = $product_id;
}

$query .= " ORDER BY sh.created_at DESC LIMIT 100";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread alerts
$stmt = $pdo->query("
    SELECT sa.*, p.name as product_name
    FROM stock_alerts sa
    JOIN products p ON sa.product_id = p.id
    WHERE sa.is_read = 0
    ORDER BY sa.created_at DESC
");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get inventory valuation
$stmt = $pdo->query("
    SELECT 
        SUM(stock * cost_price) as total_value,
        COUNT(*) as total_products,
        SUM(CASE WHEN stock <= stock_threshold THEN 1 ELSE 0 END) as low_stock_count
    FROM products
");
$inventory_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory History - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../shared/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 p-8 overflow-y-auto max-h-screen">
            <!-- Back Button -->
            <div class="mb-6">
                <a href="index.php" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Inventory
                </a>
            </div>

            <!-- Inventory Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Total Inventory Value</h3>
                    <p class="text-3xl font-bold text-indigo-600">
                        $<?php echo number_format($inventory_stats['total_value'], 2); ?>
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Total Products</h3>
                    <p class="text-3xl font-bold text-indigo-600">
                        <?php echo number_format($inventory_stats['total_products']); ?>
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Low Stock Items</h3>
                    <p class="text-3xl font-bold text-red-600">
                        <?php echo number_format($inventory_stats['low_stock_count']); ?>
                    </p>
                </div>
            </div>

            <!-- Stock Alerts -->
            <?php if (!empty($alerts)): ?>
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Stock Alerts</h2>
                    <div class="space-y-4">
                        <?php foreach ($alerts as $alert): ?>
                        <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg">
                            <div>
                                <p class="font-medium text-red-800"><?php echo htmlspecialchars($alert['message']); ?></p>
                                <p class="text-sm text-red-600">
                                    <?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?>
                                </p>
                            </div>
                            <form action="mark_alert_read.php" method="POST" class="inline">
                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stock History -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Stock History</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Previous Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($history as $record): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y H:i', strtotime($record['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($record['product_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $record['action'] === 'add' ? 'bg-green-100 text-green-800' : 
                                                    ($record['action'] === 'pullout' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                            <?php echo ucfirst($record['action']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($record['quantity']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($record['previous_stock']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($record['new_stock']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($record['user_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($record['notes']); ?>
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
</body>
</html> 