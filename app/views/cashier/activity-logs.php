<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/check_session.php';

// Check if user is cashier
try {
    $user = checkAuth('cashier');
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$actionType = $_GET['action_type'] ?? '';

// Base query to get logs specific to the cashier
try {
    $query = "
        SELECT al.*, u.name as user_name
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.user_id = :user_id
        AND (
            al.action_type LIKE 'create_sale%'
            OR al.action_type LIKE 'void_sale%'
            OR al.action_type LIKE 'refund%'
            OR al.action_type LIKE 'update_sale%'
            OR al.action_type = 'login'
            OR al.action_type = 'logout'
        )
    ";
    
    $params = ['user_id' => $user['id']];

    // Add search filter
    if ($search) {
        $query .= " AND (
            al.description LIKE :search 
            OR al.action_type LIKE :search
            OR al.affected_id LIKE :search
        )";
        $params['search'] = "%$search%";
    }

    // Add date filters
    if ($startDate) {
        $query .= " AND DATE(al.created_at) >= :start_date";
        $params['start_date'] = $startDate;
    }
    if ($endDate) {
        $query .= " AND DATE(al.created_at) <= :end_date";
        $params['end_date'] = $endDate;
    }

    $query .= " ORDER BY al.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching activity logs";
    $logs = [];
}

// Get unique action types for filter
try {
    $actionTypesQuery = "
        SELECT DISTINCT action_type 
        FROM activity_logs 
        WHERE user_id = :user_id 
        AND (
            action_type LIKE 'create_sale%'
            OR action_type LIKE 'void_sale%'
            OR action_type LIKE 'refund%'
            OR action_type LIKE 'update_sale%'
            OR action_type = 'login'
            OR action_type = 'logout'
        )
        ORDER BY action_type
    ";
    $stmt = $pdo->prepare($actionTypesQuery);
    $stmt->execute(['user_id' => $user['id']]);
    $actionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $actionTypes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Activity Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        
        .content-wrapper {
            height: calc(100vh - 2rem);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
            margin: 0;
        }
        
        .fixed-header {
            background-color: #f9fafb;
            position: sticky;
            top: 0;
            z-index: 10;
            flex-shrink: 0;
            padding: 1.5rem;
        }
        
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0 1.5rem 1.5rem 1.5rem;
        }
        
        .scrollable-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
            margin: 4px 0;
        }
        
        .scrollable-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
            border: 2px solid #f1f1f1;
        }
        
        .scrollable-content::-webkit-scrollbar-thumb:hover {
            background: #555;
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
    </style>
</head>
<body class="bg-gray-50" x-data="{ 
    searchQuery: '<?= htmlspecialchars($search) ?>',
    selectedActionType: '<?= htmlspecialchars($actionType) ?>',
    showDateModal: false,
    startDate: '<?= htmlspecialchars($startDate) ?>',
    endDate: '<?= htmlspecialchars($endDate) ?>',
    dateError: '',
    today: new Date().toISOString().split('T')[0],
    sidebarOpen: false,
    hasVisibleLogs: true,
    
    init() {
        if (!this.startDate) {
            const lastMonth = new Date();
            lastMonth.setMonth(lastMonth.getMonth() - 1);
            this.startDate = lastMonth.toISOString().split('T')[0];
        }
        if (!this.endDate) {
            this.endDate = this.today;
        }
        this.$nextTick(() => {
            this.filterLogs();
        });
    },

    filterLogs() {
        const searchLower = this.searchQuery.toLowerCase();
        const actionType = this.selectedActionType.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const timestamp = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
            const action = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const description = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const details = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            
            // Search filter
            const matchesSearch = !searchLower || 
                timestamp.includes(searchLower) ||
                action.includes(searchLower) ||
                description.includes(searchLower) ||
                details.includes(searchLower);
            
            // Action type filter
            const matchesActionType = !actionType || 
                action.includes(actionType) || 
                action.includes(actionType.replace('_', ' '));
            
            // Date range filtering
            let matchesDateRange = true;
            if (this.startDate || this.endDate) {
                const logDate = new Date(timestamp);
                const startDate = this.startDate ? new Date(this.startDate) : null;
                const endDate = this.endDate ? new Date(this.endDate) : null;
                
                if (startDate) {
                    startDate.setHours(0, 0, 0, 0);
                    if (logDate < startDate) matchesDateRange = false;
                }
                if (endDate) {
                    endDate.setHours(23, 59, 59, 999);
                    if (logDate > endDate) matchesDateRange = false;
                }
            }
            
            const isVisible = matchesSearch && matchesActionType && matchesDateRange;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        this.hasVisibleLogs = visibleCount > 0;
    }
}">
    <!-- Mobile menu button -->
    <button @click="sidebarOpen = !sidebarOpen" 
            class="md:hidden fixed top-4 left-4 z-50 p-2 rounded-md bg-white shadow-md text-gray-600 hover:text-gray-900 focus:outline-none">
        <i class="fas fa-bars text-xl"></i>
    </button>

    <div class="min-h-screen flex">
        <!-- Include sidebar -->
        <?php include __DIR__ . '/../components/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="flex-1 min-h-screen">
            <div class="content-wrapper">
                <div class="fixed-header">
                    <!-- Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">My Activity Logs</h1>
                        <p class="text-gray-600 mt-1">View your transaction history and system activities</p>
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
                </div>

                <div class="scrollable-content">
                    <!-- Activity Logs Table -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <?php if (empty($logs)): ?>
                            <div class="p-6 text-center text-gray-500">
                                No activity logs found.
                            </div>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($logs as $log): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M d, Y h:i A', strtotime($log['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs rounded-full font-medium
                                                    <?php
                                                    $color = match(true) {
                                                        str_contains($log['action_type'], 'create_sale') => 'bg-green-100 text-green-800',
                                                        str_contains($log['action_type'], 'void_sale') => 'bg-red-100 text-red-800',
                                                        str_contains($log['action_type'], 'refund') => 'bg-yellow-100 text-yellow-800',
                                                        str_contains($log['action_type'], 'update_sale') => 'bg-blue-100 text-blue-800',
                                                        str_contains($log['action_type'], 'login') => 'bg-purple-100 text-purple-800',
                                                        str_contains($log['action_type'], 'logout') => 'bg-gray-100 text-gray-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
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
                                                    <div class="text-xs text-gray-500">
                                                        <?php if ($log['affected_table'] === 'sales'): ?>
                                                            Transaction ID: <?= htmlspecialchars($log['affected_id']) ?>
                                                        <?php else: ?>
                                                            <?= ucfirst($log['affected_table']) ?> ID: <?= htmlspecialchars($log['affected_id']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- No Results Message -->
                            <div x-show="!hasVisibleLogs" 
                                 x-cloak
                                 class="py-12 text-center bg-gray-50 rounded-lg border-t">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                    <i class="fas fa-history text-gray-400 text-2xl"></i>
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
                        <?php endif; ?>
                    </div>
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
                               :max="endDate || today"
                               class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="modalEndDate" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" 
                               id="modalEndDate" 
                               x-model="endDate"
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