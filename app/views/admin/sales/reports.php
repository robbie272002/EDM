<?php
require_once __DIR__ . '/../../../../app/config/database.php';
require_once __DIR__ . '/../../../../app/config/auth.php';

// Check if user is logged in
requireLogin();

// Get current date and first day of current month
$currentDate = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

// Get date range from request or default to current month
$dateRange = $_GET['date_range'] ?? 'month';
$startDate = $_GET['start_date'] ?? $firstDayOfMonth;
$endDate = $_GET['end_date'] ?? $lastDayOfMonth;

// Handle predefined date ranges
switch ($dateRange) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'quarter':
        $currentQuarter = ceil(date('n') / 3);
        $startDate = date('Y-m-d', strtotime(date('Y') . '-' . (($currentQuarter - 1) * 3 + 1) . '-01'));
        $endDate = date('Y-m-t', strtotime(date('Y') . '-' . ($currentQuarter * 3) . '-01'));
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
    case 'season':
        $currentMonth = date('n');
        if ($currentMonth >= 3 && $currentMonth <= 5) {
            // Spring
            $startDate = date('Y-03-01');
            $endDate = date('Y-05-31');
        } elseif ($currentMonth >= 6 && $currentMonth <= 8) {
            // Summer
            $startDate = date('Y-06-01');
            $endDate = date('Y-08-31');
        } elseif ($currentMonth >= 9 && $currentMonth <= 11) {
            // Fall
            $startDate = date('Y-09-01');
            $endDate = date('Y-11-30');
        } else {
            // Winter
            $startDate = date('Y-12-01');
            $endDate = date('Y-02-28');
        }
        break;
    case 'custom':
        // Use the provided start_date and end_date
        break;
}

// Get report type
$reportType = $_GET['report_type'] ?? 'sales';

// Get data based on report type
switch ($reportType) {
    case 'products':
        $query = "SELECT 
                    p.name as product_name,
                    c.name as category_name,
                    COUNT(DISTINCT s.id) as total_sales,
                    SUM(si.quantity) as total_quantity,
                    SUM(si.quantity * si.price) as total_subtotal,
                    AVG(si.price) as avg_sale_price
                  FROM sales s
                  JOIN sale_items si ON s.id = si.sale_id
                  JOIN products p ON si.product_id = p.id
                  JOIN categories c ON p.category_id = c.id
                  WHERE s.created_at BETWEEN ? AND ?
                  GROUP BY p.id, p.name, c.name
                  ORDER BY total_subtotal DESC";
        break;
    default: // sales
        $query = "SELECT 
                    DATE(s.created_at) as sale_date,
                    COUNT(DISTINCT s.id) as total_transactions,
                    SUM(s.subtotal) as total_subtotal,
                    SUM(s.discount_amount) as total_discount,
                    SUM(s.total_amount) as total_amount,
                    COUNT(DISTINCT si.product_id) as unique_products
                  FROM sales s 
                  LEFT JOIN sale_items si ON s.id = si.sale_id
                  WHERE s.created_at BETWEEN ? AND ?
                  GROUP BY DATE(s.created_at)
                  ORDER BY sale_date ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$startDate, $endDate]);
$reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals based on report type
switch ($reportType) {
    case 'products':
        $query = "SELECT 
                    COALESCE(SUM(s.subtotal), 0) as total_revenue,
                    COALESCE(SUM(s.discount_amount), 0) as total_discount
                  FROM sales s 
                  WHERE s.created_at BETWEEN ? AND ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRevenue = $totals['total_revenue'];
        $totalDiscount = $totals['total_discount'];
        $totalSubtotal = $totalRevenue;

        $totalTransactions = array_sum(array_column($reportData, 'total_sales'));
        $totalProducts = count($reportData);
        $totalQuantity = array_sum(array_column($reportData, 'total_quantity'));
        break;
    default: // sales
        $query = "SELECT 
                    COALESCE(SUM(s.subtotal), 0) as total_revenue,
                    COALESCE(SUM(s.discount_amount), 0) as total_discount,
                    COUNT(DISTINCT s.id) as total_transactions
                  FROM sales s 
                  WHERE s.created_at BETWEEN ? AND ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRevenue = $totals['total_revenue'];
        $totalDiscount = $totals['total_discount'];
        $totalSubtotal = $totalRevenue;
        $totalTransactions = $totals['total_transactions'];
        $totalProducts = array_sum(array_column($reportData, 'unique_products'));
        $totalQuantity = 0; // Not applicable for sales report
}

