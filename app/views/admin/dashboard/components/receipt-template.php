<?php
$storeName = isset($storeName) ? $storeName : 'Your Store Name';
$storeAddress = isset($storeAddress) ? $storeAddress : '123 Store Street, City, Country';
$storePhone = isset($storePhone) ? $storePhone : '(123) 456-7890';
$storeEmail = isset($storeEmail) ? $storeEmail : 'store@example.com';
$transactionId = isset($transactionId) ? $transactionId : '';
$date = isset($date) ? $date : date('Y-m-d H:i:s');
$cashier = isset($cashier) ? $cashier : '';
$items = isset($items) ? $items : [];
$subtotal = isset($subtotal) ? $subtotal : 0;
$taxRate = isset($taxRate) ? $taxRate : 8;
$taxAmount = $subtotal * ($taxRate / 100);
$total = $subtotal + $taxAmount;
?>
<div id="receipt" class="w-80 bg-white p-4 font-mono text-sm">
    <div class="text-center mb-4">
        <h2 class="text-lg font-bold"><?php echo htmlspecialchars($storeName); ?></h2>
        <p class="text-xs"><?php echo htmlspecialchars($storeAddress); ?></p>
        <p class="text-xs">Tel: <?php echo htmlspecialchars($storePhone); ?></p>
        <p class="text-xs"><?php echo htmlspecialchars($storeEmail); ?></p>
    </div>
    
    <div class="border-t border-b border-dashed border-gray-400 py-2 mb-2">
        <p class="text-xs">Transaction #: <?php echo htmlspecialchars($transactionId); ?></p>
        <p class="text-xs">Date: <?php echo htmlspecialchars($date); ?></p>
        <p class="text-xs">Cashier: <?php echo htmlspecialchars($cashier); ?></p>
    </div>
    
    <div class="mb-2">
        <div class="flex justify-between text-xs mb-1">
            <span>Item</span>
            <span>Qty</span>
            <span>Price</span>
            <span>Total</span>
        </div>
        <?php foreach ($items as $item): ?>
        <div class="flex justify-between text-xs mb-1">
            <span class="flex-1"><?php echo htmlspecialchars($item['name']); ?></span>
            <span class="w-8 text-right"><?php echo $item['quantity']; ?></span>
            <span class="w-16 text-right">$<?php echo number_format($item['price'], 2); ?></span>
            <span class="w-16 text-right">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="border-t border-dashed border-gray-400 pt-2">
        <div class="flex justify-between text-xs mb-1">
            <span>Subtotal:</span>
            <span>$<?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="flex justify-between text-xs mb-1">
            <span>Tax (<?php echo $taxRate; ?>%):</span>
            <span>$<?php echo number_format($taxAmount, 2); ?></span>
        </div>
        <div class="flex justify-between font-bold mb-1">
            <span>Total:</span>
            <span>$<?php echo number_format($total, 2); ?></span>
        </div>
    </div>
    
    <div class="text-center mt-4 text-xs">
        <p>Thank you for your purchase!</p>
        <p>Please come again</p>
    </div>
</div> 