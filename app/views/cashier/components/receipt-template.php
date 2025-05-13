<?php
// Add the close button at the very top of the receipt template
?>
<div id="receipt-template" class="receipt-print hidden fixed top-0 left-0 w-full h-full flex items-center justify-center bg-black bg-opacity-50 z-50">
    <!-- Add X button -->
    <button onclick="closeReceipt()" class="absolute top-6 right-6 bg-red-500 hover:bg-red-600 text-white rounded-full w-10 h-10 flex items-center justify-center shadow-lg z-50 no-print">
        <i class="fas fa-times text-xl"></i>
    </button>
    <div class="receipt-paper bg-white p-4 font-mono text-sm w-[80mm] mx-auto">
        <div class="text-center mb-4">
            <h2 class="text-xl font-bold">RETAIL STORE #042</h2>
            <p class="text-sm">123 Main Street, City</p>
            <p class="text-sm">Tel: (555) 123-4567</p>
            <p class="text-sm">VAT Reg: 123-456-789-000</p>
        </div>
        
        <div class="border-t border-b border-gray-300 py-2 my-2">
            <div class="flex justify-between text-sm">
                <span>Cashier:</span>
                <span id="receipt-cashier"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Date:</span>
                <span id="receipt-date"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Time:</span>
                <span id="receipt-time"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Transaction #:</span>
                <span id="receipt-transaction"></span>
            </div>
        </div>
        
        <div class="my-4" id="receipt-items">
            <!-- Items will be dynamically added here -->
        </div>
        
        <div class="border-t border-gray-300 pt-2">
            <div class="flex justify-between text-sm">
                <span>Subtotal:</span>
                <span>₱<span id="receipt-subtotal">0.00</span></span>
            </div>
            <div id="receipt-discount-row" class="flex justify-between text-sm" style="display:none;">
                <span id="receipt-discount-label">Discount (0%):</span>
                <span>-₱<span id="receipt-discount-amount">0.00</span></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Tax (<span id="receipt-tax-rate">8</span>%):</span>
                <span>₱<span id="receipt-tax-amount">0.00</span></span>
            </div>
            <div class="flex justify-between text-sm font-bold border-t border-gray-300 mt-2 pt-2">
                <span>Total:</span>
                <span>₱<span id="receipt-total">0.00</span></span>
            </div>
        </div>

        <div class="border-t border-gray-300 mt-2 pt-2">
            <div class="flex justify-between text-sm">
                <span>Payment Method:</span>
                <span id="receipt-payment-method"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Amount Tendered:</span>
                <span>₱<span id="receipt-tendered">0.00</span></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Change:</span>
                <span>₱<span id="receipt-change">0.00</span></span>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-sm">Thank you for shopping with us!</p>
            <p class="text-xs mt-1">Returns accepted within 30 days with receipt</p>
            <div class="mt-4 text-[10px]">
                <p>----------------------------------------</p>
                <p>This is a computer generated receipt</p>
                <p>No signature required</p>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        body * {
            visibility: hidden !important;
        }
        
        .receipt-print, .receipt-print * {
            visibility: visible !important;
        }
        
        .receipt-print {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 80mm !important;
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
        }
    }
</style> 