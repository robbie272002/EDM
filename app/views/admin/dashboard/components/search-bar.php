<?php
$placeholder = isset($placeholder) ? $placeholder : 'Scan barcode or search products...';
?>
<div class="mb-4">
    <div class="relative">
        <input type="text" 
               id="product-search" 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" 
               placeholder="Search products...">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
    </div>
</div> 