// Print receipt
function printReceipt() {
    // Update the printable receipt content
    const printableReceipt = document.getElementById('printable-receipt');
    
    // Show the printable receipt temporarily
    printableReceipt.classList.remove('hidden');
    
    // Print the receipt
    window.print();
    
    // Hide it again after printing
    printableReceipt.classList.add('hidden');
}

// Format currency
function formatCurrency(amount) {
    return `${CONFIG.UI.CURRENCY_SYMBOL}${amount.toFixed(CONFIG.UI.DECIMAL_PLACES)}`;
}

// Format date
function formatDate(date) {
    const options = { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit' 
    };
    return date.toLocaleDateString(undefined, options);
}

// Format time
function formatTime(date) {
    const options = { 
        hour: '2-digit', 
        minute: '2-digit' 
    };
    return date.toLocaleTimeString(undefined, options);
}

// Generate receipt header
function generateReceiptHeader() {
    return `
        <div class="text-center mb-2">
            <div class="font-bold">${CONFIG.STORE.NAME}</div>
            <div>${CONFIG.STORE.ADDRESS}</div>
            <div>Tel: ${CONFIG.STORE.PHONE}</div>
        </div>
    `;
}

// Generate receipt transaction info
function generateReceiptTransactionInfo() {
    const now = new Date();
    return `
        <div class="border-t border-b border-gray-300 py-2 my-2">
            <div class="flex justify-between">
                <span>Cashier: <span id="receipt-cashier">John D.</span></span>
                <span>Terminal: ${CONFIG.STORE.TERMINAL}</span>
            </div>
            <div class="flex justify-between">
                <span>Date: ${formatDate(now)}</span>
                <span>Time: ${formatTime(now)}</span>
            </div>
            <div>Transaction: ${state.transactionId}</div>
        </div>
    `;
}

// Generate receipt items
function generateReceiptItems() {
    return state.cart.map(item => `
        <div class="flex justify-between py-1 border-b border-gray-200">
            <span>${item.quantity}x ${item.name}</span>
            <span>${formatCurrency(item.price * item.quantity)}</span>
        </div>
    `).join('');
}

// Generate receipt totals
function generateReceiptTotals() {
    return `
        <div class="border-t border-b border-gray-300 py-2 my-2 font-bold">
            <div class="flex justify-between">
                <span>Subtotal:</span>
                <span>${formatCurrency(state.subtotal)}</span>
            </div>
            <div class="flex justify-between">
                <span>Tax (${state.taxRate}%):</span>
                <span>${formatCurrency(state.taxAmount)}</span>
            </div>
            <div class="flex justify-between">
                <span>Total:</span>
                <span>${formatCurrency(state.total)}</span>
            </div>
            <div class="flex justify-between">
                <span>Payment Method:</span>
                <span>${state.paymentMethod}</span>
            </div>
            <div class="flex justify-between">
                <span>Amount Tendered:</span>
                <span>${formatCurrency(state.amountTendered)}</span>
            </div>
            <div class="flex justify-between">
                <span>Change:</span>
                <span>${formatCurrency(state.change)}</span>
            </div>
        </div>
    `;
}

// Generate receipt footer
function generateReceiptFooter() {
    return `
        <div class="text-center mt-4 text-xs">
            <div>Thank you for shopping with us!</div>
            <div class="mt-1">Returns accepted within 30 days with receipt</div>
            <div class="mt-2 text-[8px]">
                <div>------------------------------------------</div>
                <div>This is a computer generated receipt</div>
                <div>No signature required</div>
            </div>
        </div>
    `;
}

// Update receipt content
function updateReceiptContent() {
    const receiptContainer = document.getElementById('printable-receipt');
    receiptContainer.innerHTML = `
        <div class="receipt-paper p-4 font-mono text-sm">
            ${generateReceiptHeader()}
            ${generateReceiptTransactionInfo()}
            <div class="mb-4">
                ${generateReceiptItems()}
            </div>
            ${generateReceiptTotals()}
            ${generateReceiptFooter()}
        </div>
    `;
} 