<?php
require_once __DIR__ . '/../auth/check_session.php';
$user = checkAuth('cashier');

require_once __DIR__ . '/../../config/database.php';

// Fetch products
try {
    $stmt = $pdo->query("
        SELECT p.*, COALESCE(c.name, 'Uncategorized') as category 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching products: " . $e->getMessage());
}

// Fetch categories
try {
    $catStmt = $pdo->query("
        SELECT name as category 
        FROM categories 
        ORDER BY name
    ");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    // Add 'Uncategorized' if there are products without categories
    $categories[] = 'Uncategorized';
} catch(PDOException $e) {
    $categories = ['Uncategorized'];
}

// Generate transaction ID
$transactionId = date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'active' AND (id = 1 OR role = 'admin')");
$stmt->execute();
$totalUsers = $stmt->fetchColumn();

// Pass PHP variables to JavaScript
$jsInitialData = [
    'cashierName' => $user['name'],
    'transactionId' => $transactionId
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retail POS System</title>
    <script>
        // Initialize data from PHP
        const initialData = <?php echo json_encode($jsInitialData); ?>;
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f4f8; }
        .pos-header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
        .product-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);}
        .num-btn:active { transform: scale(0.95); background-color: #e2e8f0;}
        .action-btn:active { transform: scale(0.98);}
        .receipt-paper { background: repeating-linear-gradient(#fff,#fff 20px,#f0f4f8 21px,#f0f4f8 22px); width: 80mm;}
        .blink { animation: blink 1s step-end infinite;}
        @keyframes blink { from, to { opacity: 1 } 50% { opacity: 0.5 } }
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            
            body * {
                visibility: hidden;
            }
            
            .receipt-print, .receipt-print * {
                visibility: visible;
            }
            
            .receipt-print {
                position: absolute;
                left: 0;
                top: 0;
                width: 80mm;
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="h-screen overflow-hidden">
<div class="flex h-full">
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
    <div class="flex-1 flex h-full ml-0 transition-all duration-200 p-4 gap-4 overflow-y-auto" id="main-panels">
        <!-- Left Panel - Products -->
        <div class="bg-white rounded-2xl shadow-lg p-6 h-full flex flex-col transition-all duration-200" id="left-panel" style="width: 58%; min-width: 300px;">
        <div class="sticky top-0 z-10 sticky shadow bg-white p-4 ">
        <div class="pos-header text-white p-4 rounded-lg mb-4 flex justify-between items-center ">
                <div>
                    <h1 class="text-2xl font-bold">RETAIL POS</h1>
                    <p class="text-sm opacity-90">Store #042 • Terminal 1</p>
                </div>
                <div class="text-right">
                    <div class="text-sm">Shift: Morning (08:00-16:00)</div>
                    <div class="text-lg font-mono" id="current-time"></div>
                </div>
            </div>
            <!-- Search Bar + Category Dropdown Row -->
            <div class="mb-4 bg-white shadow rounded-lg flex items-center px-4 py-2">
                <div class="relative flex-1">
                    <input type="text" 
                           id="search-input" 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500" 
                           placeholder="Search by product name or SKU..."
                           onkeyup="searchProducts(this.value)">
                    <div class="absolute left-3 top-2.5 text-gray-400">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                <select id="category-dropdown"
                        class="ml-4 min-w-[160px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 bg-white text-gray-700">
                    <option value="all">All Items</option>
                <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
                </select>
            </div>

         </div>    <!-- Header -->
            
            <!-- Product Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-4 flex-1 overflow-y-auto" id="product-grid">
                <?php foreach ($products as $product): ?>
                <div class="product-card bg-white border border-gray-200 rounded-lg p-3 transition-all duration-200 hover:shadow-lg hover:-translate-y-1 <?php if ($product['stock'] <= 0) echo 'opacity-50 cursor-not-allowed pointer-events-none'; else echo 'cursor-pointer'; ?>"
                     <?php if ($product['stock'] > 0): ?>
                     onclick="addToCart('<?= addslashes($product['name']) ?>', <?= $product['price'] ?>, '<?= addslashes($product['sku']) ?>', '<?= addslashes($product['image']) ?>')"
                     <?php endif; ?>
                     data-category="<?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?>">
                  <div class="bg-gray-100 rounded-xl h-36 flex items-center justify-center overflow-hidden">
                        <img src="<?= !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://via.placeholder.com/150' ?>" 
                            alt="<?= htmlspecialchars($product['name']) ?>" 
                            class="w-full h-full object-cover rounded-lg" 
                            onerror="this.src='https://via.placeholder.com/150?text=No+Image'">
                    </div>
                    <h3 class="font-medium text-sm truncate mt-2 mb-1"><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="text-xs text-gray-500 truncate mb-1">
                        <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?> • SKU: <?= htmlspecialchars($product['sku']) ?>
                    </p>
                    <p class="text-xs font-semibold mb-1">
                        Stock: <span class="<?= $product['stock'] <= 0 ? 'text-red-500' : 'text-gray-700' ?>"><?= (int)$product['stock'] ?></span>
                    </p>
                    <div class="flex justify-between items-center mt-2">
                        <span class="font-bold text-indigo-600">₱<?= number_format($product['price'], 2) ?></span>
                        <button class="bg-indigo-100 text-indigo-600 p-1 rounded-full w-6 h-6 flex items-center justify-center" <?php if ($product['stock'] <= 0) echo 'disabled'; ?>>
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Draggable Divider -->
        <div id="drag-divider" class="w-2 cursor-col-resize bg-gray-200 hover:bg-gray-400 transition-all duration-200 mx-1 rounded" style="height: 100%; min-width: 8px;"></div>
        <!-- Right Panel - Cart & Payment -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 flex flex-col p-6 transition-all duration-200" id="right-panel" style="width: 40%; min-width: 260px;">
            <!-- Current Transaction -->
            <div class="p-4 border-b border-gray-200">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-xl font-bold">Current Sale</h2>
                    <div class="text-sm text-gray-500">#TR-<span id="transaction-id"></span></div>
                </div>
                <div class="flex space-x-1">
                    <button class="px-2 py-1 bg-gray-200 hover:bg-gray-300 rounded text-sm">
                        <i class="fas fa-user mr-1"></i> Customer
                    </button>
                    <button class="px-2 py-1 bg-gray-200 hover:bg-gray-300 rounded text-sm" onclick="applyDiscount()">
                        <i class="fas fa-tag mr-1"></i> Discount
                    </button>
                    <button class="px-2 py-1 bg-gray-200 hover:bg-gray-300 rounded text-sm" onclick="voidTransaction()">
                        <i class="fas fa-undo mr-1"></i> Void
                    </button>
                </div>
            </div>
            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="cart-items">
                <!-- Cart items will be dynamically added here -->
            </div>
            <!-- Totals -->
            <div class="p-4 border-t border-gray-200">
                <div class="space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span>₱<span id="subtotal">0.00</span></span>
                    </div>
                    <div class="flex justify-between" id="discount-row" style="display:none;">
                        <span id="discount-label">Discount (0%)</span>
                        <span>-₱<span id="discount-amount">0.00</span></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tax (<span id="tax-rate">8</span>%):</span>
                        <span>₱<span id="tax-amount">0.00</span></span>
                    </div>
                    <div class="flex justify-between font-bold text-lg">
                        <span>Total:</span>
                        <span>₱<span id="total">0.00</span></span>
                    </div>
                </div>
                <!-- Payment Methods -->
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <button class="action-btn bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium" onclick="processCardPayment()">
                        <i class="fas fa-credit-card mr-1"></i> Card
                    </button>
                    <button class="action-btn bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium" onclick="processCashPayment()">
                        <i class="fas fa-money-bill-wave mr-1"></i> Cash
                    </button>
                    <button class="action-btn bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg font-medium" onclick="processMobilePayment()">
                        <i class="fas fa-mobile-alt mr-1"></i> Mobile
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Hidden receipt for printing -->
<div id="receipt-template" class="receipt-print">
    <div class="receipt-paper bg-white p-6 font-sans text-sm w-[80mm] mx-auto">
        <!-- Logo and Header -->
        <div class="text-center mb-6">
            <h2 class="text-2xl font-light tracking-wide mb-1">RETAIL 042</h2>
            <div class="text-xs text-gray-600 space-y-0.5">
                <p>123 Main Street, City</p>
                <p>Tel: (555) 123-4567</p>
            </div>
        </div>
        
        <!-- Transaction Info -->
        <div class="space-y-1.5 mb-6 text-xs">
            <div class="flex justify-between">
                <span class="text-gray-600">Cashier</span>
                <span id="receipt-cashier" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Date</span>
                <span id="receipt-date" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Time</span>
                <span id="receipt-time" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Transaction</span>
                <span id="receipt-transaction" class="font-medium"></span>
            </div>
        </div>
        
        <!-- Items -->
        <div class="my-6" id="receipt-items">
            <!-- Items will be dynamically added here -->
        </div>
        
        <!-- Totals -->
        <div class="space-y-1.5 text-xs">
            <div class="flex justify-between">
                <span class="text-gray-600">Subtotal</span>
                <span>₱<span id="receipt-subtotal" class="font-medium">0.00</span></span>
            </div>
            <div id="receipt-discount-row" class="flex justify-between" style="display:none;">
                <span id="receipt-discount-label" class="text-gray-600">Discount</span>
                <span>-₱<span id="receipt-discount-amount" class="font-medium">0.00</span></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Tax (<span id="receipt-tax-rate">8</span>%)</span>
                <span>₱<span id="receipt-tax-amount" class="font-medium">0.00</span></span>
            </div>
            <div class="flex justify-between text-sm font-semibold mt-3 pt-3 border-t border-gray-200">
                <span>Total</span>
                <span>₱<span id="receipt-total">0.00</span></span>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="mt-6 pt-3 border-t border-gray-200 space-y-1.5 text-xs">
            <div class="flex justify-between">
                <span class="text-gray-600">Payment Method</span>
                <span id="receipt-payment-method" class="font-medium"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Amount Tendered</span>
                <span>₱<span id="receipt-tendered" class="font-medium">0.00</span></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Change</span>
                <span>₱<span id="receipt-change" class="font-medium">0.00</span></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-xs mb-1">Thank you for your purchase</p>
            <div class="text-[10px] text-gray-500 mt-4">
                <p>Returns accepted within 30 days</p>
                <p>with original receipt</p>
            </div>
        </div>
    </div>
</div>

<!-- Card Payment Modal -->
<div id="card-payment-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Card Payment</h3>
            <button onclick="closeCardPaymentModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Card Number</label>
                <input type="text" id="card-number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="1234 5678 9012 3456" maxlength="19" onkeyup="formatCardNumber(this)">
                <span id="card-number-error" class="text-red-500 text-sm hidden"></span>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
                    <input type="text" id="card-expiry" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="MM/YY" maxlength="5" onkeyup="formatExpiry(this)">
                    <span id="card-expiry-error" class="text-red-500 text-sm hidden"></span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">CVV</label>
                    <input type="text" id="card-cvv" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="123" maxlength="3" onkeyup="validateCVV(this)">
                    <span id="card-cvv-error" class="text-red-500 text-sm hidden"></span>
                </div>
            </div>
        </div>
        <div class="mt-6">
            <button onclick="submitCardPayment()" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Process Payment</button>
        </div>
    </div>
</div>
<!-- Cash Payment Modal -->
<div id="cash-payment-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Cash Payment</h3>
            <button onclick="closeCashPaymentModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Amount Tendered</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">₱</span>
                    </div>
                    <input type="text" 
                           id="cash-amount" 
                           class="block w-full h-12 pl-7 pr-12 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="0.00" 
                           onkeyup="validateCashAmount(this)">
                </div>
                <span id="cash-amount-error" class="text-red-500 text-sm hidden"></span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg">
                <div class="text-sm text-gray-600">Total Amount: ₱<span id="modal-total">0.00</span></div>
                <div class="text-sm text-gray-600">Change: ₱<span id="modal-change">0.00</span></div>
            </div>
        </div>
        <div class="mt-6">
            <button onclick="submitCashPayment()" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Process Payment</button>
        </div>
    </div>
</div>
<!-- Mobile Payment Modal -->
<div id="mobile-payment-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Mobile Payment</h3>
            <button onclick="closeMobilePaymentModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Wallet Type</label>
                <select id="wallet-type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select wallet type</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="grabpay">GrabPay</option>
                </select>
                <span id="wallet-type-error" class="text-red-500 text-sm hidden"></span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mobile Number</label>
                <input type="text" id="mobile-number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="09XX XXX XXXX" maxlength="11" onkeyup="formatMobileNumber(this)">
                <span id="mobile-number-error" class="text-red-500 text-sm hidden"></span>
            </div>
        </div>
        <div class="mt-6">
            <button onclick="submitMobilePayment()" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Process Payment</button>
        </div>
    </div>
</div>
<!-- Discount Modal -->
<div id="discount-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Apply Discount</h3>
            <button onclick="closeDiscountModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1 ml-2">Discount Percentage</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <input type="number" 
                           id="discount-percentage" 
                           class="block w-full h-12 pr-12 pl-4 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="0"
                           min="0"
                           max="100"
                           onkeyup="validateDiscountPercentage(this)">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                        <span class="text-gray-500 sm:text-sm">%</span>
                    </div>
                </div>
                <span id="discount-percentage-error" class="text-red-500 text-sm hidden"></span>
            </div>
            <div class="bg-gray-50 p-3 rounded-lg space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Original Total:</span>
                    <span>₱<span id="discount-original-total">0.00</span></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Discount Amount:</span>
                    <span class="text-red-600">-₱<span id="discount-amount-preview">0.00</span></span>
                </div>
                <div class="flex justify-between text-sm font-semibold">
                    <span>Final Total:</span>
                    <span>₱<span id="discount-final-total">0.00</span></span>
                </div>
            </div>
        </div>
        <div class="mt-6">
            <button onclick="submitDiscount()" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Apply Discount</button>
        </div>
    </div>
</div>
<!-- Toast Notification -->
<div id="toast-notification" class="fixed top-4 right-4 z-50 transform transition-all duration-300 translate-x-full">
    <div class="flex items-center p-4 min-w-[320px] rounded-lg shadow-lg">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg">
            <i class="fas fa-check text-xl"></i>
        </div>
        <div class="ml-3 text-sm font-normal" id="toast-message"></div>
        <button type="button" class="ml-auto -mx-1.5 -my-1.5 rounded-lg p-1.5 inline-flex items-center justify-center h-8 w-8 hover:bg-gray-100" onclick="hideToast()">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
    // Global variables
    let cart = [];
    let currentTransactionId = initialData.transactionId;
    let currentAmount = '';
    let taxRate = 8; // 8% tax rate
    let currentPaymentMethod = '';
    let currentDiscount = 0;

    // Function to generate new transaction ID
    function generateNewTransactionId() {
        const date = new Date();
        const dateStr = date.getFullYear() + 
            String(date.getMonth() + 1).padStart(2, '0') + 
            String(date.getDate()).padStart(2, '0');
        const randomNum = Math.floor(Math.random() * 999).toString().padStart(3, '0');
        return `${dateStr}-${randomNum}`;
    }

    function processPayment(method, amountTendered = 0, paymentInfo = {}) {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }

        const total = parseFloat(document.getElementById('total').textContent);
        
        // Validate cash payment
        if (method === 'cash') {
            if (!amountTendered || isNaN(amountTendered)) {
                const cashInput = document.getElementById('cash-amount');
                amountTendered = parseFloat(cashInput.value);
            }
            if (isNaN(amountTendered) || amountTendered < total) {
                showToast('Invalid amount or insufficient payment!', 'error');
                return;
            }
        }

        // Store cart data before clearing
        const cartData = cart.map(item => ({
            sku: item.sku,
            quantity: item.quantity,
            price: item.price,
            name: item.name
        }));
        
        // Update receipt with transaction details
        updateReceipt(method, amountTendered, paymentInfo);
        
        // Record transaction
        fetch('/NEW/app/api/record_transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart: cartData,
                paymentMethod: method,
                cashier: initialData.cashierName,
                transactionId: currentTransactionId,
                total: total,
                subtotal: parseFloat(document.getElementById('subtotal').textContent),
                tax: parseFloat(document.getElementById('tax-amount').textContent),
                discount: {
                    percentage: currentDiscount,
                    amount: parseFloat(document.getElementById('discount-amount').textContent || '0')
                },
                amountTendered: amountTendered
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || 'Failed to record transaction');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Print receipt using browser print dialog
                window.print();
                
                // Clear cart and reset
                cart = [];
                updateCartDisplay();
                updateTotals();
                
                // Generate new transaction ID
                currentTransactionId = generateNewTransactionId();
                document.getElementById('transaction-id').textContent = currentTransactionId;
                
                // Reset other values
                currentDiscount = 0;
                currentAmount = '';
                
                // Update product stock
                updateProductGridStockAfterSale(cartData);
                
                // Reset payment modal
                if (method === 'cash') {
                    const modal = document.getElementById('cash-payment-modal');
                    if (modal) modal.classList.add('hidden');
                    const input = document.getElementById('cash-amount');
                    if (input) input.value = '';
                }

                showToast('Transaction completed successfully!', 'success');
            } else {
                throw new Error(data.error || 'Failed to record transaction');
            }
        })
        .catch(error => {
            console.error('Transaction error:', error);
            showToast('Error: ' + error.message + '\nPlease try again or contact admin.', 'error');
        });
    }

    // Initialize transaction ID and cashier name
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('receipt-cashier').textContent = initialData.cashierName;
        document.getElementById('transaction-id').textContent = currentTransactionId;
        updateCurrentTime();
        setInterval(updateCurrentTime, 60000);
        updateCartDisplay();
        updateTotals();
    });

    // Search functionality
    function searchProducts(query) {
        const searchTerm = query.toLowerCase().trim();
        const productCards = document.querySelectorAll('.product-card');
        
        productCards.forEach(card => {
            const productName = card.querySelector('h3').textContent.toLowerCase();
            const productSku = card.querySelector('p').textContent.split('SKU:')[1].trim().toLowerCase();
            
            // Search by exact SKU or partial product name
            if (productSku === searchTerm || productName.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });

        // If search is empty, show all products
        if (!searchTerm) {
            productCards.forEach(card => {
                card.style.display = 'block';
            });
        }
    }

    // Category filter
    document.getElementById('category-dropdown').addEventListener('change', function() {
        const category = this.value;
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
        });
    });

    // Cart management
    function addToCart(name, price, sku, image) {
        // Find the product's stock from the product grid
        const productCards = document.querySelectorAll('.product-card');
        let stock = 0;
        productCards.forEach(card => {
            const skuText = card.querySelector('p.text-xs.text-gray-500').textContent;
            if (skuText.includes(sku)) {
                const stockSpan = card.querySelector('span.text-gray-700, span.text-red-500');
                stock = parseInt(stockSpan.textContent, 10);
            }
        });

        const existingItem = cart.find(item => item.sku === sku);
        
        if (existingItem) {
            if (existingItem.quantity < stock) {
            existingItem.quantity++;
        } else {
                alert('Cannot add more than available stock!');
            }
        } else {
            if (stock > 0) {
            cart.push({
                name: name,
                price: price,
                sku: sku,
                    quantity: 1,
                    image: image
            });
            } else {
                alert('Out of stock!');
            }
        }
        
        updateCartDisplay();
        updateTotals();
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        updateCartDisplay();
        updateTotals();
    }

    function updateQuantity(index, change) {
        // Find the product's stock from the product grid
        const sku = cart[index].sku;
        const productCards = document.querySelectorAll('.product-card');
        let stock = 0;
        productCards.forEach(card => {
            const skuText = card.querySelector('p.text-xs.text-gray-500').textContent;
            if (skuText.includes(sku)) {
                const stockSpan = card.querySelector('span.text-gray-700, span.text-red-500');
                stock = parseInt(stockSpan.textContent, 10);
            }
        });

        if (change > 0 && cart[index].quantity >= stock) {
            alert('Cannot add more than available stock!');
            return;
        }

        cart[index].quantity += change;
        if (cart[index].quantity < 1) {
            removeFromCart(index);
        } else {
            updateCartDisplay();
            updateTotals();
        }
    }

    function updateCartDisplay() {
        const cartContainer = document.getElementById('cart-items');
        cartContainer.innerHTML = `
          <div class="overflow-x-auto mt-4 w-full max-w-full">
            <table class="min-w-[600px] w-full text-sm bg-white rounded-xl shadow border border-gray-200">
              <thead>
                <tr class="bg-gray-100">
                  <th class="p-3 text-center w-20">Image</th>
                  <th class="p-3 text-left">Name</th>
                  <th class="p-3 text-right">Price</th>
                  <th class="p-3 text-center">Quantity</th>
                  <th class="p-3 text-right">Total</th>
                  <th class="p-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody id="cart-table-body"></tbody>
            </table>
          </div>
        `;
        const tbody = document.getElementById('cart-table-body');
        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            // Find the product's stock from the product grid
            const productCards = document.querySelectorAll('.product-card');
            let stock = 0;
            productCards.forEach(card => {
                const skuText = card.querySelector('p.text-xs.text-gray-500').textContent;
                if (skuText.includes(item.sku)) {
                    const stockSpan = card.querySelector('span.text-gray-700, span.text-red-500');
                    stock = parseInt(stockSpan.textContent, 10);
                }
            });
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td class="p-3 text-center align-middle w-20">
                <img src="${item.image ? item.image : 'https://via.placeholder.com/40?text=No+Image'}" alt="${item.name}" class="w-10 h-10 object-cover rounded border mx-auto" onerror="this.src='https://via.placeholder.com/40?text=No+Image'" />
              </td>
              <td class="p-3 align-middle text-left">${item.name}</td>
              <td class="p-3 font-bold text-gray-900 text-right align-middle">₱${item.price.toLocaleString()}</td>
              <td class="p-3 align-middle text-center">
                <div class="flex flex-col items-center">
                  <div class="flex items-center border rounded px-2 py-1 bg-gray-50">
                    <button type="button" class="px-2 text-base text-gray-600 hover:bg-gray-200" onclick="updateQuantity(${index}, -1)">-</button>
                    <input type="number" min="1" max="${stock}" value="${item.quantity === '' ? '' : item.quantity}" data-index="${index}" class="w-10 text-center border-0 focus:ring-0 text-base bg-gray-50" style="height:1.5rem;" />
                    <button type="button" class="px-2 text-base text-gray-600 hover:bg-gray-200" onclick="updateQuantity(${index}, 1)">+</button>
                </div>
                  <div class="text-xs text-red-500 min-h-[18px]" id="qty-error-${index}"></div>
                    </div>
              </td>
              <td class="p-3 font-bold text-orange-600 text-right align-middle" id="row-total-${index}">₱${itemTotal.toLocaleString()}</td>
              <td class="p-3 text-center align-middle">
                <button onclick="removeFromCart(${index})" class="text-red-600 hover:underline text-sm">Delete</button>
              </td>
            `;
            tbody.appendChild(tr);
        });
        // Add event listeners for quantity inputs
        document.querySelectorAll('#cart-items input[type="number"]').forEach(input => {
            input.addEventListener('input', function() {
                const idx = parseInt(this.getAttribute('data-index'), 10);
                let val = this.value === '' ? '' : parseInt(this.value, 10);
                // Find the product's stock from the product grid
                const sku = cart[idx].sku;
                const productCards = document.querySelectorAll('.product-card');
                let stock = 0;
                productCards.forEach(card => {
                    const skuText = card.querySelector('p.text-xs.text-gray-500').textContent;
                    if (skuText.includes(sku)) {
                        const stockSpan = card.querySelector('span.text-gray-700, span.text-red-500');
                        stock = parseInt(stockSpan.textContent, 10);
                    }
                });
                const errorDiv = document.getElementById('qty-error-' + idx);
                if (val === '') {
                    cart[idx].quantity = 0;
                    errorDiv.textContent = '';
                    const totalCell = document.getElementById('row-total-' + idx);
                    if (totalCell) {
                        totalCell.textContent = '₱0';
                    }
                    updateTotals();
                    return;
                } else if (isNaN(val) || val < 1) {
                    removeFromCart(idx);
                    return;
                } else if (val > stock) {
                    cart[idx].quantity = stock;
                    this.value = stock;
                    errorDiv.textContent = `Cannot exceed available stock (${stock})`;
                } else {
                    cart[idx].quantity = val;
                    errorDiv.textContent = '';
                }
                // Update only the total cell for this row
                const totalCell = document.getElementById('row-total-' + idx);
                if (totalCell) {
                    totalCell.textContent = '₱' + (cart[idx].price * cart[idx].quantity).toLocaleString();
                }
                updateTotals();
            });
        });
    }

    function updateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const discountAmount = subtotal * (currentDiscount / 100);
        const discountedSubtotal = subtotal - discountAmount;
        const taxAmount = discountedSubtotal * (taxRate / 100);
        const total = discountedSubtotal + taxAmount;
        
        // Update POS display
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('discount-row').style.display = currentDiscount > 0 ? '' : 'none';
        document.getElementById('discount-amount').textContent = discountAmount.toFixed(2);
        document.getElementById('discount-label').textContent = `Discount (${currentDiscount}%)`;
        document.getElementById('tax-amount').textContent = taxAmount.toFixed(2);
        document.getElementById('total').textContent = total.toFixed(2);

        // Update receipt display
        document.getElementById('receipt-subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('receipt-discount-row').style.display = currentDiscount > 0 ? '' : 'none';
        document.getElementById('receipt-discount-amount').textContent = discountAmount.toFixed(2);
        document.getElementById('receipt-discount-label').textContent = `Discount (${currentDiscount}%)`;
        document.getElementById('receipt-tax-amount').textContent = taxAmount.toFixed(2);
        document.getElementById('receipt-total').textContent = total.toFixed(2);
    }

    // Payment Processing Functions
    function processCardPayment() {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }
        currentPaymentMethod = 'card';
        document.getElementById('card-payment-modal').classList.remove('hidden');
        document.getElementById('card-payment-modal').classList.add('flex');
    }

    function processCashPayment() {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }
        currentPaymentMethod = 'cash';
        document.getElementById('cash-payment-modal').classList.remove('hidden');
        document.getElementById('cash-payment-modal').classList.add('flex');
        const total = parseFloat(document.getElementById('total').textContent);
        document.getElementById('modal-total').textContent = total.toFixed(2);
        document.getElementById('cash-amount').value = '';
        document.getElementById('modal-change').textContent = '0.00';
    }

    function processMobilePayment() {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }
        currentPaymentMethod = 'mobile';
        document.getElementById('mobile-payment-modal').classList.remove('hidden');
        document.getElementById('mobile-payment-modal').classList.add('flex');
    }

    // Card Payment Validation
    function validateCardPayment() {
        const cardNumber = document.getElementById('card-number');
        const cardExpiry = document.getElementById('card-expiry');
        const cardCvv = document.getElementById('card-cvv');

        const isCardNumberValid = validateCardNumber(cardNumber);
        const isExpiryValid = validateExpiry(cardExpiry);
        const isCvvValid = validateCVV(cardCvv);

        if (!isCardNumberValid || !isExpiryValid || !isCvvValid) {
            return false;
        }

        return {
            type: 'card',
            cardNumber: cardNumber.value,
            expiry: cardExpiry.value,
            cvv: cardCvv.value
        };
    }

    // Cash Payment Validation
    function validateCashPayment() {
        const cashAmount = document.getElementById('cash-amount');
        const isValid = validateCashAmount(cashAmount);

        if (!isValid) {
            return false;
        }

        return {
            type: 'cash',
            amount: parseFloat(cashAmount.value)
        };
    }

    // Mobile Payment Validation
    function validateMobilePayment() {
        const walletType = document.getElementById('wallet-type');
        const mobileNumber = document.getElementById('mobile-number');

        const isWalletTypeValid = validateWalletType();
        const isMobileNumberValid = validateMobileNumber(mobileNumber);

        if (!isWalletTypeValid || !isMobileNumberValid) {
            return false;
        }

        return {
            type: 'mobile',
            walletType: walletType.value,
            mobileNumber: mobileNumber.value
        };
    }

    // Submit Payment Handler
    function submitPayment() {
        let paymentInfo;

        switch (currentPaymentMethod) {
            case 'card':
                paymentInfo = validateCardPayment();
                break;
            case 'cash':
                paymentInfo = validateCashPayment();
                break;
            case 'mobile':
                paymentInfo = validateMobilePayment();
                break;
            default:
                alert('Invalid payment method');
                return;
        }

        if (paymentInfo) {
            processPayment(
                currentPaymentMethod, 
                paymentInfo.amount || 0, 
                paymentInfo
            );
            closePaymentModal();
        }
    }

    function closePaymentModal() {
        document.getElementById('payment-modal').classList.add('hidden');
        document.getElementById('payment-modal').classList.remove('flex');
        // Clear form fields
        document.getElementById('card-number').value = '';
        document.getElementById('card-expiry').value = '';
        document.getElementById('card-cvv').value = '';
        document.getElementById('cash-amount').value = '';
        document.getElementById('mobile-number').value = '';
    }

    function switchPaymentTab(method) {
        // Update tab styles
        document.querySelectorAll('[id$="-tab"]').forEach(tab => {
            tab.classList.remove('bg-indigo-600', 'text-white');
            tab.classList.add('bg-gray-200', 'text-gray-700');
        });
        document.getElementById(`${method}-tab`).classList.remove('bg-gray-200', 'text-gray-700');
        document.getElementById(`${method}-tab`).classList.add('bg-indigo-600', 'text-white');

        // Show/hide forms
        document.getElementById('card-payment-form').classList.add('hidden');
        document.getElementById('cash-payment-form').classList.add('hidden');
        document.getElementById('mobile-payment-form').classList.add('hidden');
        document.getElementById(`${method}-payment-form`).classList.remove('hidden');

        // Update current payment method
        currentPaymentMethod = method;

        // Update modal title
        document.getElementById('modal-title').textContent = `${method.charAt(0).toUpperCase() + method.slice(1)} Payment`;

        // If cash payment, update total and change
        if (method === 'cash') {
            const total = parseFloat(document.getElementById('total').textContent);
            document.getElementById('modal-total').textContent = total.toFixed(2);
            document.getElementById('cash-amount').value = '';
            document.getElementById('modal-change').textContent = '0.00';
        }
    }

    function formatCardNumber(input) {
        let value = input.value.replace(/\D/g, '');
        value = value.replace(/(\d{4})/g, '$1 ').trim();
        input.value = value;
        validateCardNumber(input);
    }

    function validateCardNumber(input) {
        const error = document.getElementById('card-number-error');
        const value = input.value.replace(/\s/g, '');
        
        if (value.length < 16) {
            error.textContent = 'Card number must be 16 digits';
            error.classList.remove('hidden');
            return false;
        }
        
        error.classList.add('hidden');
        return true;
    }

    function formatExpiry(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.slice(0,2) + '/' + value.slice(2);
        }
        input.value = value;
        validateExpiry(input);
    }

    function validateExpiry(input) {
        const error = document.getElementById('card-expiry-error');
        const [month, year] = input.value.split('/');
        
        if (!month || !year || month < 1 || month > 12) {
            error.textContent = 'Invalid expiry date';
            error.classList.remove('hidden');
            return false;
        }
        
        error.classList.add('hidden');
        return true;
    }

    function validateCVV(input) {
        const error = document.getElementById('card-cvv-error');
        const value = input.value;
        
        if (value.length < 3) {
            error.textContent = 'CVV must be 3 digits';
            error.classList.remove('hidden');
            return false;
        }
        
        error.classList.add('hidden');
        return true;
    }

    function validateCashAmount(input) {
        const error = document.getElementById('cash-amount-error');
        const total = parseFloat(document.getElementById('total').textContent);
        const amount = parseFloat(input.value) || 0;
        
        if (amount < total) {
            error.textContent = 'Amount must be greater than total';
            error.classList.remove('hidden');
            document.getElementById('modal-change').textContent = '0.00';
            return false;
        }
        
        const change = amount - total;
        document.getElementById('modal-change').textContent = change.toFixed(2);
        error.classList.add('hidden');
        return true;
    }

    function formatMobileNumber(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length > 0 && !value.startsWith('09')) {
            value = '09' + value.slice(0, 9);
        }
        input.value = value;
        validateMobileNumber(input);
    }

    function validateMobileNumber(input) {
        const error = document.getElementById('mobile-number-error');
        const value = input.value;
        
        if (value.length < 11 || !value.startsWith('09')) {
            error.textContent = 'Invalid mobile number format';
            error.classList.remove('hidden');
            return false;
        }
        
        error.classList.add('hidden');
        return true;
    }

    function validateWalletType() {
        const error = document.getElementById('wallet-type-error');
        const value = document.getElementById('wallet-type').value;
        
        if (!value) {
            error.textContent = 'Please select a wallet type';
            error.classList.remove('hidden');
            return false;
        }
        
        error.classList.add('hidden');
        return true;
    }

    function updateReceipt(method, amountTendered, paymentInfo) {
        // Set date and time
        const now = new Date();
        document.getElementById('receipt-date').textContent = now.toLocaleDateString();
        document.getElementById('receipt-time').textContent = now.toLocaleTimeString();
        
        // Set cashier and transaction details
        document.getElementById('receipt-cashier').textContent = initialData.cashierName;
        document.getElementById('receipt-transaction').textContent = currentTransactionId;
        document.getElementById('receipt-payment-method').textContent = method.charAt(0).toUpperCase() + method.slice(1);
        
        // Format amounts
        document.getElementById('receipt-subtotal').textContent = document.getElementById('subtotal').textContent;
        document.getElementById('receipt-tax-amount').textContent = document.getElementById('tax-amount').textContent;
        document.getElementById('receipt-total').textContent = document.getElementById('total').textContent;
        document.getElementById('receipt-tendered').textContent = amountTendered.toFixed(2);
        
        // Calculate and display change
        const total = parseFloat(document.getElementById('total').textContent);
        const change = amountTendered - total;
        document.getElementById('receipt-change').textContent = change.toFixed(2);
        
        // Handle discount if any
        if (currentDiscount > 0) {
            document.getElementById('receipt-discount-row').style.display = 'flex';
            document.getElementById('receipt-discount-label').textContent = `Discount (${currentDiscount}%)`;
            document.getElementById('receipt-discount-amount').textContent = document.getElementById('discount-amount').textContent;
        } else {
            document.getElementById('receipt-discount-row').style.display = 'none';
        }
        
        // Clear and populate items
        const itemsContainer = document.getElementById('receipt-items');
        itemsContainer.innerHTML = '';
        
        // Add items with modern styling
        cart.forEach(item => {
            const itemRow = document.createElement('div');
            itemRow.className = 'mb-2 text-xs';
            itemRow.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="font-medium">${item.name}</div>
                        <div class="text-gray-600">
                            ${item.quantity} × ₱${item.price.toFixed(2)}
                        </div>
                    </div>
                    <div class="font-medium ml-4">
                        ₱${(item.quantity * item.price).toFixed(2)}
                    </div>
                </div>
            `;
            itemsContainer.appendChild(itemRow);
        });
    }

    // Numeric keypad functions
    function addToAmount(digit) {
        currentAmount += digit;
        // You can add validation here if needed
    }

    function clearAmount() {
        currentAmount = '';
    }

    function calculateChange() {
        if (!currentAmount) return;
        
        const total = parseFloat(document.getElementById('total').textContent);
        const tendered = parseFloat(currentAmount);
        
        if (tendered < total) {
            alert('Insufficient payment!');
            return;
        }
        
        const change = tendered - total;
        alert(`Change: ₱${change.toFixed(2)}`);
    }

    function completeSale() {
        if (cart.length === 0) {
            alert('Cart is empty!');
            return;
        }
        processPayment('cash');
    }

    function voidTransaction() {
        if (confirm('Are you sure you want to void this transaction?')) {
            cart = [];
            currentAmount = '';
            updateCartDisplay();
            updateTotals();
        }
    }

    function applyDiscount() {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }
        document.getElementById('discount-modal').classList.remove('hidden');
        document.getElementById('discount-modal').classList.add('flex');
        
        const subtotal = parseFloat(document.getElementById('subtotal').textContent);
        document.getElementById('discount-original-total').textContent = subtotal.toFixed(2);
        document.getElementById('discount-percentage').value = '';
        document.getElementById('discount-amount-preview').textContent = '0.00';
        document.getElementById('discount-final-total').textContent = subtotal.toFixed(2);
    }

    function validateDiscountPercentage(input) {
        const error = document.getElementById('discount-percentage-error');
        const value = parseFloat(input.value);
        
        if (isNaN(value) || value < 0 || value > 100) {
            error.textContent = 'Please enter a valid percentage between 0 and 100';
            error.classList.remove('hidden');
            return false;
        }
        
        error.classList.add('hidden');
        
        // Update preview calculations
        const subtotal = parseFloat(document.getElementById('discount-original-total').textContent);
        const discountAmount = subtotal * (value / 100);
        const finalTotal = subtotal - discountAmount;
        
        document.getElementById('discount-amount-preview').textContent = discountAmount.toFixed(2);
        document.getElementById('discount-final-total').textContent = finalTotal.toFixed(2);
        
        return true;
    }

    function submitDiscount() {
        const input = document.getElementById('discount-percentage');
        if (!validateDiscountPercentage(input)) {
            return;
        }
        
        currentDiscount = parseFloat(input.value);
        updateTotals();
        closeDiscountModal();
    }

    function closeDiscountModal() {
        document.getElementById('discount-modal').classList.add('hidden');
        document.getElementById('discount-modal').classList.remove('flex');
        document.getElementById('discount-percentage').value = '';
        document.getElementById('discount-percentage-error').classList.add('hidden');
    }

    // Set current time in header
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        document.getElementById('current-time').textContent = timeString;
    }

    function updateProductGridStockAfterSale(cartData) {
        cartData.forEach(item => {
            // Find the product card by SKU (or another unique identifier)
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                const skuText = card.querySelector('p.text-xs.text-gray-500').textContent;
                if (skuText.includes(item.sku)) {
                    // Find the stock span
                    const stockSpan = card.querySelector('span.text-gray-700, span.text-red-500');
                    let currentStock = parseInt(stockSpan.textContent, 10);
                    let newStock = currentStock - item.quantity;
                    stockSpan.textContent = newStock;
                    if (newStock <= 0) {
                        stockSpan.classList.remove('text-gray-700');
                        stockSpan.classList.add('text-red-500');
                        card.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                        card.removeAttribute('onclick');
                        // Optionally disable the plus button
                        const plusBtn = card.querySelector('button');
                        if (plusBtn) plusBtn.setAttribute('disabled', 'disabled');
                    }
                }
            });
        });
    }

    // Payment Modal Open/Close Functions
    function processCardPayment() {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }
        currentPaymentMethod = 'card';
        document.getElementById('card-payment-modal').classList.remove('hidden');
        document.getElementById('card-payment-modal').classList.add('flex');
    }
    
    function processCashPayment() {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }
        currentPaymentMethod = 'cash';
        document.getElementById('cash-payment-modal').classList.remove('hidden');
        document.getElementById('cash-payment-modal').classList.add('flex');
        const total = parseFloat(document.getElementById('total').textContent);
        document.getElementById('modal-total').textContent = total.toFixed(2);
        document.getElementById('cash-amount').value = '';
        document.getElementById('modal-change').textContent = '0.00';
    }
    
    function processMobilePayment() {
        if (cart.length === 0) {
            showToast('Cart is empty!', 'warning');
            return;
        }
        currentPaymentMethod = 'mobile';
        document.getElementById('mobile-payment-modal').classList.remove('hidden');
        document.getElementById('mobile-payment-modal').classList.add('flex');
    }
    
    function closeCardPaymentModal() {
        document.getElementById('card-payment-modal').classList.add('hidden');
        document.getElementById('card-payment-modal').classList.remove('flex');
        document.getElementById('card-number').value = '';
        document.getElementById('card-expiry').value = '';
        document.getElementById('card-cvv').value = '';
    }
    
    function closeCashPaymentModal() {
        document.getElementById('cash-payment-modal').classList.add('hidden');
        document.getElementById('cash-payment-modal').classList.remove('flex');
        document.getElementById('cash-amount').value = '';
    }
    
    function closeMobilePaymentModal() {
        document.getElementById('mobile-payment-modal').classList.add('hidden');
        document.getElementById('mobile-payment-modal').classList.remove('flex');
        document.getElementById('mobile-number').value = '';
    }
    
    // Submit Handlers
    function submitCardPayment() {
        const paymentInfo = validateCardPayment();
        if (paymentInfo) {
            processPayment('card', 0, paymentInfo);
            closeCardPaymentModal();
        }
    }
    
    function submitCashPayment() {
        const paymentInfo = validateCashPayment();
        if (paymentInfo) {
            processPayment('cash', paymentInfo.amount, paymentInfo);
            closeCashPaymentModal();
        }
    }
    
    function submitMobilePayment() {
        const paymentInfo = validateMobilePayment();
        if (paymentInfo) {
            processPayment('mobile', 0, paymentInfo);
            closeMobilePaymentModal();
        }
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast-notification');
        const toastMessage = document.getElementById('toast-message');
        const icon = toast.querySelector('.fa-check');
        
        // Set message
        toastMessage.textContent = message;
        
        // Reset classes
        toast.querySelector('.flex').className = 'flex items-center p-4 min-w-[320px] rounded-lg shadow-lg';
        
        // Apply style based on type
        switch(type) {
            case 'success':
                toast.querySelector('.flex').classList.add('bg-green-100', 'text-green-700');
                icon.className = 'fas fa-check text-xl text-green-700';
                break;
            case 'error':
                toast.querySelector('.flex').classList.add('bg-red-100', 'text-red-700');
                icon.className = 'fas fa-times text-xl text-red-700';
                break;
            case 'warning':
                toast.querySelector('.flex').classList.add('bg-yellow-100', 'text-yellow-700');
                icon.className = 'fas fa-exclamation-triangle text-xl text-yellow-700';
                break;
            case 'info':
                toast.querySelector('.flex').classList.add('bg-blue-100', 'text-blue-700');
                icon.className = 'fas fa-info-circle text-xl text-blue-700';
                break;
        }
        
        // Show toast
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
        
        // Auto hide after 3 seconds
        setTimeout(hideToast, 3000);
    }
    
    function hideToast() {
        const toast = document.getElementById('toast-notification');
        toast.classList.remove('translate-x-0');
        toast.classList.add('translate-x-full');
    }

    (function() {
        const leftPanel = document.getElementById('left-panel');
        const rightPanel = document.getElementById('right-panel');
        const divider = document.getElementById('drag-divider');
        const container = document.getElementById('main-panels');
        let dragging = false;
        let startX = 0;
        let startLeftWidth = 0;
        let startRightWidth = 0;
        divider.addEventListener('mousedown', function(e) {
            dragging = true;
            startX = e.clientX;
            startLeftWidth = leftPanel.offsetWidth;
            startRightWidth = rightPanel.offsetWidth;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });
        document.addEventListener('mousemove', function(e) {
            if (!dragging) return;
            const dx = e.clientX - startX;
            const containerWidth = container.offsetWidth;
            let newLeft = startLeftWidth + dx;
            let newRight = startRightWidth - dx;
            // Set min/max widths
            if (newLeft < 250) newLeft = 250;
            if (newRight < 220) newRight = 220;
            if (newLeft + newRight > containerWidth - 16) {
                newRight = containerWidth - newLeft - 16;
            }
            leftPanel.style.width = newLeft + 'px';
            rightPanel.style.width = newRight + 'px';
        });
        document.addEventListener('mouseup', function() {
            if (dragging) {
                dragging = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
        // Touch support
        divider.addEventListener('touchstart', function(e) {
            dragging = true;
            startX = e.touches[0].clientX;
            startLeftWidth = leftPanel.offsetWidth;
            startRightWidth = rightPanel.offsetWidth;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });
        document.addEventListener('touchmove', function(e) {
            if (!dragging) return;
            const dx = e.touches[0].clientX - startX;
            const containerWidth = container.offsetWidth;
            let newLeft = startLeftWidth + dx;
            let newRight = startRightWidth - dx;
            if (newLeft < 250) newLeft = 250;
            if (newRight < 220) newRight = 220;
            if (newLeft + newRight > containerWidth - 16) {
                newRight = containerWidth - newLeft - 16;
            }
            leftPanel.style.width = newLeft + 'px';
            rightPanel.style.width = newRight + 'px';
        });
        document.addEventListener('touchend', function() {
            if (dragging) {
                dragging = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    })();
</script>
</body>
</html>