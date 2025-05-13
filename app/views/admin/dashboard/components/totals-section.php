<?php
$subtotal = isset($subtotal) ? $subtotal : 0;
$taxRate = isset($taxRate) ? $taxRate : 8;
$taxAmount = $subtotal * ($taxRate / 100);
$total = $subtotal + $taxAmount;
?>
<div class="p-4 border-t border-gray-200 bg-white">
    <div class="space-y-2">
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Subtotal</span>
            <span class="font-medium" id="subtotal">$<?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Tax (<?php echo $taxRate; ?>%)</span>
            <span class="font-medium" id="tax">$<?php echo number_format($taxAmount, 2); ?></span>
        </div>
        <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2 mt-2">
            <span>Total</span>
            <span id="total">$<?php echo number_format($total, 2); ?></span>
        </div>
    </div>
    
    <div class="mt-4 space-y-2">
        <button id="complete-sale" 
                class="w-full py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Complete Sale
        </button>
        <button id="hold-transaction" 
                class="w-full py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
            Hold Transaction
        </button>
    </div>
</div> 