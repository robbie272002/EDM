<?php
require_once __DIR__ . '/../../config/database.php';

// Helper to check for valid JSON
function is_valid_json($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

// Fetch all sales with cashier name
$stmt = $pdo->query("
    SELECT s.*, u.name AS cashier_name
    FROM sales s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
");
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch activity logs for sales
$stmt = $pdo->query("
    SELECT al.*, u.name AS user_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    WHERE al.action_type IN ('create_sale', 'void_sale', 'refund_sale', 'cashier_sale', 'cashier_void', 'cashier_refund')
    ORDER BY al.created_at DESC
");
$activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine sales and activity logs
$combined = [];
foreach ($sales as $sale) {
    $combined[] = [
        'type' => 'sale',
        'date' => $sale['created_at'],
        'transaction_id' => $sale['transaction_id'],
        'user' => $sale['cashier_name'],
        'action' => 'Sale',
        'description' => '',
        'total' => $sale['total_amount'],
        'payment_method' => $sale['payment_method'],
        'details' => json_encode($sale) // for modal
    ];
}
foreach ($activityLogs as $log) {
    if ($log['action_type'] === 'create_sale') continue;
    $combined[] = [
        'type' => 'activity',
        'date' => $log['created_at'],
        'transaction_id' => $log['transaction_id'] ?? '',
        'user' => $log['user_name'],
        'action' => $log['action_type'],
        'description' => $log['description'],
        'total' => '',
        'payment_method' => '',
        'details' => $log['new_value']
    ];
}
// Sort by date descending
usort($combined, function($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});

$view = $_GET['view'] ?? 'transactions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-screen overflow-hidden">
<div class="flex h-full">
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
    <div class="flex-1 flex h-full ml-0 transition-all duration-200 p-4 gap-4 overflow-y-auto" id="main-panels">
        <div class="bg-white rounded-2xl shadow-lg p-6 h-full flex-1 flex flex-col transition-all duration-200">
            <div class="pos-header text-white p-4 rounded-lg mb-6 flex justify-between items-center bg-gradient-to-r from-indigo-700 to-indigo-500">
                <h1 class="text-2xl font-bold">Audit Logs / Sales Transactions</h1>
            </div>

            <!-- Unified Transactions & Activity Logs Table -->
            <div class="bg-white rounded-xl shadow p-6 mb-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Time</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($combined as $row): ?>
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($row['date']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($row['transaction_id']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($row['user']) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full
                                    <?php
                                    if ($row['type'] === 'sale') {
                                        echo 'bg-indigo-100 text-indigo-800';
                                    } else {
                                        switch($row['action']) {
                                            case 'cashier_sale':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'void_sale':
                                            case 'cashier_void':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            case 'refund_sale':
                                            case 'cashier_refund':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                    }
                                    ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['action']))) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= $row['total'] !== '' ? '$' . number_format($row['total'], 2) : '' ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($row['payment_method']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html> 