<?php
require_once __DIR__ . '/../../../config/database.php';

// Fetch all products with their categories
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC, p.name ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all categories for the dropdown
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .minimal-input, .minimal-select {
            height: 40px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background: #fff;
            box-shadow: none;
            font-size: 0.95rem;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
            transition: border 0.2s;
        }
        .minimal-input:focus, .minimal-select:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 2px #2563eb22;
        }
        .minimal-btn {
            border-radius: 6px;
            background: #f3f4f6;
            color: #1f2937;
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            transition: background 0.2s;
        }
        .minimal-btn:hover {
            background: #e5e7eb;
        }
        .minimal-table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e5e7eb;
        }
        .minimal-table td {
            border-bottom: 1px solid #f3f4f6;
        }
        .minimal-img {
            border-radius: 6px;
            width: 36px;
            height: 36px;
            object-fit: cover;
        }
    </style>
</head>
<body class="bg-gray-50">
<div x-data="{ sidebarOpen: false }" class="flex h-screen" x-cloak>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../shared/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <div class="flex-1 overflow-y-auto p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Product Management</h1>
                <button @click="$dispatch('open-modal', 'add-product')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-plus mr-2"></i>Add New Product
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-md border border-gray-200 p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Search</label>
                        <input type="text" id="searchInput" placeholder="Search by product name or SKU..." class="minimal-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Category</label>
                        <select id="categoryFilter" class="minimal-select w-full">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Sort By</label>
                        <select id="sortFilter" class="minimal-select w-full">
                            <option value="name">Name</option>
                            <option value="price">Price</option>
                            <option value="category">Category</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full minimal-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Product</th>
                                <th class="px-4 py-2 text-left">Category</th>
                                <th class="px-4 py-2 text-left">SKU</th>
                                <th class="px-4 py-2 text-left">Price</th>
                                <th class="px-4 py-2 text-center">Status</th>
                                <th class="px-4 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <?php foreach ($products as $product): ?>
                            <tr data-category-id="<?= $product['category_id'] ?>">
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img class="minimal-img" 
                                             src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://via.placeholder.com/40'; ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <span class="ml-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($product['sku']); ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                    $<?php echo number_format($product['price'], 2); ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $product['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                                    <button @click="$dispatch('open-modal', 'edit-product-<?= $product['id'] ?>')" 
                                            class="minimal-btn mr-2">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="toggleProductStatus(<?= $product['id'] ?>, '<?= $product['status'] ?>')" 
                                            class="minimal-btn <?php echo $product['status'] === 'active' ? 'text-red-600' : 'text-green-600'; ?>">
                                        <i class="fas <?php echo $product['status'] === 'active' ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
                                        <?php echo $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div x-data="{ show: false }" 
     x-show="show" 
     x-on:open-modal.window="if ($event.detail === 'add-product') show = true"
     x-on:close-modal.window="show = false"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30 overflow-y-auto" 
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-6">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-xl" style="width: 620px;">
            <form action="add_product.php" method="POST" enctype="multipart/form-data" class="w-full">
                <h3 class="text-2xl font-semibold text-gray-800 mb-6">Add New Product</h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-2">Product Image</label>
                        <div class="flex items-center gap-6">
                            <div class="w-28 h-28 rounded-xl overflow-hidden border-2 border-gray-200 flex items-center justify-center bg-gray-50">
                                <img id="imagePreview" src="#" alt="Preview" class="hidden w-full h-full object-cover">
                                <div id="uploadPlaceholder" class="text-gray-400 text-center p-2">
                                    <i class="fas fa-image text-2xl mb-1"></i>
                                    <p class="text-xs">No image</p>
                                </div>
                            </div>
                            <div class="flex-1">
                                <input type="file" 
                                       name="image" 
                                       id="imageInput" 
                                       accept="image/*" 
                                       class="hidden"
                                       onchange="previewImage(this)">
                                <button type="button" 
                                        onclick="document.getElementById('imageInput').click()" 
                                        class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition">
                                    Choose Image
                                </button>
                                <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" required class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Category</label>
                        <select name="category_id" required class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Price</label>
                        <input type="number" name="price" step="0.01" min="0" required class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="px-5 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<?php foreach ($products as $product): ?>
