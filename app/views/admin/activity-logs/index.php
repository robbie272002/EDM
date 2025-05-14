<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Check if user is admin
try {
    $user = checkAuth('admin');
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

// Fetch all logs without any filters
try {
    $query = "
        SELECT al.*, u.name as user_name, u.role as user_role
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}

// Get unique action types for filter
try {
    $actionTypes = $pdo->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $actionTypes = [];
}

// Debug session
error_log("Session data: " . print_r($_SESSION, true));

// Add JavaScript for filter handling
?>
<script>
// Remove all the old JavaScript code and keep only Alpine.js implementation
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .filter-bar {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .filter-input {
            width: 100%;
            height: 2.5rem;
            padding: 0.5rem 0.75rem;
            background-color: white;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: #1F2937;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .filter-input:hover {
            border-color: #9CA3AF;
        }
        .filter-input:focus {
            outline: none;
            border-color: #6366F1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        select.filter-input {
            padding-right: 2rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .modal-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .btn-primary {
            background-color: #4f46e5;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: #4338ca;
        }
        .btn-secondary {
            background-color: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-secondary:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
        }
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
</head>
<body class="bg-gray-100" x-data="{ 
    searchQuery: '',
    selectedActionType: '',
    showDateModal: false,
    startDate: '',
    endDate: '',
    dateError: '',
    today: new Date().toISOString().split('T')[0],
    hasVisibleRows: true,
    
    init() {
        // Set default date range to last 30 days
        const today = new Date();
        const lastMonth = new Date();
        lastMonth.setDate(lastMonth.getDate() - 30);
        
        this.endDate = today.toISOString().split('T')[0];
        this.startDate = lastMonth.toISOString().split('T')[0];
        
        // Initial filter
        this.$nextTick(() => {
            this.filterLogs();
        });
    },

    validateDates() {
        const start = new Date(this.startDate);
        const end = new Date(this.endDate);
        const today = new Date();
        
        start.setHours(0, 0, 0, 0);
        end.setHours(0, 0, 0, 0);
        today.setHours(0, 0, 0, 0);

        if (start > today) {
            this.dateError = 'Start date cannot be in the future';
            this.startDate = this.today;
            return false;
        }

        if (end > today) {
            this.dateError = 'End date cannot be in the future';
            this.endDate = this.today;
            return false;
        }

        if (start > end) {
            this.dateError = 'Start date cannot be after end date';
            return false;
        }

        this.dateError = '';
        return true;
    },

    filterLogs() {
        if (!this.validateDates()) return;

        const searchLower = this.searchQuery.toLowerCase();
        const actionType = this.selectedActionType.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const timestamp = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
            const userName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const action = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const description = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const details = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            
            // Search filter
            const matchesSearch = !searchLower || 
                timestamp.includes(searchLower) ||
                userName.includes(searchLower) ||
                action.includes(searchLower) ||
                description.includes(searchLower) ||
                details.includes(searchLower);
            
            // Action type filter
            const matchesActionType = !actionType || 
                action.includes(actionType) || 
                action.includes(actionType.replace('_', ' '));
            
            // Date range filtering
            let matchesDateRange = true;
            if (this.startDate && this.endDate) {
                const rowTimestamp = new Date(timestamp);
                const startDate = new Date(this.startDate);
                const endDate = new Date(this.endDate);
                
                // Set time to start of day for start date and end of day for end date
                startDate.setHours(0, 0, 0, 0);
                    endDate.setHours(23, 59, 59, 999);
                
                matchesDateRange = rowTimestamp >= startDate && rowTimestamp <= endDate;
                }
            
            const isVisible = matchesSearch && matchesActionType && matchesDateRange;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        this.hasVisibleRows = visibleCount > 0;
    }
}" class="min-h-screen flex">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg">
            <?php include __DIR__ . '/../shared/sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64 overflow-auto">
            <div class="p-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Activity Logs</h1>
                        <p class="text-sm text-gray-600 mt-1">Track all system activities and user actions</p>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-4">
                        <div class="flex justify-between items-center">
                            <div class="flex space-x-4 flex-1">
                                <div class="relative flex-1">
                                    <input type="text" 
                                           placeholder="Search logs..." 
                                           class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                                           x-model="searchQuery"
                                           @input="filterLogs">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                                <select class="border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        x-model="selectedActionType"
                                        @change="filterLogs">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actionTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>">
                                            <?= ucwords(str_replace('_', ' ', $type)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                    <button @click="showDateModal = true" 
                                            class="px-4 py-2 border rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <i class="fas fa-calendar mr-2"></i> Date Range
                                    </button>
                            </div>
                        </div>

                        <!-- Active Filters -->
                        <div class="mt-4 flex flex-wrap gap-2" x-show="searchQuery || selectedActionType || startDate || endDate">
                            <div class="text-sm text-gray-600 flex flex-wrap gap-2">
                                <template x-if="searchQuery">
                                    <span class="bg-gray-100 px-3 py-1 rounded-full flex items-center">
                                        <span class="font-medium mr-1">Search:</span>
                                        <span x-text="searchQuery"></span>
                                        <button @click="searchQuery = ''; filterLogs()" class="ml-2 text-gray-500 hover:text-gray-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>
                                </template>
                                <template x-if="selectedActionType">
                                    <span class="bg-gray-100 px-3 py-1 rounded-full flex items-center">
                                        <span class="font-medium mr-1">Action:</span>
                                        <span x-text="selectedActionType.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')"></span>
                                        <button @click="selectedActionType = ''; filterLogs()" class="ml-2 text-gray-500 hover:text-gray-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>
                                </template>
                                <template x-if="startDate || endDate">
                                    <span class="bg-gray-100 px-3 py-1 rounded-full flex items-center">
                                        <span class="font-medium mr-1">Date Range:</span>
                                        <span x-text="startDate ? new Date(startDate).toLocaleDateString() : 'Any'"></span>
                                        <span class="mx-1">to</span>
                                        <span x-text="endDate ? new Date(endDate).toLocaleDateString() : 'Any'"></span>
                                        <button @click="startDate = ''; endDate = ''; filterLogs()" class="ml-2 text-gray-500 hover:text-gray-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Logs Table -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <?php if (empty($logs)): ?>
                        <div class="p-6 text-center text-gray-500">
                            No activity logs found.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($log['user_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= ucfirst(htmlspecialchars($log['user_role'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php
                                                    $color = 'bg-gray-100 text-gray-800';
                                                    if (strpos($log['action_type'], 'create') === 0) $color = 'bg-green-100 text-green-800';
                                                    if (strpos($log['action_type'], 'update') === 0) $color = 'bg-blue-100 text-blue-800';
                                                    if (strpos($log['action_type'], 'delete') === 0) $color = 'bg-red-100 text-red-800';
                                                    if (strpos($log['action_type'], 'login') !== false) $color = 'bg-purple-100 text-purple-800';
                                                    if (strpos($log['action_type'], 'logout') !== false) $color = 'bg-yellow-100 text-yellow-800';
                                                    echo $color;
                                                    ?>">
                                                    <?= ucwords(str_replace('_', ' ', $log['action_type'])) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?= htmlspecialchars($log['description']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php if ($log['affected_table']): ?>
                                                    <div>Table: <?= htmlspecialchars($log['affected_table']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($log['affected_id']): ?>
                                                    <div>ID: <?= htmlspecialchars($log['affected_id']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- No Results Message -->
                            <div x-show="!hasVisibleRows" 
                                 x-cloak
                                 class="py-12 text-center bg-gray-50 rounded-lg border-t">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                    <i class="fas fa-search text-gray-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Activity Logs Found</h3>
                                <p class="text-sm text-gray-600">
                                    <template x-if="startDate && endDate">
                                        <span>No activity logs have been found for the selected date range.</span>
                                    </template>
                                    <template x-if="searchQuery && !startDate && !endDate">
                                        <span>No activity logs match your search criteria.</span>
                                    </template>
                                    <template x-if="selectedActionType && !startDate && !endDate">
                                        <span>No activity logs found for the selected action type.</span>
                                    </template>
                                    <template x-if="!searchQuery && !selectedActionType && !startDate && !endDate">
                                        <span>No activity logs available.</span>
                                    </template>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Modal -->
    <div x-show="showDateModal" 
         class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30" 
         x-cloak
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.away="showDateModal = false">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-semibold mb-6 text-gray-800">Custom Date Range</h2>
            <form @submit.prevent="filterLogs(); showDateModal = false" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="modalStartDate" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" 
                               id="modalStartDate" 
                               x-model="startDate"
                               @change="validateDates(); filterLogs()"
                               :max="endDate || today"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
                    <div>
                        <label for="modalEndDate" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" 
                               id="modalEndDate" 
                               x-model="endDate"
                               @change="validateDates(); filterLogs()"
                               :min="startDate"
                               :max="today"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
          </div>
                <div x-show="dateError" class="text-sm text-red-600" x-text="dateError"></div>
                <div class="flex justify-end space-x-3">
                    <button type="button" 
                            @click="showDateModal = false"
                            class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
            <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Apply
                    </button>
          </div>
        </form>
      </div>
    </div>
</body>
</html> 