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
    $_SESSION['error_message'] = "Error fetching activity logs";
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
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date inputs with current date range if not set
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = today.toISOString().split('T')[0];
    
    const lastMonth = new Date();
    lastMonth.setMonth(lastMonth.getMonth() - 1);
    const lastMonthStr = lastMonth.toISOString().split('T')[0];
    
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    const modalStartDate = document.getElementById('modalStartDate');
    const modalEndDate = document.getElementById('modalEndDate');
    
    // Set max date for both inputs to today
    modalStartDate.setAttribute('max', todayStr);
    modalEndDate.setAttribute('max', todayStr);
    
    // Prevent manual input of future dates
    modalStartDate.addEventListener('input', function(e) {
        const inputDate = new Date(this.value);
        inputDate.setHours(0, 0, 0, 0);
        
        if (inputDate > today) {
            this.value = todayStr;
            showDateError('Start date cannot be in the future');
        }
    });
    
    // Set default dates if not already set
    if (!dateFrom.value) {
        dateFrom.value = lastMonthStr;
        modalStartDate.value = lastMonthStr;
    } else {
        // Ensure existing date is not in future
        const existingDate = new Date(dateFrom.value);
        existingDate.setHours(0, 0, 0, 0);
        if (existingDate > today) {
            dateFrom.value = todayStr;
            modalStartDate.value = todayStr;
        }
    }
    
    if (!dateTo.value) {
        dateTo.value = todayStr;
        modalEndDate.value = todayStr;
    }
    
    // Add event listener for search input (debounced)
    let searchTimeout;
    const searchInput = document.querySelector('input[name="search"]');
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });

    // Add event listeners for date inputs
    modalStartDate.addEventListener('change', function() {
        const startDate = new Date(this.value);
        const endDate = new Date(modalEndDate.value);
        
        // Reset time to midnight for accurate comparison
        startDate.setHours(0, 0, 0, 0);
        
        // Strict validation for start date
        if (startDate > today) {
            this.value = todayStr;
            showDateError('Start date cannot be in the future');
            return;
        }
        
        // Update end date constraints
        if (endDate < startDate) {
            modalEndDate.value = this.value;
        }
        
        // Ensure end date is not before start date
        modalEndDate.min = this.value;
        hideDateError();
    });

    modalEndDate.addEventListener('change', function() {
        const startDate = new Date(modalStartDate.value);
        const endDate = new Date(this.value);
        
        // Reset time to midnight for accurate comparison
        endDate.setHours(0, 0, 0, 0);
        
        // Validate end date
        if (endDate > today) {
            this.value = todayStr;
            showDateError('End date cannot be in the future');
            return;
        }
        
        hideDateError();
    });
});

function showDateError(message) {
    const errorDiv = document.getElementById('dateError');
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');
}

function hideDateError() {
    const errorDiv = document.getElementById('dateError');
    errorDiv.classList.add('hidden');
}

function openDateModal() {
    const modal = document.getElementById('dateModal');
    const modalStartDate = document.getElementById('modalStartDate');
    const modalEndDate = document.getElementById('modalEndDate');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    // Get today's date for validation
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayStr = today.toISOString().split('T')[0];
    
    // Validate and set start date
    let startValue = dateFrom.value;
    const startDate = new Date(startValue);
    startDate.setHours(0, 0, 0, 0);
    
    if (startDate > today) {
        startValue = todayStr;
    }
    
    // Set modal values
    modalStartDate.value = startValue;
    modalEndDate.value = dateTo.value;
    
    // Set constraints
    modalStartDate.max = todayStr;
    modalEndDate.max = todayStr;
    modalEndDate.min = modalStartDate.value;
    
    modal.classList.add('show');
}

function closeDateModal() {
    document.getElementById('dateModal').classList.remove('show');
    document.getElementById('dateError').classList.add('hidden');
}

function applyDateRange() {
    const startDate = document.getElementById('modalStartDate').value;
    const endDate = document.getElementById('modalEndDate').value;
    
    // Validate dates
    if (!startDate || !endDate) {
        showDateError('Both start and end dates are required');
        return;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);
    const today = new Date();
    
    // Reset time to midnight for accurate comparison
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);

    // Strict validation
    if (start > today) {
        showDateError('Start date cannot be in the future');
        document.getElementById('modalStartDate').value = today.toISOString().split('T')[0];
        return;
    }

    if (end > today) {
        showDateError('End date cannot be in the future');
        document.getElementById('modalEndDate').value = today.toISOString().split('T')[0];
        return;
    }

    if (start > end) {
        showDateError('Start date cannot be after end date');
        return;
    }

    // Set values and submit form
    document.getElementById('date_from').value = startDate;
    document.getElementById('date_to').value = endDate;
    closeDateModal();
    document.getElementById('filterForm').submit();
}

