<?php
require_once __DIR__ . '/../../../config/database.php';

try {
    // Fetch products for inventory management with direct stock values
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.stock,
            p.sku,
            p.image,
            p.status,
            c.name as category_name,
            COALESCE(SUM(si.quantity), 0) as sold_last_30_days,
            COALESCE(SUM(si.quantity * si.price), 0) as total_revenue
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN sale_items si ON p.id = si.product_id 
            AND si.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE p.status = 'active'
        GROUP BY 
            p.id, p.name, p.stock, p.sku, p.image, p.status, c.name
        ORDER BY p.name ASC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all categories for the dropdown
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    $products = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
       .stock-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            transition: all 0.2s ease;

            /* Simulate blurred border */
            position: relative;
            z-index: 0;
        }

        /* Custom scrollbar styling */
        select {
            max-height: 200px;
            overflow-y: auto;
        }

        /* Webkit browsers (Chrome, Safari) */
        select::-webkit-scrollbar {
            width: 8px;
        }

        select::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        select::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        select::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Firefox */
        select {
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
        }

        .stock-badge.low {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .stock-badge.out {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        .stock-badge.in {
            background-color: #DCFCE7;
            color: #166534;
        }
        .stock-badge:hover {
            transform: translateY(-1px);
        }
        .table-header {
            background-color: #F9FAFB;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../shared/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">Inventory Management</h1>
                    <p class="mt-2 text-sm text-gray-600">Manage your product inventory, track stock levels, and monitor sales.</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-boxes text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Products</p>
                                <p class="text-2xl font-semibold text-gray-900"><?= count($products) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Low Stock Items</p>
                                <p class="text-2xl font-semibold text-gray-900">
                                    <?= count(array_filter($products, fn($p) => $p['stock'] < 10 && $p['stock'] > 0)) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-times-circle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Out of Stock</p>
                                <p class="text-2xl font-semibold text-gray-900">
                                    <?= count(array_filter($products, fn($p) => $p['stock'] === 0)) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-chart-line text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Sales (30d)</p>
                                <p class="text-2xl font-semibold text-gray-900">
                                    $<?= number_format(array_sum(array_column($products, 'total_revenue')), 2) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-medium text-gray-900">Filters</h2>
                            <button onclick="resetFilters()" class="text-sm text-gray-600 hover:text-gray-900">
                                <i class="fas fa-redo-alt mr-1"></i> Reset Filters
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Search -->
                            <div class="relative">
                                <label class="block text-sm font-medium mb-1">Search Products</label>
                                <div class="relative">
                                    <input type="text" id="searchInput" placeholder="Search by name or SKU..." 
                                           class="w-full h-10 rounded-lg border-gray-300 pl-10 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Category Filter -->
                            <div x-data="{
                                    open: false,
                                    selected: '',
                                    options: [
                                        { value: '', label: 'All Categories' },
                                        { value: 'uncategorized', label: 'Uncategorized' },
                                        <?php foreach ($categories as $category): ?>
                                        { value: '<?= htmlspecialchars($category['name']) ?>', label: '<?= htmlspecialchars($category['name']) ?>' },
                                        <?php endforeach; ?>
                                    ],
                                    selectOption(option) {
                                        this.selected = option.value;
                                        this.open = false;
                                        document.getElementById('categoryFilterHidden').value = option.value;
                                        if (typeof filterAndSort === 'function') filterAndSort();
                                    }
                                }" class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                <input type="hidden" id="categoryFilterHidden" x-model="selected">
                                <button type="button"
                                    @click="open = !open"
                                    class="w-full h-10 rounded-lg border border-gray-300 bg-white shadow-sm flex items-center justify-between px-3 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                >
                                    <span x-text="options.find(o => o.value === selected)?.label || 'All Categories'"></span>
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="open" @click.away="open = false"
                                    class="absolute z-10 mt-1 w-full bg-white rounded-lg shadow-lg max-h-48 overflow-y-auto border border-gray-300"
                                    style="display: none;"
                                    x-transition
                                >
                                    <template x-for="option in options" :key="option.value">
                                        <div
                                            @click="selectOption(option)"
                                            class="px-4 py-2 cursor-pointer hover:bg-gray-100"
                                            :class="{'bg-gray-100': selected === option.value}"
                                            x-text="option.label"
                                        ></div>
                                    </template>
                                </div>
                            </div>

                            <!-- Stock Status Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stock Status</label>
                                <select id="stockFilter" class="w-full h-10 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Stock Levels</option>
                                    <option value="low">Low Stock (< 10)</option>
                                    <option value="out">Out of Stock (0)</option>
                                    <option value="in">In Stock (> 0)</option>
                                </select>
                            </div>

                            <!-- Sort Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                                <select id="sortFilter" class="w-full h-10 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="name">Name (A-Z)</option>
                                    <option value="stock">Stock Level (Low to High)</option>
                                    <option value="sales">Sales (30d) (High to Low)</option>
                                    <option value="revenue">Revenue (High to Low)</option>
                                </select>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales (30d)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($products as $product): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-lg object-cover" 
                                                     src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://via.placeholder.com/40'; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $stockLevel = (int)$product['stock'];
                                        $stockClass = '';
                                        
                                        if ($stockLevel <= 0) {
                                            $stockClass = 'out';
                                        } elseif ($stockLevel < 10) {
                                            $stockClass = 'low';
                                        } else {
                                            $stockClass = 'in';
                                        }
                                        ?>
                                        <div class="flex justify-center">
                                            <span class="stock-badge <?php echo $stockClass; ?>">
                                                <?php echo $stockLevel; ?> units
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($product['sold_last_30_days']); ?> units
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        $<?php echo number_format($product['total_revenue'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="openRestockModal(<?= $product['id'] ?>, <?= (int)$product['stock'] ?>)" 
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-boxes"></i> Restock
                                        </button>
                                        <button onclick="openAdjustModal(<?= $product['id'] ?>, <?= (int)$product['stock'] ?>)" 
                                                class="text-yellow-600 hover:text-yellow-900">
                                            <i class="fas fa-balance-scale"></i> Adjust
                                        </button>
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

    <!-- Restock Modal -->
    <div id="restockModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form action="update_stock.php" method="POST" class="p-6">
                    <input type="hidden" name="product_id" id="restockProductId">
                    <input type="hidden" name="action" value="add">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Restock Product</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">Current Stock</label>
                            <input type="text" id="restockCurrentStock" disabled class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">Add Stock</label>
                            <input type="number" name="quantity" min="1" required 
                                   class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">Reason for Restock</label>
                            <select name="notes" required 
                                    class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                <option value="Regular Restock">Regular Restock</option>
                                <option value="Bulk Order">Bulk Order</option>
                                <option value="Seasonal Stock">Seasonal Stock</option>
                                <option value="Emergency Restock">Emergency Restock</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeRestockModal()" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                            Add Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form action="update_stock.php" method="POST" class="p-6">
                    <input type="hidden" name="product_id" id="adjustProductId">
                    <input type="hidden" name="action" value="set">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Adjust Stock</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Stock</label>
                            <input type="text" id="adjustCurrentStock" disabled class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Stock Level</label>
                            <input type="number" name="quantity" min="0" required 
                                   class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Reason for Adjustment</label>
                            <select name="notes" required 
                                    class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                                <option value="Damaged Goods">Damaged Goods</option>
                                <option value="Lost Inventory">Lost Inventory</option>
                                <option value="Found Inventory">Found Inventory</option>
                                <option value="Stock Count Correction">Stock Count Correction</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeAdjustModal()" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 rounded-lg">
                            Adjust Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize elements
        const searchInput = document.getElementById('searchInput');
        const stockFilter = document.getElementById('stockFilter');
        const sortFilter = document.getElementById('sortFilter');
        const tableBody = document.querySelector('tbody');
        
        if (!tableBody) return;

        const rows = Array.from(tableBody.querySelectorAll('tr'));

        function filterAndSort() {
            const searchTerm = searchInput?.value.toLowerCase() || '';
            const categoryValue = document.getElementById('categoryFilterHidden')?.value || '';
            const stockValue = stockFilter?.value || '';
            const sortValue = sortFilter?.value || 'name';

            // Filter rows
            rows.forEach(row => {
                const name = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                const sku = row.querySelector('td:first-child .text-gray-500')?.textContent.toLowerCase() || '';
                const category = row.querySelector('td:nth-child(2)')?.textContent.trim() || '';
                const stockText = row.querySelector('td:nth-child(3) .stock-badge')?.textContent || '0';
                const stock = parseInt(stockText.replace(/[^0-9]/g, '')) || 0;
                
                const matchesSearch = name.includes(searchTerm) || sku.includes(searchTerm);
                const matchesCategory = !categoryValue || 
                    (categoryValue === 'uncategorized' && category === 'Uncategorized') ||
                    category === categoryValue;
                const matchesStock = !stockValue || 
                    (stockValue === 'low' && stock < 10 && stock > 0) ||
                    (stockValue === 'out' && stock === 0) ||
                    (stockValue === 'in' && stock > 0);
                
                row.style.display = matchesSearch && matchesCategory && matchesStock ? '' : 'none';
            });

            // Sort rows
            const visibleRows = rows.filter(row => row.style.display !== 'none');
            visibleRows.sort((a, b) => {
                let aValue, bValue;
                switch(sortValue) {
                    case 'name':
                        aValue = a.querySelector('td:first-child')?.textContent || '';
                        bValue = b.querySelector('td:first-child')?.textContent || '';
                        break;
                    case 'stock':
                        aValue = parseInt(a.querySelector('td:nth-child(3) .stock-badge')?.textContent.replace(/[^0-9]/g, '') || '0');
                        bValue = parseInt(b.querySelector('td:nth-child(3) .stock-badge')?.textContent.replace(/[^0-9]/g, '') || '0');
                        break;
                    case 'sales':
                        aValue = parseInt(a.querySelector('td:nth-child(4)')?.textContent.replace(/[^0-9]/g, '') || '0');
                        bValue = parseInt(b.querySelector('td:nth-child(4)')?.textContent.replace(/[^0-9]/g, '') || '0');
                        break;
                    case 'revenue':
                        aValue = parseFloat(a.querySelector('td:nth-child(5)')?.textContent.replace(/[^0-9.]/g, '') || '0');
                        bValue = parseFloat(b.querySelector('td:nth-child(5)')?.textContent.replace(/[^0-9.]/g, '') || '0');
                        break;
                }
                return aValue > bValue ? 1 : -1;
            });

            // Reorder rows in the table
            visibleRows.forEach(row => tableBody.appendChild(row));
        }

        // Add event listeners
        if (searchInput) searchInput.addEventListener('input', filterAndSort);
        if (stockFilter) stockFilter.addEventListener('change', filterAndSort);
        if (sortFilter) sortFilter.addEventListener('change', filterAndSort);

        // Make filterAndSort available globally
        window.filterAndSort = filterAndSort;
    });

    // Reset filters function
    function resetFilters() {
        const searchInput = document.getElementById('searchInput');
        const categoryFilterHidden = document.getElementById('categoryFilterHidden');
        const stockFilter = document.getElementById('stockFilter');
        const sortFilter = document.getElementById('sortFilter');
        
        if (searchInput) searchInput.value = '';
        if (categoryFilterHidden) categoryFilterHidden.value = '';
        if (stockFilter) stockFilter.value = '';
        if (sortFilter) sortFilter.value = 'name';
        
        if (typeof window.filterAndSort === 'function') {
            window.filterAndSort();
        }
    }

    // Modal functions
    function openRestockModal(productId, currentStock) {
        const modal = document.getElementById('restockModal');
        const productIdInput = document.getElementById('restockProductId');
        const currentStockInput = document.getElementById('restockCurrentStock');
        
        if (modal && productIdInput && currentStockInput) {
            modal.classList.remove('hidden');
            productIdInput.value = productId;
            currentStockInput.value = currentStock + ' units';
        }
    }

    function closeRestockModal() {
        const modal = document.getElementById('restockModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openAdjustModal(productId, currentStock) {
        console.log('Opening adjust modal with:', { productId, currentStock });
        const modal = document.getElementById('adjustModal');
        const productIdInput = document.getElementById('adjustProductId');
        const currentStockInput = document.getElementById('adjustCurrentStock');
        
        if (modal && productIdInput && currentStockInput) {
            modal.classList.remove('hidden');
            productIdInput.value = productId;
            currentStockInput.value = currentStock;
            console.log('Modal values set:', { 
                productId: productIdInput.value, 
                currentStock: currentStockInput.value 
            });
        } else {
            console.error('Modal elements not found:', { 
                modal: !!modal, 
                productIdInput: !!productIdInput, 
                currentStockInput: !!currentStockInput 
            });
        }
    }

    function closeAdjustModal() {
        const modal = document.getElementById('adjustModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const restockModal = document.getElementById('restockModal');
        const adjustModal = document.getElementById('adjustModal');
        
        if (event.target === restockModal) {
            closeRestockModal();
        }
        if (event.target === adjustModal) {
            closeAdjustModal();
        }
    }

    // Form submit handler
    document.addEventListener('DOMContentLoaded', function() {
        const restockForm = document.querySelector('#restockModal form');
        if (restockForm) {
            restockForm.addEventListener('submit', function(e) {
                const formData = new FormData(this);
                const data = Object.fromEntries(formData);
                
                // Validate form data
                if (!data.product_id || !data.quantity || !data.notes) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                    return;
                }

                // Validate quantity
                if (parseInt(data.quantity) <= 0) {
                    e.preventDefault();
                    alert('Quantity must be greater than 0');
                    return;
                }
            });
        }
    });
    </script>
</body>
</html> 