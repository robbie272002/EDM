<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Current Transaction</h2>
        <button id="clear-transaction" class="text-red-600 hover:text-red-700 text-sm font-medium">
            Clear All
        </button>
    </div>
    
    <div class="space-y-2 mb-4" id="transaction-items">
        <!-- Transaction items will be added here dynamically -->
    </div>
    
    <div class="border-t border-gray-200 pt-4">
        <div class="flex justify-between text-sm mb-1">
            <span class="text-gray-600">Subtotal</span>
            <span class="font-medium" id="subtotal">$0.00</span>
        </div>
        <div class="flex justify-between text-sm mb-1">
            <span class="text-gray-600">Tax (10%)</span>
            <span class="font-medium" id="tax">$0.00</span>
        </div>
        <div class="flex justify-between text-lg font-bold">
            <span class="text-gray-900">Total</span>
            <span class="text-indigo-600" id="total">$0.00</span>
        </div>
    </div>
</div> 