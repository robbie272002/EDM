<?php
// Fetch categories from database
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $categories = [];
}
?>
<div class="mb-4 overflow-x-auto">
    <div class="flex space-x-2">
        <button class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                data-category="all">
            All
        </button>
        <?php foreach ($categories as $category): ?>
        <button class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" 
                data-category="<?php echo htmlspecialchars($category); ?>">
            <?php echo htmlspecialchars($category); ?>
        </button>
        <?php endforeach; ?>
    </div>
</div> 