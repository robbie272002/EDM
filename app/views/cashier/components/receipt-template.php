<div id="receipt-template" class="hidden">
    <div class="bg-white p-4 max-w-sm mx-auto">
        <div class="text-center mb-4">
            <h2 class="text-xl font-bold">Store Name</h2>
            <p class="text-sm text-gray-600">123 Main Street</p>
            <p class="text-sm text-gray-600">Phone: (123) 456-7890</p>
        </div>
        
        <div class="border-t border-b border-gray-200 py-2 mb-4">
            <div class="flex justify-between text-sm">
                <span>Date:</span>
                <span id="receipt-date"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Receipt #:</span>
                <span id="receipt-number"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span>Cashier:</span>
                <span id="receipt-cashier"></span>
            </div>
        </div>
        
        <div class="mb-4">
            <div class="text-sm font-medium mb-2">Items:</div>
            <div id="receipt-items"></div>
        </div>
        
        <div class="border-t border-gray-200 pt-2">
            <div class="flex justify-between text-sm mb-1">
                <span>Subtotal:</span>
                <span id="receipt-subtotal"></span>
            </div>
            <div class="flex justify-between text-sm mb-1">
                <span>Tax (10%):</span>
                <span id="receipt-tax"></span>
            </div>
            <div class="flex justify-between font-bold">
                <span>Total:</span>
                <span id="receipt-total"></span>
            </div>
        </div>
        
        <div class="mt-4 text-center text-sm text-gray-600">
            <p>Thank you for your purchase!</p>
            <p>Please come again</p>
        </div>
    </div>
</div> 