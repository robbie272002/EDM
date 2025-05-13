<?php
// Fetch only active products for the cashier view
$stmt = $pdo->query("
    SELECT p.*, c.name as category 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY p.name ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="product-grid">
    <?php foreach ($products as $product): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer product-card"
         data-product-id="<?php echo $product['id']; ?>"
         data-category="<?php echo htmlspecialchars($product['category']); ?>">
        <div class="aspect-w-1 aspect-h-1 mb-2">
            <img src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : '/NEW/public/assets/images/no-image.png'; ?>" 
                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                 class="object-cover rounded-lg">
        </div>
        <h3 class="text-sm font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($product['name']); ?></h3>
        <p class="text-lg font-bold text-indigo-600">$<?php echo number_format($product['price'], 2); ?></p>
        <p class="text-xs text-gray-500">Stock: <?php echo $product['stock']; ?></p>
    </div>
    <?php endforeach; ?>
</div> 