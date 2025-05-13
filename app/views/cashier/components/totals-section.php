<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
            <select id="payment-method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="mobile">Mobile Payment</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Amount Received</label>
            <input type="number" id="amount-received" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="0.00">
        </div>
    </div>
    
    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Change</label>
        <div class="text-2xl font-bold text-indigo-600" id="change-amount">$0.00</div>
    </div>
    
    <div class="mt-6">
        <button id="complete-sale" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Complete Sale
        </button>
    </div>
</div> 