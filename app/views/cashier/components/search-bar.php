<?php
$placeholder = isset($placeholder) ? $placeholder : 'Scan barcode or search products...';
?>
<div class="relative mb-4">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <i class="fas fa-search text-gray-400"></i>
    </div>
    <input 
        type="text" 
        id="productSearch" 
        class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
        placeholder="Scan barcode or search products..."
        autofocus
    >
    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
        <button class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-barcode"></i>
        </button>
    </div>
</div> 