// Reset filters function
function resetFilters() {
    document.querySelector('select[name="action_type"]').value = '';
    document.querySelector('input[name="date_from"]').value = '';
    document.querySelector('input[name="date_to"]').value = '';
    document.querySelector('input[name="search"]').value = '';
    document.getElementById('filterForm').submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('dateModal');
    if (event.target === modal) {
        closeDateModal();
    }
}
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
    filterLogs() {
        const searchLower = this.searchQuery.toLowerCase();
        const actionType = this.selectedActionType.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const timestamp = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
            const user = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const action = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const description = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const details = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
            
            // Search filter
            const matchesSearch = !searchLower || 
                timestamp.includes(searchLower) ||
                user.includes(searchLower) ||
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
                const logDate = new Date(timestamp.split(' ')[0]);
                const startDate = this.startDate ? new Date(this.startDate) : null;
                const endDate = this.endDate ? new Date(this.endDate) : null;
                
                // Set end date to end of day if it exists
                if (endDate) {
                    endDate.setHours(23, 59, 59, 999);
                }
                
                if (startDate && startDate > logDate) {
                    matchesDateRange = false;
                }
                if (endDate && endDate < logDate) {
                    matchesDateRange = false;
                }
            }
            
            row.style.display = matchesSearch && matchesActionType && matchesDateRange ? '' : 'none';
        });
    },
    applyDateRange() {
        // Validate dates
        if (this.startDate && this.endDate) {
            const start = new Date(this.startDate);
            const end = new Date(this.endDate);
            
            if (start > end) {
                this.dateError = 'Start date cannot be after end date';
                return;
            }
        }
        
        this.dateError = '';
        this.showDateModal = false;
        this.filterLogs();
    },
    resetDateRange() {
        this.startDate = '';
        this.endDate = '';
        this.dateError = '';
        this.filterLogs();
    },
    resetFilters() {
        this.searchQuery = '';
        this.selectedActionType = '';
        this.startDate = '';
        this.endDate = '';
        this.dateError = '';
        this.filterLogs();
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
                    <div class="p-6">
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
                                <div class="flex space-x-2">
                                    <button @click="showDateModal = true" 
                                            class="px-4 py-2 border rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <i class="fas fa-calendar mr-2"></i> Date Range
                                    </button>
                                    <button @click="resetDateRange()" 
                                            class="px-4 py-2 border rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            x-show="startDate || endDate">
                                        <i class="fas fa-times mr-2"></i> Clear Dates
                                    </button>
                                    <button @click="resetFilters()" 
                                            class="px-4 py-2 border rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            x-show="searchQuery || selectedActionType || startDate || endDate">
                                        <i class="fas fa-undo mr-2"></i> Reset All
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Active Filters -->
                        <div class="mt-4 flex flex-wrap gap-2" x-show="searchQuery || selectedActionType || startDate || endDate">
                            <div class="text-sm text-gray-600 flex flex-wrap gap-2">
                                <template x-if="searchQuery">
                                    <span class="bg-gray-100 px-2 py-1 rounded">
                                        <span class="font-medium">Search:</span>
                                        <span x-text="searchQuery"></span>
                                    </span>
                                </template>
                                <template x-if="selectedActionType">
                                    <span class="bg-gray-100 px-2 py-1 rounded">
                                        <span class="font-medium">Action:</span>
                                        <span x-text="selectedActionType.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')"></span>
                                    </span>
                                </template>
                                <template x-if="startDate || endDate">
                                    <span class="bg-gray-100 px-2 py-1 rounded">
                                        <span class="font-medium">Date Range:</span>
                                        <span x-text="startDate ? new Date(startDate).toLocaleDateString() : 'Any'"></span>
                                        <span>to</span>
                                        <span x-text="endDate ? new Date(endDate).toLocaleDateString() : 'Any'"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-r-lg shadow-sm" role="alert">
                        <p><?= htmlspecialchars($_SESSION['success_message']) ?></p>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-r-lg shadow-sm" role="alert">
                        <p><?= htmlspecialchars($_SESSION['error_message']) ?></p>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Logs Table -->
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
      <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <h2 class="text-xl font-semibold mb-6 text-gray-800">Custom Date Range</h2>
        <form @submit.prevent="applyDateRange()" class="w-full">
          <div class="flex gap-6 mb-4">
            <div class="flex-1">
              <label for="modalStartDate" class="block text-gray-700 font-medium mb-2">Start Date</label>
              <input type="date" id="modalStartDate" x-model="startDate"
                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                :max="endDate || today" onkeydown="return false">
            </div>
            <div class="flex-1">
              <label for="modalEndDate" class="block text-gray-700 font-medium mb-2">End Date</label>
              <input type="date" id="modalEndDate" x-model="endDate"
                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition"
                :min="startDate" :max="today" onkeydown="return false">
            </div>
          </div>
          <div x-show="dateError" class="text-sm text-red-600 mb-4" x-text="dateError"></div>
          <div class="flex justify-end gap-3">
            <button type="button" @click="showDateModal = false"
              class="px-5 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition btn-secondary">Cancel</button>
            <button type="submit"
              class="px-5 py-2 rounded-lg bg-indigo-500 text-white font-semibold hover:bg-indigo-600 transition btn-primary">Apply</button>
          </div>
        </form>
      </div>
    </div>
    <style>
    /* Fallback for non-Tailwind environments */
    #customDateModal .modal-content {
      background: #fff;
      border-radius: 1rem;
      box-shadow: 0 8px 32px rgba(0,0,0,0.15);
      padding: 2rem;
      width: 100%;
      max-width: 400px;
    }
    .modal-fields {
      display: flex; gap: 1.5rem; margin-bottom: 1rem;
    }
    .modal-fields label {
      display: block; color: #374151; font-weight: 500; margin-bottom: 0.5rem;
    }
    .filter-date {
      width: 100%; border-radius: 0.5rem; border: 1px solid #d1d5db;
      padding: 0.5rem 1rem; color: #374151;
    }
    .modal-actions {
      display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;
    }
    .btn-primary {
      background: #6366f1; color: #fff; font-weight: 600;
      border-radius: 0.5rem; padding: 0.5rem 1.25rem; border: none;
      transition: background 0.2s;
    }
    .btn-primary:hover { background: #4f46e5; }
    .btn-secondary {
      background: #f3f4f6; color: #374151; font-weight: 600;
      border-radius: 0.5rem; padding: 0.5rem 1.25rem; border: none;
      transition: background 0.2s;
    }
    .btn-secondary:hover { background: #e5e7eb; }
    </style>
</body>
</html> 