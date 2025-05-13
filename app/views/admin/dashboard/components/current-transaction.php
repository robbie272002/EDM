<?php
$transactionId = isset($transactionId) ? $transactionId : date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
?>
<div class="p-4 border-b border-gray-200">
    <div class="flex justify-between items-center mb-2">
        <h2 class="text-lg font-semibold text-gray-800">Current Transaction</h2>
        <span class="text-sm text-gray-600">#<?php echo $transactionId; ?></span>
    </div>
    <div class="text-sm text-gray-600">
        <?php echo date('F j, Y g:i A'); ?>
    </div>
</div> 