<div x-data="{ show: false }" 
     x-show="show" 
     x-on:open-modal.window="if ($event.detail === 'edit-product-<?= $product['id'] ?>') show = true"
     x-on:close-modal.window="show = false"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30 overflow-y-auto" 
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-6">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-xl" style="width: 620px;">
            <form action="edit_product.php" method="POST" enctype="multipart/form-data" class="w-full">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <h3 class="text-2xl font-semibold text-gray-800 mb-6">Edit Product</h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-2">Product Image</label>
                        <div class="flex items-center gap-6">
                            <div class="w-28 h-28 rounded-xl overflow-hidden border-2 border-gray-200 flex items-center justify-center bg-gray-50">
                                <img id="editImagePreview<?= $product['id'] ?>" 
                                     src="<?= !empty($product['image']) ? htmlspecialchars($product['image']) : '#' ?>" 
                                     alt="Preview" 
                                     class="<?= empty($product['image']) ? 'hidden' : '' ?> w-full h-full object-cover">
                                <div id="editUploadPlaceholder<?= $product['id'] ?>" 
                                     class="text-gray-400 text-center p-2 <?= !empty($product['image']) ? 'hidden' : '' ?>">
                                    <i class="fas fa-image text-2xl mb-1"></i>
                                    <p class="text-xs">No image</p>
                                </div>
                            </div>
                            <div class="flex-1">
                                <input type="file" 
                                       name="image" 
                                       id="editImageInput<?= $product['id'] ?>" 
                                       accept="image/*" 
                                       class="hidden"
                                       onchange="previewEditImage(this, <?= $product['id'] ?>)">
                                <button type="button" 
                                        onclick="document.getElementById('editImageInput<?= $product['id'] ?>').click()" 
                                        class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition">
                                    Choose Image
                                </button>
                                <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Category</label>
                        <select name="category_id" required class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $category['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Price</label>
                        <input type="number" name="price" step="0.01" min="0" value="<?= $product['price'] ?>" required class="block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition">
                    </div>
                </div>
                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="px-5 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div x-data="{ show: false }" 
     x-show="show" 
     x-on:open-modal.window="if ($event.detail === 'delete-product-<?= $product['id'] ?>') show = true"
     x-on:close-modal.window="show = false"
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form action="delete_product.php" method="POST" class="p-6">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Product</h3>
                <p class="text-gray-600 mb-4">Are you sure you want to delete "<?= htmlspecialchars($product['name']) ?>"? This action cannot be undone.</p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" @click="show = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                        Delete Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const placeholder = document.getElementById('uploadPlaceholder');
    const errorMsg = document.getElementById('imageError');
    
    // Clear previous error
    if (errorMsg) errorMsg.remove();
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            showImageError('Please select a valid image file (JPG, PNG, or GIF)');
            input.value = '';
            return;
        }
        
        // Validate file size (2MB max)
        const maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if (file.size > maxSize) {
            showImageError('Image size should be less than 2MB');
            input.value = '';
            return;
        }
        
        // Preview valid image
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
    }
}

function showImageError(message) {
    // Remove any existing error message
    const existingError = document.getElementById('imageError');
    if (existingError) existingError.remove();
    
    // Create and show new error message
    const errorDiv = document.createElement('div');
    errorDiv.id = 'imageError';
    errorDiv.className = 'mt-2 text-sm text-red-600';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i>${message}`;
    
    // Insert error message after the image upload section
    const uploadSection = document.querySelector('.flex.items-center.gap-6');
    uploadSection.parentNode.insertBefore(errorDiv, uploadSection.nextSibling);
    
    // Reset preview
    const preview = document.getElementById('imagePreview');
    const placeholder = document.getElementById('uploadPlaceholder');
    preview.classList.add('hidden');
    placeholder.classList.remove('hidden');
}

// Add form validation before submit
document.querySelector('form[action="add_product.php"]').addEventListener('submit', function(e) {
    const imageInput = document.getElementById('imageInput');
    if (imageInput.files.length === 0) {
        e.preventDefault();
        showImageError('Please select a product image');
        return false;
    }
    return true;
});

function previewEditImage(input, productId) {
    const preview = document.getElementById('editImagePreview' + productId);
    const placeholder = document.getElementById('editUploadPlaceholder' + productId);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
    }
}

// Filter and sort functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const sortFilter = document.getElementById('sortFilter');
    const tableBody = document.querySelector('tbody');
    const rows = Array.from(tableBody.querySelectorAll('tr'));

    function filterAndSort() {
        const searchTerm = searchInput.value.toLowerCase();
        const categoryValue = categoryFilter.value;
        const sortValue = sortFilter.value;

        // Filter rows
        rows.forEach(row => {
            const name = row.querySelector('td:first-child').textContent.toLowerCase();
            const sku = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const categoryCell = row.querySelector('td:nth-child(2)');
            const matchesSearch = name.includes(searchTerm) || sku.includes(searchTerm);
            const matchesCategory = !categoryValue || categoryCell.closest('tr').getAttribute('data-category-id') === categoryValue;

            row.style.display = matchesSearch && matchesCategory ? '' : 'none';
        });

        // Sort rows
        const visibleRows = rows.filter(row => row.style.display !== 'none');
        visibleRows.sort((a, b) => {
            let aValue, bValue;
            switch(sortValue) {
                case 'name':
                    aValue = a.querySelector('td:first-child').textContent;
                    bValue = b.querySelector('td:first-child').textContent;
                    break;
                case 'price':
                    aValue = parseFloat(a.querySelector('td:nth-child(4)').textContent.replace('$', ''));
                    bValue = parseFloat(b.querySelector('td:nth-child(4)').textContent.replace('$', ''));
                    break;
                case 'category':
                    aValue = a.querySelector('td:nth-child(2)').textContent;
                    bValue = b.querySelector('td:nth-child(2)').textContent;
                    break;
            }
            return aValue > bValue ? 1 : -1;
        });

        // Reorder rows in the table
        visibleRows.forEach(row => tableBody.appendChild(row));
    }

    searchInput.addEventListener('input', filterAndSort);
    categoryFilter.addEventListener('change', filterAndSort);
    sortFilter.addEventListener('change', filterAndSort);
});

function toggleProductStatus(productId, currentStatus) {
    if (!confirm('Are you sure you want to ' + (currentStatus === 'active' ? 'deactivate' : 'activate') + ' this product?')) {
        return;
    }

    fetch('toggle_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'product_id=' + productId + '&current_status=' + currentStatus
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error updating product status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating product status');
    });
}
</script>
</body>
</html> 