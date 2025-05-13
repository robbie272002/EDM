<?php
require_once __DIR__ . '/../../auth/check_session.php';
$user = checkAuth('admin');

require_once __DIR__ . '/../../../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../shared/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold text-gray-800">Add New Category</h1>
                            <a href="index.php" class="text-gray-600 hover:text-gray-900">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>

                        <form action="save_category.php" method="POST" class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Category Name</label>
                                <input type="text" 
                                       name="name" 
                                       id="name" 
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                       placeholder="Enter category name">
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" 
                                          id="description" 
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                          placeholder="Enter category description"></textarea>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <a href="index.php" 
                                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                                    Cancel
                                </a>
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md">
                                    Save Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 