<?php
require_once __DIR__ . '/../../auth/check_session.php';
$user = checkAuth('admin');

require_once __DIR__ . '/../../../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch all categories with product counts
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(p.id) as product_count,
               COALESCE(SUM(p.stock), 0) as total_stock
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        GROUP BY c.id
        ORDER BY c.created_at DESC, c.name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching categories: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div x-data="{ 
        showModal: false,
        showDeleteModal: false,
        modalType: 'add',
        selectedCategory: null,
        categoryName: '',
        categoryDescription: '',
        searchQuery: '',
        filterCategories() {
            const searchLower = this.searchQuery.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const description = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                
                const matchesSearch = name.includes(searchLower) || description.includes(searchLower);
                row.style.display = matchesSearch ? '' : 'none';
            });
        },
        openAddModal() {
            this.modalType = 'add';
            this.selectedCategory = null;
            this.categoryName = '';
            this.categoryDescription = '';
            this.showModal = true;
        },
        openEditModal(category) {
            this.modalType = 'edit';
            this.selectedCategory = category;
            this.categoryName = category.name;
            this.categoryDescription = category.description || '';
            this.showModal = true;
        },
        openDeleteModal(category) {
            this.selectedCategory = category;
            this.showDeleteModal = true;
        },
        async saveCategory() {
            if (!this.categoryName.trim()) {
                alert('Category name is required');
                return;
            }

            const data = {
                name: this.categoryName.trim(),
                description: this.categoryDescription.trim()
            };

            if (this.modalType === 'edit' && this.selectedCategory) {
                data.id = this.selectedCategory.id;
            }

            try {
                const response = await fetch('save_category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to save category'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving category: ' + error.message);
            }
        },
        async deleteCategory() {
            if (!this.selectedCategory) return;

            try {
                const response = await fetch('delete_category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: this.selectedCategory.id })
                });

                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to delete category'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting category: ' + error.message);
            }
        }
    }" class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg">
            <?php include __DIR__ . '/../shared/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64 overflow-auto">
            <div class="p-8">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold text-gray-800">Category Management</h1>
                            <div class="flex space-x-4">
                                <div class="relative">
                                    <input type="text" 
                                           placeholder="Search categories..." 
                                           class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                           x-model="searchQuery"
                                           @input="filterCategories">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                                <button @click="openAddModal()" 
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <i class="fas fa-plus mr-2"></i> Add Category
                                </button>
                            </div>
                        </div>

                        <!-- Categories Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Products</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Total Stock</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 truncate max-w-[200px]" title="<?php echo htmlspecialchars($category['name']); ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500 truncate max-w-[300px]" title="<?php echo htmlspecialchars($category['description'] ?? 'No description'); ?>">
                                                <?php echo htmlspecialchars($category['description'] ?? 'No description'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($category['product_count']); ?> products
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo number_format($category['total_stock']); ?> units
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button @click="openEditModal(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button @click="openDeleteModal(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Delete
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

        <!-- Category Modal -->
        <div x-show="showModal" 
             class="fixed inset-0 z-50 overflow-y-auto" 
             x-cloak
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click.away="showModal = false">
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 opacity-75"></div>

                <!-- Modal panel -->
                <div class="relative bg-white rounded-2xl shadow-xl transform transition-all w-full max-w-lg "
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    
                    <!-- Modal header -->
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-gray-200 rounded-xl">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="modalType === 'add' ? 'Add New Category' : 'Edit Category'"></h3>
                            <button type="button" 
                                    @click="showModal = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Modal body -->
                    <form @submit.prevent="saveCategory()" class="bg-white rounded-2xl" >
                        <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="space-y-6">
                                <!-- Category Name -->
                                <div>
                                    <label for="categoryName" class="block text-sm font-medium text-gray-700">
                                        Category Name <span class="text-red-500">*</span>
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="text" 
                                               id="categoryName"
                                               x-model="categoryName"
                                               class="block w-full h-12 rounded-md border-2 border-gray-300 pl-2 pr-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                               placeholder="Enter category name"
                                               required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <i class="fas fa-tag text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label for="categoryDescription" class="block text-sm font-medium text-gray-700">
                                        Description
                                    </label>
                                    <div class="mt-1">
                                        <textarea id="categoryDescription"
                                                  x-model="categoryDescription"
                                                  rows="3"
                                                  class="block w-full rounded-md border-2 border-gray-300 pl-2 pr-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                                                  placeholder="Enter category description"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal footer -->
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse  border-gray-200 rounded-b-2xl">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                <i class="fas fa-save mr-2"></i>
                                <span x-text="modalType === 'add' ? 'Create Category' : 'Save Changes'"></span>
                            </button>
                            <button type="button"
                                    @click="showModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div x-show="showDeleteModal" 
             class="fixed inset-0 z-50 overflow-y-auto" 
             x-cloak
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click.away="showDeleteModal = false">
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 opacity-75"></div>

                <!-- Modal panel -->
                <div class="relative bg-white rounded-2xl shadow-xl transform transition-all w-full max-w-lg"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    
                    <!-- Modal header -->
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-gray-200 rounded-t-2xl">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Category</h3>
                            <button type="button" 
                                    @click="showDeleteModal = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Modal body -->
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Are you sure you want to delete this category?
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        This action cannot be undone. This will permanently delete the category
                                        <span class="font-medium text-gray-900" x-text="selectedCategory?.name"></span>
                                        and remove it from all associated products.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200 rounded-b-2xl">
                        <button type="button"
                                @click="deleteCategory(); showDeleteModal = false"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-trash mr-2"></i>
                            Delete Category
                        </button>
                        <button type="button"
                                @click="showDeleteModal = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            [x-cloak] { display: none !important; }
            
            /* Custom scrollbar for modal */
            .overflow-y-auto::-webkit-scrollbar {
                width: 8px;
            }
            
            .overflow-y-auto::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }
            
            .overflow-y-auto::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }
            
            .overflow-y-auto::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        </style>
    </div>
</body>
</html> 