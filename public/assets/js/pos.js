// POS State
const state = {
    cart: [],
    subtotal: 0,
    taxRate: CONFIG.TAX.RATE,
    taxAmount: 0,
    total: 0,
    paymentMethod: '',
    amountTendered: 0,
    change: 0,
    transactionId: generateTransactionId()
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load components
    loadComponents();
    
    // Initialize UI
    document.getElementById('productSearch').focus();
    document.getElementById('transaction-id').textContent = state.transactionId;
    updateDateTime();
    setInterval(updateDateTime, 60000); // Update time every minute
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
});

// Load components
async function loadComponents() {
    const components = [
        { id: 'pos-header', path: 'components/pos-header.html' },
        { id: 'search-bar', path: 'components/search-bar.html' },
        { id: 'category-tabs', path: 'components/category-tabs.html' },
        { id: 'product-grid', path: 'components/product-grid.html' },
        { id: 'current-transaction', path: 'components/current-transaction.html' },
        { id: 'totals-section', path: 'components/totals-section.html' },
        { id: 'receipt-template', path: 'components/receipt-template.html' }
    ];
    
    for (const component of components) {
        try {
            const response = await fetch(component.path);
            const html = await response.text();
            document.getElementById(component.id).innerHTML = html;
        } catch (error) {
            console.error(`Error loading component ${component.path}:`, error);
        }
    }
}

// Generate a random transaction ID
function generateTransactionId() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const randomNum = Math.floor(1000 + Math.random() * 9000);
    return `${CONFIG.TRANSACTION.PREFIX}-${year}${month}${day}-${randomNum}`;
}

// Update date and time display
function updateDateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const dateString = now.toLocaleDateString();
    
    // Update receipt date/time
    document.getElementById('receipt-date').textContent = dateString;
    document.getElementById('receipt-time').textContent = timeString;
}

// Add product to cart
function addToCart(name, price, sku) {
    // Check if item already exists in cart
    const existingItem = state.cart.find(item => item.sku === sku);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        state.cart.push({
            name,
            price,
            sku,
            quantity: 1
        });
    }
    
    updateCartDisplay();
    calculateTotals();
    document.getElementById('productSearch').focus();
}

// Update cart quantity
function updateCartItem(sku, newQuantity) {
    const item = state.cart.find(item => item.sku === sku);
    if (item) {
        if (newQuantity <= 0) {
            // Remove item if quantity is 0 or less
            state.cart = state.cart.filter(item => item.sku !== sku);
        } else {
            item.quantity = newQuantity;
        }
        updateCartDisplay();
        calculateTotals();
    }
}

