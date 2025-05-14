<?php
require_once __DIR__ . '/../../config/database.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Helper to check for valid JSON
function is_valid_json($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

// Base query for sales with parameters
$salesParams = [];
$salesQuery = "
    SELECT 
        s.*,
        u.name AS cashier_name,
        si.quantity,
        si.price as item_price,
        p.name as product_name,
        p.sku as product_sku
    FROM sales s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE 1=1
";

// Add sales filters
if ($search) {
    $salesQuery .= " AND (s.transaction_id LIKE ? OR u.name LIKE ? OR s.payment_method LIKE ?)";
    $salesParams = array_merge($salesParams, ["%$search%", "%$search%", "%$search%"]);
}
if ($startDate) {
    $salesQuery .= " AND DATE(s.created_at) >= ?";
    $salesParams[] = $startDate;
}
if ($endDate) {
    $salesQuery .= " AND DATE(s.created_at) <= ?";
    $salesParams[] = $endDate;
}

$salesQuery .= " ORDER BY s.created_at DESC";

// Execute sales query
$stmt = $pdo->prepare($salesQuery);
$stmt->execute($salesParams);
$temp_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group sale items by transaction
$sales = [];
foreach ($temp_sales as $row) {
    $transaction_id = $row['transaction_id'];
    
    if (!isset($sales[$transaction_id])) {
        $sales[$transaction_id] = [
            'id' => $row['id'],
            'transaction_id' => $row['transaction_id'],
            'created_at' => $row['created_at'],
            'cashier_name' => $row['cashier_name'],
            'status' => $row['status'],
            'total_amount' => $row['total_amount'],
            'subtotal' => $row['subtotal'],
            'tax_amount' => $row['tax_amount'],
            'discount_amount' => $row['discount_amount'],
            'payment_method' => $row['payment_method'],
            'payment_reference' => $row['payment_reference'] ?? null,
            'notes' => $row['notes'] ?? null,
            'items' => []
        ];
    }
    
    if ($row['product_name']) {  // Only add if product exists
        $sales[$transaction_id]['items'][] = [
            'name' => $row['product_name'],
            'sku' => $row['product_sku'],
            'quantity' => $row['quantity'],
            'price' => $row['item_price']
        ];
    }
}
$sales = array_values($sales);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .transaction-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out, opacity 0.3s ease-out;
        }
        .transaction-details.show {
            max-height: 2000px;
        }
        .rotate-icon {
            transform: rotate(0deg);
            transition: transform 0.3s ease;
        }
        .rotate-icon.active {
            transform: rotate(180deg);
        }
        .transaction-row:hover {
            background-color: #f9fafb;
        }
        .transaction-details-content {
            opacity: 0;
            transition: opacity 0.3s ease-out;
        }
        .transaction-details.show .transaction-details-content {
            opacity: 1;
        }
        
        .content-wrapper {
            height: calc(100vh - 2rem);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
            margin: 0;
        }
        
        .fixed-header {
            background-color: #f9fafb;
            position: sticky;
            top: 0;
            z-index: 10;
            flex-shrink: 0;
            padding: 1.5rem;
        }
        
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0 1.5rem 1.5rem 1.5rem;
        }
        
        /* Enhanced scrollbar styling */
        .scrollable-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
            margin: 4px 0;
        }
        
        .scrollable-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
            border: 2px solid #f1f1f1;
        }
        
        .scrollable-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Table header improvements */
        .table-container {
            position: relative;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .table-header {
            position: sticky;
            top: 0;
            background: #f9fafb;
            z-index: 10;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .receipt {
            background: white;
            max-width: 400px;
            margin: 0 auto;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            font-family: 'Courier New', monospace;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed #ccc;
        }
        
        .receipt-items {
            margin: 1rem 0;
            padding: 1rem 0;
            border-bottom: 1px dashed #ccc;
        }
        
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .receipt-item-details {
            flex-grow: 1;
        }
        
        .receipt-totals {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed #ccc;
        }
        
        .receipt-total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }
        
        .receipt-final-total {
            font-weight: bold;
            font-size: 1.1em;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px dashed #ccc;
        }
        .filter-section {
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        .filter-bar {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .filter-input {
            width: 100%;
            height: 2.5rem;
            padding: 0.5rem 0.75rem;
            background-color: white;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: #1F2937;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .filter-input:hover {
            border-color: #9CA3AF;
        }
        .filter-input:focus {
            outline: none;
            border-color: #6366F1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        select.filter-input {
            padding-right: 2rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .modal-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-screen bg-gray-50" x-data="{ 
    searchQuery: '<?= htmlspecialchars($search) ?>',
    showDateModal: false,
    startDate: '<?= htmlspecialchars($startDate) ?>',
    endDate: '<?= htmlspecialchars($endDate) ?>',
    dateError: '',
    today: new Date().toISOString().split('T')[0],
    sidebarOpen: false,
    activeTransaction: null,
    hasVisibleTransactions: true,
    
    init() {
        if (!this.startDate) {
            const lastMonth = new Date();
            lastMonth.setMonth(lastMonth.getMonth() - 1);
            this.startDate = lastMonth.toISOString().split('T')[0];
        }
        if (!this.endDate) {
            this.endDate = this.today;
        }
        this.$nextTick(() => {
            this.filterTransactions();
        });
    },

    filterTransactions() {
        const searchLower = this.searchQuery.toLowerCase();
        const rows = document.querySelectorAll('tbody tr.transaction-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            // Get all searchable content from the row and its details
            const transactionId = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const cashier = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const status = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            const amount = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
            const payment = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
            const date = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            
            // Get the details section content
            const detailsId = `details-${transactionId.trim()}`;
            const detailsSection = document.getElementById(detailsId);
            let detailsContent = '';
            if (detailsSection) {
                detailsContent = detailsSection.textContent.toLowerCase();
            }
            
            // Search filter - include all relevant content
            const matchesSearch = !searchLower || 
                transactionId.includes(searchLower) ||
                cashier.includes(searchLower) ||
                status.includes(searchLower) ||
                amount.includes(searchLower) ||
                payment.includes(searchLower) ||
                detailsContent.includes(searchLower);
            
            // Date range filtering
            let matchesDateRange = true;
            if (this.startDate || this.endDate) {
                const transDate = new Date(date);
                const startDate = this.startDate ? new Date(this.startDate) : null;
                const endDate = this.endDate ? new Date(this.endDate) : null;
                
                if (startDate) {
                    startDate.setHours(0, 0, 0, 0);
                    if (transDate < startDate) matchesDateRange = false;
                }
                if (endDate) {
                    endDate.setHours(23, 59, 59, 999);
                    if (transDate > endDate) matchesDateRange = false;
                }
            }
            
            // Apply visibility
            const isVisible = matchesSearch && matchesDateRange;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
            
            // Hide/show details row if it exists
            const detailsRow = row.nextElementSibling;
            if (detailsRow && detailsRow.classList.contains('transaction-details')) {
                detailsRow.style.display = isVisible ? '' : 'none';
            }
        });

        this.hasVisibleTransactions = visibleCount > 0;
    }
}">
    <!-- Mobile menu button -->
    <button @click="sidebarOpen = !sidebarOpen" 
            class="md:hidden fixed top-4 left-4 z-50 p-2 rounded-md bg-white shadow-md text-gray-600 hover:text-gray-900 focus:outline-none">
        <i class="fas fa-bars text-xl"></i>
    </button>

    <div class="min-h-screen flex">
        <!-- Include sidebar -->
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="flex-1 min-h-screen">
            <div class="content-wrapper">
                <div class="fixed-header">
                    <!-- Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">Transactions</h1>
                        <p class="text-gray-600 mt-1">View and manage your transaction history</p>
                    </div>

                    <!-- Filter Bar -->
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <div class="flex space-x-4 flex-1">
                                    <div class="relative flex-1">
                                        <input type="text" 
                                               placeholder="Search transactions..." 
                                               class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                                               x-model="searchQuery"
                                               @input="filterTransactions">
                                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                    </div>
                                    <button @click="showDateModal = true" 
                                            class="px-4 py-2 border rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <i class="fas fa-calendar mr-2"></i> Date Range
                                    </button>
                                </div>
                            </div>

                            <!-- Active Filters -->
                            <div class="mt-4 flex flex-wrap gap-2" x-show="searchQuery || startDate || endDate">
                                <div class="text-sm text-gray-600 flex flex-wrap gap-2">
                                    <template x-if="searchQuery">
                                        <span class="bg-gray-100 px-3 py-1 rounded-full flex items-center">
                                            <span class="font-medium mr-1">Search:</span>
                                            <span x-text="searchQuery"></span>
                                            <button @click="searchQuery = ''; filterTransactions()" class="ml-2 text-gray-500 hover:text-gray-700">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </span>
                                    </template>
                                    <template x-if="startDate || endDate">
                                        <span class="bg-gray-100 px-3 py-1 rounded-full flex items-center">
                                            <span class="font-medium mr-1">Date Range:</span>
                                            <span x-text="startDate ? new Date(startDate).toLocaleDateString() : 'Any'"></span>
                                            <span class="mx-1">to</span>
                                            <span x-text="endDate ? new Date(endDate).toLocaleDateString() : 'Any'"></span>
                                            <button @click="startDate = ''; endDate = ''; filterTransactions()" class="ml-2 text-gray-500 hover:text-gray-700">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>

                <div class="scrollable-content">
                    <!-- Transactions Table -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="table-container">
                <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 table-header">
                        <tr>
                                        <th scope="col" class="w-10 px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Time</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cashier</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($sales as $sale): ?>
                                    <tr class="transaction-row">
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <button onclick="toggleDetails('<?= $sale['transaction_id'] ?>')" class="text-gray-400 hover:text-gray-600">
                                                <i class="fas fa-chevron-down rotate-icon" id="icon-<?= $sale['transaction_id'] ?>"></i>
                                            </button>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-indigo-600">
                                            <?= htmlspecialchars($sale['transaction_id']) ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-600">
                                            <?= htmlspecialchars($sale['cashier_name']) ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full font-medium <?= 
                                                $sale['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                ($sale['status'] === 'void' ? 'bg-red-100 text-red-800' : 
                                                'bg-yellow-100 text-yellow-800') ?>">
                                                <?= ucfirst(htmlspecialchars($sale['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            $<?= number_format($sale['total_amount'], 2) ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                                <?= ucfirst(htmlspecialchars($sale['payment_method'])) ?>
                                </span>
                            </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="p-0 border-b">
                                            <div id="details-<?= $sale['transaction_id'] ?>" class="transaction-details">
                                                <div class="transaction-details-content bg-gray-50 p-6">
                                                    <div class="receipt">
                                                        <div class="receipt-header">
                                                            <h2 class="text-xl font-bold mb-2">SALES RECEIPT</h2>
                                                            <p class="text-sm"><?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?></p>
                                                            <p class="text-sm">Transaction #: <?= htmlspecialchars($sale['transaction_id']) ?></p>
                                                            <p class="text-sm">Cashier: <?= htmlspecialchars($sale['cashier_name']) ?></p>
                                                        </div>

                                                        <?php if (!empty($sale['items'])): ?>
                                                        <div class="receipt-items">
                                                            <?php foreach ($sale['items'] as $item): ?>
                                                            <div class="receipt-item">
                                                                <div class="receipt-item-details">
                                                                    <div><?= htmlspecialchars($item['name']) ?></div>
                                                                    <div class="text-sm text-gray-600">
                                                                        <?= $item['quantity'] ?> @ $<?= number_format($item['price'], 2) ?>
                                                                    </div>
                                                                </div>
                                                                <div class="text-right">
                                                                    $<?= number_format($item['quantity'] * $item['price'], 2) ?>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <?php endif; ?>

                                                        <div class="receipt-totals">
                                                            <div class="receipt-total-line">
                                                                <span>Subtotal:</span>
                                                                <span>$<?= number_format($sale['subtotal'], 2) ?></span>
                                                            </div>
                                                            <div class="receipt-total-line">
                                                                <span>Tax:</span>
                                                                <span>$<?= number_format($sale['tax_amount'], 2) ?></span>
                                                            </div>
                                                            <?php if ($sale['discount_amount'] > 0): ?>
                                                            <div class="receipt-total-line">
                                                                <span>Discount:</span>
                                                                <span>-$<?= number_format($sale['discount_amount'], 2) ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <div class="receipt-final-total">
                                                                <div class="receipt-total-line">
                                                                    <span>TOTAL:</span>
                                                                    <span>$<?= number_format($sale['total_amount'], 2) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mt-4 pt-4 border-t border-dashed text-center">
                                                            <div class="text-sm">
                                                                <div class="font-medium">Payment Information</div>
                                                                <div>Method: <?= ucfirst($sale['payment_method']) ?></div>
                                                                <?php if (!empty($sale['payment_reference'])): ?>
                                                                <div>Reference: <?= htmlspecialchars($sale['payment_reference']) ?></div>
                                                                <?php endif; ?>
                                                                <div>Status: <?= ucfirst($sale['status']) ?></div>
                                                            </div>
                                                        </div>

                                                        <?php if (!empty($sale['notes'])): ?>
                                                        <div class="mt-4 pt-4 border-t border-dashed text-center">
                                                            <div class="text-sm">
                                                                <div class="font-medium">Notes</div>
                                                                <div><?= htmlspecialchars($sale['notes']) ?></div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <div class="mt-4 pt-4 border-t border-dashed text-center text-sm text-gray-600">
                                                            <p>Thank you for your business!</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- No Results Message -->
                <div x-show="!hasVisibleTransactions" 
                     x-cloak
                     class="py-12 text-center bg-gray-50 rounded-lg border-t">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-receipt text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Transactions Found</h3>
                    <p class="text-sm text-gray-600">
                        <template x-if="startDate && endDate">
                            <span>No transactions have been found for the selected date range.</span>
                        </template>
                        <template x-if="searchQuery && !startDate && !endDate">
                            <span>No transactions match your search criteria.</span>
                        </template>
                        <template x-if="!searchQuery && !startDate && !endDate">
                            <span>No transactions available.</span>
                        </template>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>

    <!-- Date Range Modal -->
    <div x-show="showDateModal" 
         class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30" 
         x-cloak
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.away="showDateModal = false">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-semibold mb-6 text-gray-800">Custom Date Range</h2>
            <form @submit.prevent="filterTransactions(); showDateModal = false" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="modalStartDate" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" 
                               id="modalStartDate" 
                               x-model="startDate"
                               :max="endDate || today"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="modalEndDate" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" 
                               id="modalEndDate" 
                               x-model="endDate"
                               :min="startDate"
                               :max="today"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div x-show="dateError" class="text-sm text-red-600" x-text="dateError"></div>
                <div class="flex justify-end space-x-3">
                    <button type="button" 
                            @click="showDateModal = false"
                            class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleDetails(transactionId) {
        const detailsDiv = document.getElementById(`details-${transactionId}`);
        const icon = document.getElementById(`icon-${transactionId}`);
        
        detailsDiv.classList.toggle('show');
        icon.classList.toggle('active');
        
        // Scroll the expanded content into view smoothly
        if (detailsDiv.classList.contains('show')) {
            setTimeout(() => {
                detailsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }
    }

    let searchTimeout;
    function debounceSearch(value) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500);
    }

    function applyDateRange() {
        const start = new Date(this.startDate);
        const end = new Date(this.endDate);
        const today = new Date();
        
        // Reset time to midnight for accurate comparison
        start.setHours(0, 0, 0, 0);
        end.setHours(0, 0, 0, 0);
        today.setHours(0, 0, 0, 0);

        if (start > today || end > today) {
            this.dateError = 'Dates cannot be in the future';
            return;
        }

        if (start > end) {
            this.dateError = 'Start date cannot be after end date';
            return;
        }

        this.showDateModal = false;
        this.submitForm();
    }

    function resetFilters() {
        window.location.href = 'transaction-history.php';
    }
    </script>
</body>
</html> 