// Get previous period data for comparison
$prevStartDate = date('Y-m-d', strtotime($startDate . ' -1 month'));
$prevEndDate = date('Y-m-d', strtotime($endDate . ' -1 month'));

$query = "SELECT COALESCE(SUM(subtotal), 0) as total_revenue FROM sales WHERE created_at BETWEEN ? AND ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$prevStartDate, $prevEndDate]);
$prevTotalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

// Calculate growth
$growth = $prevTotalRevenue > 0 ? (($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100 : 0;

// Add predictive analytics calculations
function calculateMovingAverage($data, $period = 3) {
    $result = [];
    for ($i = 0; $i < count($data); $i++) {
        if ($i < $period - 1) {
            $result[] = null;
            continue;
        }
        $sum = 0;
        for ($j = 0; $j < $period; $j++) {
            $sum += $data[$i - $j];
        }
        $result[] = $sum / $period;
    }
    return $result;
}

// Calculate forecast for next 3 periods
function calculateForecast($data, $periods = 3) {
    $n = count($data);
    if ($n < 2) return array_fill(0, $periods, 0);
    
    // Calculate trend
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $sumX += $i;
        $sumY += $data[$i];
        $sumXY += $i * $data[$i];
        $sumX2 += $i * $i;
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    
    // Generate forecast
    $forecast = [];
    for ($i = 0; $i < $periods; $i++) {
        $forecast[] = $slope * ($n + $i) + $intercept;
    }
    
    return $forecast;
}

// Prepare data for charts
$chartData = [];
$forecastData = [];

if ($reportType === 'sales') {
    // Get daily sales data for charts with all dates in range
    $dailyQuery = "
        WITH RECURSIVE dates AS (
            SELECT ? as date
            UNION ALL
            SELECT date + INTERVAL 1 DAY
            FROM dates
            WHERE date < ?
        )
        SELECT 
            DATE(d.date) as sale_date,
            COALESCE(COUNT(DISTINCT s.id), 0) as total_transactions,
            COALESCE(SUM(s.subtotal), 0) as total_subtotal,
            COALESCE(SUM(s.total_amount), 0) as total_amount
        FROM dates d
        LEFT JOIN sales s ON DATE(s.created_at) = d.date
        GROUP BY d.date
        ORDER BY d.date ASC";
    
    $dailyStmt = $pdo->prepare($dailyQuery);
    $dailyStmt->execute([$startDate, $endDate]);
    $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartLabels = [];
    $revenueData = [];
    $transactionData = [];

    foreach ($dailyData as $row) {
        $chartLabels[] = date('M d', strtotime($row['sale_date']));
        $revenueData[] = $row['total_subtotal'];
        $transactionData[] = $row['total_transactions'];
    }
    
    // Calculate moving averages
    $revenueMA = calculateMovingAverage($revenueData);
    $transactionMA = calculateMovingAverage($transactionData);
    
    // Calculate forecasts
    $revenueForecast = calculateForecast($revenueData);
    $transactionForecast = calculateForecast($transactionData);
    
    $chartData = [
        'dates' => $chartLabels,
        'revenues' => $revenueData,
        'transactions' => $transactionData,
        'revenueMA' => $revenueMA,
        'transactionMA' => $transactionMA,
        'revenueForecast' => $revenueForecast,
        'transactionForecast' => $transactionForecast
    ];
} elseif ($reportType === 'products') {
    // Sort products by subtotal in descending order
    usort($reportData, function($a, $b) {
        return $b['total_subtotal'] - $a['total_subtotal'];
    });
    
    // Take top 10 products
    $topProducts = array_slice($reportData, 0, 10);
    
    foreach ($topProducts as $row) {
        $chartLabels[] = $row['product_name'];
        $revenueData[] = $row['total_subtotal'];
        $transactionData[] = $row['total_sales'];
    }

    // For time series chart, get daily data for each product
    $dailyQuery = "SELECT 
        DATE(s.created_at) as sale_date,
        p.name as product_name,
        SUM(s.subtotal * (si.quantity * si.price) / s.subtotal) as product_subtotal
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id
        JOIN products p ON si.product_id = p.id
        WHERE s.created_at BETWEEN ? AND ?
        AND p.id IN (SELECT id FROM products WHERE name IN (" . implode(',', array_fill(0, count($topProducts), '?')) . "))
        GROUP BY DATE(s.created_at), p.name
        ORDER BY sale_date ASC, p.name";
    
    $params = array_merge([$startDate, $endDate], array_column($topProducts, 'product_name'));
    $dailyStmt = $pdo->prepare($dailyQuery);
    $dailyStmt->execute($params);
    $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize data by product
    $productData = [];
    $dates = [];
    foreach ($dailyData as $row) {
        if (!in_array($row['sale_date'], $dates)) {
            $dates[] = $row['sale_date'];
        }
        if (!isset($productData[$row['product_name']])) {
            $productData[$row['product_name']] = [];
        }
        $productData[$row['product_name']][$row['sale_date']] = $row['product_subtotal'];
    }

    // Fill in missing dates with 0
    foreach ($productData as $product => $data) {
        foreach ($dates as $date) {
            if (!isset($data[$date])) {
                $productData[$product][$date] = 0;
            }
        }
        ksort($productData[$product]);
    }

    $chartData = [
        'dates' => array_map(function($date) { return date('M d', strtotime($date)); }, $dates),
        'products' => $productData,
        'revenues' => $revenueData,
        'transactions' => $transactionData
    ];
}

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Admin Panel</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 2rem 1.5rem;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            margin-bottom: 2.5rem;
        }
        .summary-card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 4px 16px rgba(79,70,229,0.08);
            padding: 1.7rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-height: 120px;
            transition: box-shadow 0.2s;
            border: 1px solid #e5e7eb;
        }
        .summary-card:hover {
            box-shadow: 0 8px 32px rgba(79,70,229,0.13);
        }
        .summary-icon {
            font-size: 2rem;
            margin-bottom: 0.7rem;
            color: #6366f1;
            background: #eef2ff;
            border-radius: 50%;
            padding: 0.7rem;
        }
        .summary-label {
            font-size: 1.05rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: #18181b;
        }
        .summary-growth {
            font-size: 1rem;
            margin-top: 0.7rem;
        }
        .filter-bar {
            background: #f3f4f6;
            border-radius: 0.9rem;
            padding: 1.5rem 1.2rem;
            margin-bottom: 2.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            align-items: center;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 180px;
        }
        .filter-label {
            font-size: 1.05rem;
            color: #4b5563;
            margin-bottom: 0.25rem;
        }
        .filter-select, .filter-date {
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 0.6rem;
            padding: 0.6rem 1rem;
            font-size: 1.05rem;
            color: #374151;
        }
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            margin-bottom: 2.5rem;
        }
        .chart-card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 4px 16px rgba(79,70,229,0.08);
            padding: 1.7rem 1.5rem;
            min-height: 370px;
            display: flex;
            flex-direction: column;
        }
        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #18181b;
            margin-bottom: 1.2rem;
            letter-spacing: -0.5px;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3730a3;
            margin: 2.7rem 0 1.5rem 0;
            letter-spacing: -0.5px;
        }
        @media (max-width: 900px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 600px) {
            .main-content {
                padding: 1.2rem 0.3rem;
            }
            .filter-bar {
                flex-direction: column;
                gap: 1.2rem;
            }
        }
        #customDateModal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.3);
            align-items: center;
            justify-content: center;
        }
        #customDateModal .modal-content {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            padding: 2.5rem 2rem 2rem 2rem;
            min-width: 320px;
            max-width: 95vw;
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #customDateModal h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }
        #customDateModal .modal-fields {
            display: flex;
            gap: 1.5rem;
            width: 100%;
            margin-bottom: 1.5rem;
            justify-content: center;
        }
        #customDateModal .modal-fields > div {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        #customDateModal label {
            font-size: 1rem;
            color: #4b5563;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        #customDateModal input[type="date"] {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            color: #374151;
            background: #f9fafb;
        }
        #customDateModal .modal-actions {
            display: flex;
            gap: 1.5rem;
            justify-content: flex-end;
            width: 100%;
            margin-top: 1rem;
        }
        #customDateModal .btn-primary {
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        #customDateModal .btn-primary:hover {
            background: #4f46e5;
        }
        #customDateModal .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        #customDateModal .btn-secondary:hover {
            background: #e5e7eb;
        }
        #customDateModal .text-red-600 {
            color: #dc2626;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        @media (max-width: 600px) {
            #customDateModal .modal-content {
                padding: 1.5rem 0.5rem;
            }
            #customDateModal .modal-fields {
                flex-direction: column;
                gap: 1rem;
            }
        }
        .detailed-table-container {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 4px 16px rgba(79,70,229,0.08);
            overflow-x: auto;
            margin-bottom: 2.5rem;
        }
        .detailed-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 700px;
        }
        .detailed-table th {
            background: #f3f4f6;
            color: #4b5563;
            font-size: 1.05rem;
            font-weight: 600;
            padding: 1.1rem 1.3rem;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
        }
        .detailed-table td {
            padding: 0.95rem 1.3rem;
            font-size: 1.05rem;
            color: #18181b;
            border-bottom: 1px solid #f3f4f6;
            background: #fff;
        }
        .detailed-table tr:last-child td {
            border-bottom: none;
        }
        .detailed-table tbody tr:hover {
            background: #f1f5f9;
            transition: background 0.2s;
        }
        @media (max-width: 900px) {
            .detailed-table {
                min-width: 500px;
                font-size: 1rem;
            }
            .detailed-table th, .detailed-table td {
                padding: 0.8rem 0.8rem;
            }
        }
        @media (max-width: 600px) {
            .detailed-table {
                min-width: 400px;
                font-size: 0.97rem;
            }
            .detailed-table th, .detailed-table td {
                padding: 0.6rem 0.6rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg">
            <?php include '../shared/sidebar.php'; ?>
        </div>
        <!-- Main Content -->
        <div class="flex-1 ml-64 overflow-auto">
            <div class="main-content">
                        <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Sales Reports</h1>
                        <p class="text-gray-600">Analyze your sales performance and trends</p>
                    </div>
                        <div class="flex space-x-4">
                                <button onclick="exportToPDF()" class="btn-primary">
                                    <i class="fas fa-file-pdf mr-2"></i>
                                    Export PDF
                                </button>
                                <button onclick="exportToCSV()" class="btn-primary">
                                    <i class="fas fa-file-csv mr-2"></i>
                                    Export CSV
                                </button>
                            </div>
                        </div>
                        <!-- Filters -->
                <form method="GET" id="reportForm" class="filter-bar">
                    <div class="filter-group">
                        <label class="filter-label">Report Type</label>
                        <select name="report_type" onchange="this.form.submit()" class="filter-select">
                            <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>Product Performance</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date Range</label>
                        <select name="date_range" id="dateRange" onchange="handleDateRangeChange(this)" class="filter-select">
                            <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="quarter" <?php echo $dateRange === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="season" <?php echo $dateRange === 'season' ? 'selected' : ''; ?>>This Season</option>
                            <option value="year" <?php echo $dateRange === 'year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    <!-- Custom Date Range Modal Triggered by JS -->
                    <div style="flex:1;"></div>
                    <button type="button" class="btn-secondary" onclick="window.location.href=window.location.pathname">Clear Filter</button>
                </form>
                <!-- Custom Date Range Modal -->
                <div id="customDateModal">
                    <div class="modal-content">
                        <h2>Custom Date Range</h2>
                        <form id="customDateForm" style="width:100%;">
                            <div class="modal-fields">
                                    <div>
                                    <label for="modalStartDate">Start Date</label>
                                    <input type="date" name="start_date" id="modalStartDate" 
                                           class="filter-date" 
                                           onkeydown="return false"
                                           max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div>
                                    <label for="modalEndDate">End Date</label>
                                    <input type="date" name="end_date" id="modalEndDate" 
                                           class="filter-date"
                                           onkeydown="return false"
                                           max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            <div id="customDateError" class="text-sm text-red-600 mt-2" style="display:none;"></div>
                            <div class="modal-actions">
                                <button type="button" id="cancelCustomDate" class="btn-secondary">Cancel</button>
                                <button type="submit" class="btn-primary">Apply</button>
                            </div>
                            </form>
                        </div>
                                    </div>
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500">Total Sales</h3>
                                <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalSubtotal, 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-tag text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500">Total Discount</h3>
                                <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalDiscount, 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                                <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($totalRevenue, 2); ?></p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-sm <?php echo $growth >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <i class="fas fa-<?php echo $growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs(round($growth, 1)); ?>% vs last period
                            </span>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-shopping-cart text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500">Total Transactions</h3>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($totalTransactions); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Charts Section -->
                <div class="charts-row">
                    <div class="chart-card">
                        <div class="chart-title">Revenue Trend</div>
                        <div style="flex:1;min-height:220px;"><canvas id="revenueChart"></canvas></div>
                            </div>
                    <div class="chart-card">
                        <div class="chart-title">Transaction Volume</div>
                        <div style="flex:1;min-height:220px;"><canvas id="transactionsChart"></canvas></div>
                                    </div>
                                    </div>
                <div class="section-title">Descriptive Analytics</div>
                <div class="charts-row">
                    <div class="chart-card">
                        <div class="chart-title">Sales Trend Analysis</div>
                        <div style="flex:1;min-height:220px;"><canvas id="timeSeriesChart"></canvas></div>
                                </div>
                    <div class="chart-card">
                        <div class="chart-title"><?php echo $reportType === 'sales' ? 'Daily Sales Distribution' : 'Product Performance'; ?></div>
                        <div style="flex:1;min-height:220px;"><canvas id="distributionChart"></canvas></div>
                            </div>
                                    </div>
                        <?php if ($reportType === 'sales'): ?>
                <div class="section-title">Predictive Analytics</div>
                <div class="charts-row">
                    <div class="chart-card">
                        <div class="chart-title">Sales Forecast</div>
                        <div style="flex:1;min-height:220px;"><canvas id="forecastChart"></canvas></div>
                                </div>
                    <div class="chart-card">
                        <div class="chart-title">Moving Average Analysis</div>
                        <div style="flex:1;min-height:220px;"><canvas id="movingAverageChart"></canvas></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- Detailed Data Table -->
                <div class="section-title">Detailed Data</div>
                <div class="detailed-table-container">
                    <div class="p-0">
                    <div class="overflow-x-auto">
                            <table class="detailed-table">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transaction ID</th>
                                    <th>Subtotal</th>
                                    <th>Discount</th>
                                    <th>Tax</th>
                                    <th>Total Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                                <tbody>
                                    <?php
                                    // Get detailed sales data
                                    $detailedQuery = "SELECT 
                                        DATE(created_at) as sale_date,
                                        transaction_id,
                                        subtotal,
                                        discount_amount,
                                        tax_amount,
                                        total_amount,
                                        payment_method,
                                        status
                                        FROM sales 
                                        WHERE created_at BETWEEN ? AND ?
                                        ORDER BY created_at DESC";
                                    
                                    $detailedStmt = $pdo->prepare($detailedQuery);
                                    $detailedStmt->execute([$startDate, $endDate]);
                                    $detailedData = $detailedStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($detailedData as $row): 
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['sale_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['transaction_id']); ?></td>
                                        <td>$<?php echo number_format($row['subtotal'], 2); ?></td>
                                        <td>$<?php echo number_format($row['discount_amount'], 2); ?></td>
                                        <td>$<?php echo number_format($row['tax_amount'], 2); ?></td>
                                        <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($row['payment_method'])); ?></td>
                                        <td>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                <?php echo $row['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                    ($row['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                    'bg-red-100 text-red-800'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                            </span>
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
    </div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date inputs with current date range if not set
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const todayStr = today.toISOString().split('T')[0];
            
            const modalStartDate = document.getElementById('modalStartDate');
            const modalEndDate = document.getElementById('modalEndDate');
            
            // Set max date for both inputs to today
            modalStartDate.setAttribute('max', todayStr);
            modalEndDate.setAttribute('max', todayStr);
            
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

            // Prepare chart data based on report type
            <?php
            $chartLabels = [];
            $revenueData = [];
            $transactionData = [];

            if ($reportType === 'sales') {
                // Get daily sales data for charts with all dates in range
                $dailyQuery = "
                    WITH RECURSIVE dates AS (
                        SELECT ? as date
                        UNION ALL
                        SELECT date + INTERVAL 1 DAY
                        FROM dates
                        WHERE date < ?
                    )
                    SELECT 
                        DATE(d.date) as sale_date,
                        COALESCE(COUNT(DISTINCT s.id), 0) as total_transactions,
                        COALESCE(SUM(s.subtotal), 0) as total_subtotal,
                        COALESCE(SUM(s.total_amount), 0) as total_amount
                    FROM dates d
                    LEFT JOIN sales s ON DATE(s.created_at) = d.date
                    GROUP BY d.date
                    ORDER BY d.date ASC";
                
                $dailyStmt = $pdo->prepare($dailyQuery);
                $dailyStmt->execute([$startDate, $endDate]);
                $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($dailyData as $row) {
                    $chartLabels[] = date('M d', strtotime($row['sale_date']));
                    $revenueData[] = $row['total_subtotal'];
                    $transactionData[] = $row['total_transactions'];
                }
            } elseif ($reportType === 'products') {
                // Sort products by subtotal in descending order
                usort($reportData, function($a, $b) {
                    return $b['total_subtotal'] - $a['total_subtotal'];
                });
                
                // Take top 10 products
                $topProducts = array_slice($reportData, 0, 10);
                
                foreach ($topProducts as $row) {
                    $chartLabels[] = $row['product_name'];
                    $revenueData[] = $row['total_subtotal'];
                    $transactionData[] = $row['total_sales'];
                }

                // For time series chart, get daily data for each product
                $dailyQuery = "SELECT 
                    DATE(s.created_at) as sale_date,
                    p.name as product_name,
                    SUM(s.subtotal * (si.quantity * si.price) / s.subtotal) as product_subtotal
                    FROM sales s
                    JOIN sale_items si ON s.id = si.sale_id
                    JOIN products p ON si.product_id = p.id
                    WHERE s.created_at BETWEEN ? AND ?
                    AND p.id IN (SELECT id FROM products WHERE name IN (" . implode(',', array_fill(0, count($topProducts), '?')) . "))
                    GROUP BY DATE(s.created_at), p.name
                    ORDER BY sale_date ASC, p.name";
                
                $params = array_merge([$startDate, $endDate], array_column($topProducts, 'product_name'));
                $dailyStmt = $pdo->prepare($dailyQuery);
                $dailyStmt->execute($params);
                $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            ?>

            // Time Series Chart (Sales Trend)
            const timeSeriesCtx = document.getElementById('timeSeriesChart').getContext('2d');
            new Chart(timeSeriesCtx, {
                type: '<?php echo $reportType === 'products' ? 'bar' : 'line'; ?>',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: <?php 
                        if ($reportType === 'products') {
                            $colors = [
                                '#4f46e5',   // Indigo
                                '#10b981',   // Green
                                '#f59e0b',   // Yellow
                                '#ef4444',   // Red
                                '#3b82f6',   // Blue
                                '#8b5cf6',   // Purple
                                '#ec4899',   // Pink
                                '#14b8a6',   // Teal
                                '#f97316',   // Orange
                                '#06b6d4'    // Cyan
                            ];
                            $barColors = [];
                            $i = 0;
                            foreach ($chartLabels as $label) {
                                $barColors[] = $colors[$i % count($colors)];
                                $i++;
                            }
                            echo json_encode([
                                [
                                    'label' => 'Product Sales',
                                    'data' => array_values($revenueData),
                                    'backgroundColor' => $barColors,
                                    'borderColor' => $barColors,
                                    'borderWidth' => 2,
                                    'barPercentage' => 0.7,
                                    'categoryPercentage' => 0.7
                                ]
                            ]);
                        } else {
                            echo json_encode([[
                                'label' => $reportType === 'sales' ? 'Sales Trend (Subtotal)' : 'Sales Trend',
                                'data' => array_column($dailyData, 'total_subtotal'),
                                'borderColor' => '#4f46e5',
                                'backgroundColor' => '#4f46e5',
                                'tension' => 0.4,
                                'fill' => false,
                                'borderWidth' => 2,
                                'pointRadius' => 6,
                                'pointHoverRadius' => 8,
                                'pointBackgroundColor' => '#4f46e5',
                                'pointBorderColor' => '#fff',
                                'spanGaps' => true
                            ]]);
                        }
                    ?>
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: <?php echo $reportType === 'products' ? 'false' : 'true'; ?>,
                            position: 'top',
                            align: 'center',
                            labels: {
                                boxWidth: 18,
                                boxHeight: 18,
                                padding: 20,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 13,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 12
                            },
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 13
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.07)'
                            },
                            ticks: {
                                font: {
                                    size: 13
                                },
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: '<?php echo $reportType === 'sales' ? 'line' : 'bar'; ?>',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: '<?php echo $reportType === 'sales' ? 'Sales (Subtotal)' : 'Sales'; ?>',
                        data: <?php echo json_encode($revenueData); ?>,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Transactions Chart
            const transactionsCtx = document.getElementById('transactionsChart').getContext('2d');
            new Chart(transactionsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: '<?php echo $reportType === 'sales' ? 'Transactions' : 'Sales'; ?>',
                        data: <?php echo json_encode($transactionData); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Distribution Chart
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            new Chart(distributionCtx, {
                type: '<?php echo $reportType === 'products' ? 'pie' : 'bar'; ?>',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [
                        {
                            label: '<?php echo $reportType === 'sales' ? 'Daily Sales (Subtotal)' : 'Product Sales (Subtotal)'; ?>',
                            data: <?php echo json_encode($revenueData); ?>,
                            backgroundColor: [
                                '#4f46e5',   // Indigo
                                '#10b981',   // Green
                                '#f59e0b',   // Yellow
                                '#ef4444',   // Red
                                '#3b82f6',   // Blue
                                '#8b5cf6',   // Purple
                                '#ec4899',   // Pink
                                '#14b8a6',   // Teal
                                '#f97316',   // Orange
                                '#06b6d4'    // Cyan
                            ],
                            borderColor: '#fff',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'center',
                            labels: {
                                boxWidth: 18,
                                boxHeight: 18,
                                padding: 20,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += new Intl.NumberFormat('en-US', {
                                            style: 'currency',
                                            currency: 'USD'
                                        }).format(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });

            <?php if ($reportType === 'sales'): ?>
            // Forecast Chart
            const forecastCtx = document.getElementById('forecastChart').getContext('2d');
            new Chart(forecastCtx, {
                type: 'line',
                data: {
                    labels: [...<?php echo json_encode($chartLabels); ?>, 'Forecast 1', 'Forecast 2', 'Forecast 3'],
                    datasets: [{
                        label: 'Actual Sales (Subtotal)',
                        data: [...<?php echo json_encode(array_column($dailyData, 'total_subtotal')); ?>, null, null, null],
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Sales Forecast',
                        data: [...Array(<?php echo count($chartLabels); ?>).fill(null), ...<?php echo json_encode(calculateForecast(array_column($dailyData, 'total_subtotal'))); ?>],
                        borderColor: '#ef4444',
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Moving Average Chart
            const movingAverageCtx = document.getElementById('movingAverageChart').getContext('2d');
            new Chart(movingAverageCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Actual Sales (Subtotal)',
                        data: <?php echo json_encode(array_column($dailyData, 'total_subtotal')); ?>,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: '3-Period Moving Average',
                        data: <?php echo json_encode(calculateMovingAverage(array_column($dailyData, 'total_subtotal'))); ?>,
                        borderColor: '#10b981',
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });

        function showDateError(message) {
            const errorDiv = document.getElementById('customDateError');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }

        function hideDateError() {
            const errorDiv = document.getElementById('customDateError');
            errorDiv.style.display = 'none';
        }

        // Date range selection handler
        function handleDateRangeChange(select) {
            if (select.value === 'custom') {
                showCustomDateModal();
            } else {
                document.getElementById('customDateModal').style.display = 'none';
                select.form.submit();
            }
        }

        function showCustomDateModal() {
            const modal = document.getElementById('customDateModal');
            const modalStartDate = document.getElementById('modalStartDate');
            const modalEndDate = document.getElementById('modalEndDate');
            
            // Get today's date for validation
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const todayStr = today.toISOString().split('T')[0];
            
            // Set modal values and constraints
            modalStartDate.value = "<?php echo $startDate; ?>";
            modalEndDate.value = "<?php echo $endDate; ?>";
            
            // Set constraints
            modalStartDate.max = todayStr;
            modalEndDate.max = todayStr;
            modalEndDate.min = modalStartDate.value;
            
            modal.style.display = 'flex';
            hideDateError();
        }

        // Modal logic
        const modal = document.getElementById('customDateModal');
        document.getElementById('cancelCustomDate').onclick = function() {
            modal.style.display = 'none';
            // Reset dropdown to a default value
            document.getElementById('dateRange').value = 'month';
            hideDateError();
        };

        document.getElementById('customDateForm').onsubmit = function(e) {
            e.preventDefault();
            
            const start = document.getElementById('modalStartDate').value;
            const end = document.getElementById('modalEndDate').value;
            
            // Validate dates
            if (!start || !end) {
                showDateError('Both start and end dates are required');
                return;
            }

            const startDate = new Date(start);
            const endDate = new Date(end);
            const today = new Date();
            
            // Reset time to midnight for accurate comparison
            startDate.setHours(0, 0, 0, 0);
            endDate.setHours(0, 0, 0, 0);
            today.setHours(0, 0, 0, 0);

            // Strict validation
            if (startDate > today) {
                showDateError('Start date cannot be in the future');
                return;
            }

            if (endDate > today) {
                showDateError('End date cannot be in the future');
                return;
            }

            if (startDate > endDate) {
                showDateError('Start date cannot be after end date');
                return;
            }

            // Set values in main form and submit
            const mainForm = document.getElementById('reportForm');
            let startInput = mainForm.querySelector('input[name="start_date"]');
            let endInput = mainForm.querySelector('input[name="end_date"]');
            
            if (!startInput) {
                startInput = document.createElement('input');
                startInput.type = 'hidden';
                startInput.name = 'start_date';
                mainForm.appendChild(startInput);
            }
            
            if (!endInput) {
                endInput = document.createElement('input');
                endInput.type = 'hidden';
                endInput.name = 'end_date';
                mainForm.appendChild(endInput);
            }
            
            startInput.value = start;
            endInput.value = end;
            modal.style.display = 'none';
            mainForm.submit();
        };

        // Export functions
        function exportToPDF() {
            window.location.href = 'get_sale_details.php?export=pdf&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&report_type=<?php echo $reportType; ?>&date_range=<?php echo $dateRange; ?>';
        }

        function exportToCSV() {
            window.location.href = 'get_sale_details.php?export=csv&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&report_type=<?php echo $reportType; ?>&date_range=<?php echo $dateRange; ?>';
        }
    </script>
</body>
</html> 