// Update cart display
function updateCartDisplay() {
    const cartItemsContainer = document.getElementById('cart-items');
    cartItemsContainer.innerHTML = '';
    
    if (state.cart.length === 0) {
        cartItemsContainer.innerHTML = '<div class="text-center text-gray-500 py-8">Cart is empty</div>';
        return;
    }
    
    state.cart.forEach(item => {
        const itemElement = document.createElement('div');
        itemElement.className = 'bg-white rounded-lg p-3 border border-gray-200';
        itemElement.innerHTML = `
            <div class="flex justify-between">
                <div>
                    <h3 class="font-medium">${item.name}</h3>
                    <p class="text-xs text-gray-500">$${item.price.toFixed(2)} × ${item.quantity}</p>
                </div>
                <div class="text-right">
                    <div class="font-bold">$${(item.price * item.quantity).toFixed(2)}</div>
                    <div class="flex items-center justify-end space-x-2 mt-1">
                        <button onclick="updateCartItem('${item.sku}', ${item.quantity - 1})" class="text-gray-400 hover:text-gray-600 w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <span class="text-sm">${item.quantity}</span>
                        <button onclick="updateCartItem('${item.sku}', ${item.quantity + 1})" class="text-gray-400 hover:text-gray-600 w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        cartItemsContainer.appendChild(itemElement);
    });
}

// Calculate totals
function calculateTotals() {
    state.subtotal = state.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    state.taxAmount = state.subtotal * (state.taxRate / 100);
    state.total = state.subtotal + state.taxAmount;
    
    // Update display
    document.getElementById('subtotal').textContent = state.subtotal.toFixed(2);
    document.getElementById('tax-amount').textContent = state.taxAmount.toFixed(2);
    document.getElementById('total').textContent = state.total.toFixed(2);
    
    // Update receipt values
    document.getElementById('receipt-subtotal').textContent = state.subtotal.toFixed(2);
    document.getElementById('receipt-tax-rate').textContent = state.taxRate;
    document.getElementById('receipt-tax-amount').textContent = state.taxAmount.toFixed(2);
    document.getElementById('receipt-total').textContent = state.total.toFixed(2);
}

// Apply discount
function applyDiscount() {
    const discountPercentage = prompt('Enter discount percentage (e.g., 10 for 10%):');
    if (discountPercentage && !isNaN(discountPercentage)) {
        const discount = parseFloat(discountPercentage);
        if (discount > 0 && discount <= 100) {
            // In a real POS, you would apply discount to specific items or the whole transaction
            alert(`Discount of ${discount}% will be applied to the transaction`);
        } else {
            alert('Please enter a valid discount between 1-100%');
        }
    }
}

// Void transaction
function voidTransaction() {
    if (confirm('Are you sure you want to void this transaction?')) {
        state.cart = [];
        state.subtotal = 0;
        state.taxAmount = 0;
        state.total = 0;
        state.paymentMethod = '';
        state.amountTendered = 0;
        state.change = 0;
        state.transactionId = generateTransactionId();
        
        updateCartDisplay();
        calculateTotals();
        document.getElementById('transaction-id').textContent = state.transactionId;
        document.getElementById('productSearch').focus();
    }
}

// Process payment
function processPayment(method) {
    state.paymentMethod = method.charAt(0).toUpperCase() + method.slice(1);
    
    if (method === 'cash') {
        const amount = prompt('Enter amount tendered:');
        if (amount && !isNaN(amount)) {
            state.amountTendered = parseFloat(amount);
            state.change = state.amountTendered - state.total;
            
            if (state.change < 0) {
                alert('Amount tendered is less than total amount');
                return;
            }
            
            // Update receipt
            document.getElementById('receipt-payment-method').textContent = state.paymentMethod;
            document.getElementById('receipt-tendered').textContent = state.amountTendered.toFixed(2);
            document.getElementById('receipt-change').textContent = state.change.toFixed(2);
            
            completeSale();
        }
    } else {
        // For card/mobile payments, assume full amount is tendered
        state.amountTendered = state.total;
        state.change = 0;
        
        // Update receipt
        document.getElementById('receipt-payment-method').textContent = state.paymentMethod;
        document.getElementById('receipt-tendered').textContent = state.total.toFixed(2);
        document.getElementById('receipt-change').textContent = '0.00';
        
        completeSale();
    }
}

// Complete sale and print receipt
function completeSale() {
    if (state.cart.length === 0) {
        alert('Cart is empty. Add items before completing sale.');
        return;
    }
    
    if (state.total <= 0) {
        alert('Invalid total amount.');
        return;
    }
    
    if (!state.paymentMethod) {
        alert('Please select a payment method first.');
        return;
    }
    
    // Update receipt items
    const receiptItemsContainer = document.getElementById('receipt-items');
    receiptItemsContainer.innerHTML = '';
    
    state.cart.forEach(item => {
        const itemElement = document.createElement('div');
        itemElement.className = 'flex justify-between py-1 border-b border-gray-200';
        itemElement.innerHTML = `
            <span>${item.quantity}x ${item.name}</span>
            <span>$${(item.price * item.quantity).toFixed(2)}</span>
        `;
        receiptItemsContainer.appendChild(itemElement);
    });
    
    // Update transaction ID on receipt
    document.getElementById('receipt-transaction').textContent = state.transactionId;
    
    // Print receipt
    printReceipt();
    
    // In a real POS, you would send this data to your backend
    console.log('Transaction completed:', {
        transactionId: state.transactionId,
        items: state.cart,
        subtotal: state.subtotal,
        tax: state.taxAmount,
        total: state.total,
        paymentMethod: state.paymentMethod,
        amountTendered: state.amountTendered,
        change: state.change,
        timestamp: new Date().toISOString()
    });
    
    // Reset for next transaction
    state.cart = [];
    state.subtotal = 0;
    state.taxAmount = 0;
    state.total = 0;
    state.paymentMethod = '';
    state.amountTendered = 0;
    state.change = 0;
    state.transactionId = generateTransactionId();
    
    updateCartDisplay();
    calculateTotals();
    document.getElementById('transaction-id').textContent = state.transactionId;
    document.getElementById('productSearch').focus();
}

// Initialize keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Focus search on Ctrl+F
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('productSearch').focus();
        }
        
        // Complete sale on Enter
        if (e.key === 'Enter' && !document.querySelector('.fixed.inset-0')) {
            completeSale();
        }
        
        // Void transaction on Ctrl+V
        if (e.ctrlKey && e.key === 'v') {
            e.preventDefault();
            voidTransaction();
        }
        
        // Print receipt on Ctrl+P
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printReceipt();
        }
    });
}

// Numeric keypad functions
function addToAmount(value) {
    // In a real POS, this would add to payment amount or quantity
    console.log('Adding:', value);
}

function clearAmount() {
    console.log('Clearing input');
}

function calculateChange() {
    if (state.paymentMethod && state.amountTendered > 0) {
        state.change = state.amountTendered - state.total;
        alert(`Change due: $${state.change.toFixed(2)}`);
    } else {
        alert('Please process payment first');
    }
} 