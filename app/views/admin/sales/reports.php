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
    case 'today':
        $startDate = date('Y-m-d 00:00:00');
        $endDate = date('Y-m-d 23:59:59');
        $prevStartDate = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $prevEndDate = date('Y-m-d 23:59:59', strtotime('-1 day'));
        break;
    case 'yesterday':
        $startDate = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $endDate = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $prevStartDate = date('Y-m-d 00:00:00', strtotime('-2 days'));
        $prevEndDate = date('Y-m-d 23:59:59', strtotime('-2 days'));
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('w') == 0 ? date('Y-m-d') : min(date('Y-m-d'), date('Y-m-d', strtotime('sunday this week')));
        $prevStartDate = date('Y-m-d', strtotime('monday last week'));
        $prevEndDate = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = min(date('Y-m-d'), date('Y-m-t'));
        $prevStartDate = date('Y-m-01', strtotime('first day of last month'));
        $prevEndDate = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'last_month':
        $startDate = date('Y-m-01', strtotime('first day of last month'));
        $endDate = date('Y-m-t', strtotime('last day of last month'));
        $prevStartDate = date('Y-m-01', strtotime('first day of -2 month'));
        $prevEndDate = date('Y-m-t', strtotime('last day of -2 month'));
        break;
    case 'quarter':
        $currentQuarter = ceil(date('n') / 3);
        $currentYear = date('Y');
        
        $startMonth = (($currentQuarter - 1) * 3) + 1;
        $endMonth = $currentQuarter * 3;
        
        $startDate = date('Y-m-d', strtotime("$currentYear-$startMonth-01"));
        $endDate = min(date('Y-m-d'), date('Y-m-t', strtotime("$currentYear-$endMonth-01")));
        
        if ($currentQuarter == 1) {
            $prevYear = $currentYear - 1;
            $prevStartDate = date('Y-m-d', strtotime("$prevYear-10-01"));
            $prevEndDate = date('Y-m-d', strtotime("$prevYear-12-31"));
        } else {
            $prevQuarterStart = $startMonth - 3;
            $prevQuarterEnd = $endMonth - 3;
            $prevStartDate = date('Y-m-d', strtotime("$currentYear-$prevQuarterStart-01"));
            $prevEndDate = date('Y-m-t', strtotime("$currentYear-$prevQuarterEnd-01"));
        }
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate = min(date('Y-m-d'), date('Y-12-31'));
        $prevStartDate = date('Y-01-01', strtotime('-1 year'));
        $prevEndDate = date('Y-12-31', strtotime('-1 year'));
        break;
    case 'last_year':
        $startDate = date('Y-01-01', strtotime('-1 year'));
        $endDate = date('Y-12-31', strtotime('-1 year'));
        $prevStartDate = date('Y-01-01', strtotime('-2 year'));
        $prevEndDate = date('Y-12-31', strtotime('-2 year'));
        break;
    case 'season':
        $currentMonth = date('n');
        if ($currentMonth >= 3 && $currentMonth <= 5) {
            // Spring
            $startDate = date('Y-03-01');
            $endDate = date('Y-05-31');
            $prevStartDate = date('Y-12-01', strtotime('-1 year'));
            $prevEndDate = date('Y-02-28', strtotime('-1 year'));
        } elseif ($currentMonth >= 6 && $currentMonth <= 8) {
            // Summer
            $startDate = date('Y-06-01');
            $endDate = date('Y-08-31');
            $prevStartDate = date('Y-03-01');
            $prevEndDate = date('Y-05-31');
        } elseif ($currentMonth >= 9 && $currentMonth <= 11) {
            // Fall
            $startDate = date('Y-09-01');
            $endDate = date('Y-11-30');
            $prevStartDate = date('Y-06-01');
            $prevEndDate = date('Y-08-31');
        } else {
            // Winter (December, January, February)
            $startDate = date('Y-12-01');
            $endDate = date('Y-02-28');
            $prevStartDate = date('Y-09-01', strtotime('-1 year'));
            $prevEndDate = date('Y-11-30', strtotime('-1 year'));
        }
        break;
    case 'custom':
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $startDate = date('Y-m-d', strtotime($_GET['start_date']));
            $endDate = date('Y-m-d', strtotime($_GET['end_date']));
            
            if ($endDate < $startDate) {
                $endDate = $startDate;
            }

            $duration = strtotime($endDate) - strtotime($startDate);
            $prevStartDate = date('Y-m-d', strtotime("-1 day", strtotime($startDate)) - $duration);
            $prevEndDate = date('Y-m-d', strtotime("-1 day", strtotime($startDate)));
        }
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
                    AVG(si.price) as avg_sale_price,
                    SUM(si.quantity * si.price) as total_revenue
                  FROM sales s
                  JOIN sale_items si ON s.id = si.sale_id
                  JOIN products p ON si.product_id = p.id
                  JOIN categories c ON p.category_id = c.id
                  WHERE s.created_at >= ? AND s.created_at <= ?
                  GROUP BY p.id, p.name, c.name
                  ORDER BY total_revenue DESC
                  LIMIT 10";
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
                  WHERE s.created_at >= ? AND s.created_at <= ?
                  GROUP BY DATE(s.created_at)
                  ORDER BY sale_date ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$startDate, $endDate]);
$reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get totals for the selected date range
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(subtotal), 0) as total_sales,
        COALESCE(SUM(discount_amount), 0) as total_discount,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(DISTINCT id) as total_transactions
    FROM sales 
    WHERE created_at >= ? AND created_at <= ?
");

$currentPeriodParams = [$startDate, $endDate];
$stmt->execute($currentPeriodParams);
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

// Get totals for previous period
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(subtotal), 0) as prev_total_sales,
        COALESCE(SUM(discount_amount), 0) as prev_total_discount,
        COALESCE(SUM(total_amount), 0) as prev_total_revenue,
        COUNT(DISTINCT id) as prev_total_transactions
    FROM sales 
    WHERE created_at >= ? AND created_at <= ?
");

$prevPeriodParams = [$prevStartDate, $prevEndDate];
$stmt->execute($prevPeriodParams);
$prevTotals = $stmt->fetch(PDO::FETCH_ASSOC);

// Add debug logging
error_log("Date Range: " . $dateRange);
error_log("Current Period: " . $startDate . " to " . $endDate);
error_log("Previous Period: " . $prevStartDate . " to " . $prevEndDate);
error_log("Current Totals: " . json_encode($totals));
error_log("Previous Totals: " . json_encode($prevTotals));

// Calculate growth percentages with proper handling of zero values
$salesGrowth = calculateGrowth($totals['total_sales'], $prevTotals['prev_total_sales']);
$discountGrowth = calculateGrowth($totals['total_discount'], $prevTotals['prev_total_discount']);
$revenueGrowth = calculateGrowth($totals['total_revenue'], $prevTotals['prev_total_revenue']);
$transactionGrowth = calculateGrowth($totals['total_transactions'], $prevTotals['prev_total_transactions']);

// Helper function to calculate growth percentage
function calculateGrowth($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return (($current - $previous) / $previous) * 100;
}

// Update the display text based on the selected period
$periodText = match($dateRange) {
    'today' => 'vs yesterday',
    'yesterday' => 'vs day before',
    'week' => 'vs last week',
    'month' => 'vs last month',
    'quarter' => 'vs last quarter',
    'year' => 'vs last year',
    'custom' => 'vs previous period',
    default => 'vs last period'
};

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
        LEFT JOIN sales s ON DATE(s.created_at) = d.date AND s.status = 'completed'
        GROUP BY d.date
        ORDER BY d.date ASC";
    
    $dailyStmt = $pdo->prepare($dailyQuery);
    $dailyStmt->execute([$startDate, $endDate]);
    $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dailyData as $row) {
        $chartLabels[] = date('M d', strtotime($row['sale_date']));
        $revenueData[] = floatval($row['total_subtotal']);
        $transactionData[] = intval($row['total_transactions']);
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
    // Get product performance data
    $productQuery = "
        SELECT 
            p.name as product_name,
            COUNT(DISTINCT s.id) as total_sales,
            SUM(si.quantity) as total_quantity,
            SUM(si.quantity * si.price) as total_revenue,
            AVG(si.price) as avg_price
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id
        JOIN products p ON si.product_id = p.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        AND s.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY total_revenue DESC
        LIMIT 10";
    
    $productStmt = $pdo->prepare($productQuery);
    $productStmt->execute([$startDate, $endDate]);
    $productData = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($productData as $row) {
        $chartLabels[] = $row['product_name'];
        $revenueData[] = floatval($row['total_revenue']);
        $transactionData[] = intval($row['total_sales']);
    }

    // Get daily product sales for time series only if we have products
    $dailyProductData = [];
    if (!empty($chartLabels)) {
        $placeholders = str_repeat('?,', count($chartLabels) - 1) . '?';
        $dailyProductQuery = "
            SELECT 
        DATE(s.created_at) as sale_date,
        p.name as product_name,
                SUM(si.quantity * si.price) as daily_revenue
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id
        JOIN products p ON si.product_id = p.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            AND s.status = 'completed'
            AND p.name IN ($placeholders)
        GROUP BY DATE(s.created_at), p.name
            ORDER BY sale_date ASC, daily_revenue DESC";
    
        $params = array_merge([$startDate, $endDate], $chartLabels);
        $dailyProductStmt = $pdo->prepare($dailyProductQuery);
        $dailyProductStmt->execute($params);
        $dailyProductData = $dailyProductStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add debug information
    error_log("Chart Type: " . $reportType);
    error_log("Labels: " . json_encode($chartLabels));
    error_log("Revenue Data: " . json_encode($revenueData));
    error_log("Transaction Data: " . json_encode($transactionData));

    $chartData = [
        'labels' => $chartLabels,
        'revenues' => $revenueData,
        'transactions' => $transactionData,
        'dailyData' => $dailyProductData ?? []
    ];
}

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
    
    // Debug information
    $debug = [
        'date_range' => $dateRange,
        'current_period' => [
            'start' => $startDate,
            'end' => $endDate,
            'totals' => $totals
        ],
        'previous_period' => [
            'start' => $prevStartDate,
            'end' => $prevEndDate,
            'totals' => $prevTotals
        ],
        'growth' => [
            'sales' => $salesGrowth,
            'discount' => $discountGrowth,
            'revenue' => $revenueGrowth,
            'transactions' => $transactionGrowth
        ]
    ];

    // Return JSON response with debug info
    echo json_encode([
        'success' => true,
        'debug' => $debug,
        'totals' => [
            'total_sales' => floatval($totals['total_sales']),
            'total_discount' => floatval($totals['total_discount']),
            'total_revenue' => floatval($totals['total_revenue']),
            'total_transactions' => intval($totals['total_transactions'])
        ],
        'growth' => [
            'sales' => floatval($salesGrowth),
            'discount' => floatval($discountGrowth),
            'revenue' => floatval($revenueGrowth),
            'transactions' => floatval($transactionGrowth)
        ],
        'periodText' => $periodText,
        'dateRange' => [
            'start' => $startDate,
            'end' => $endDate,
            'prev_start' => $prevStartDate,
            'prev_end' => $prevEndDate
        ],
        'chartData' => $chartData
    ]);
    exit;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
<body class="bg-gray-50" x-data="{ 
    showDateModal: false,
    dateError: '',
    hasVisibleData: true,
    
    init() {
        this.$nextTick(() => {
            this.checkVisibleData();
        });
    },

    checkVisibleData() {
        const rows = document.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                visibleCount++;
            }
        });

        this.hasVisibleData = visibleCount > 0;
    }
}">
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
                        <select name="report_type" onchange="updateDashboard({report_type: this.value})" class="filter-select">
                            <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="products" <?php echo $reportType === 'products' ? 'selected' : ''; ?>>Product Performance</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date Range</label>
                        <select name="date_range" id="dateRange" onchange="handleDateRangeChange(this)" class="filter-select">
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $dateRange === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="quarter" <?php echo $dateRange === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="year" <?php echo $dateRange === 'year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="last_year" <?php echo $dateRange === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="season" <?php echo $dateRange === 'season' ? 'selected' : ''; ?>>Season</option>
                            <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    <input type="hidden" name="start_date" id="hiddenStartDate" value="<?php echo htmlspecialchars($startDate); ?>">
                    <input type="hidden" name="end_date" id="hiddenEndDate" value="<?php echo htmlspecialchars($endDate); ?>">
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
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="flex items-center justify-between w-full mb-4">
                            <div class="icon text-blue-500 bg-blue-100 rounded-full p-3">
                                <i class="fas fa-shopping-cart text-xl"></i>
                            </div>
                                </div>
                        <h3 class="text-gray-500 text-sm font-medium mb-2">Total Sales</h3>
                        <div class="text-2xl font-bold mb-1 total-sales">₱<?= number_format($totals['total_sales'], 2) ?></div>
                        <div class="text-sm sales-growth">
                            <i class="fas fa-arrow-<?= $salesGrowth >= 0 ? 'up text-green-500' : 'down text-red-500' ?> mr-1"></i>
                            <span class="<?= $salesGrowth >= 0 ? 'text-green-500' : 'text-red-500' ?>"><?= abs(round($salesGrowth, 1)) ?>% <?= $periodText ?></span>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="flex items-center justify-between w-full mb-4">
                            <div class="icon text-yellow-500 bg-yellow-100 rounded-full p-3">
                                <i class="fas fa-tag text-xl"></i>
                            </div>
                                </div>
                        <h3 class="text-gray-500 text-sm font-medium mb-2">Total Discount</h3>
                        <div class="text-2xl font-bold mb-1 total-discount">₱<?= number_format($totals['total_discount'], 2) ?></div>
                        <div class="text-sm discount-growth">
                            <i class="fas fa-arrow-<?= $discountGrowth >= 0 ? 'up text-green-500' : 'down text-red-500' ?> mr-1"></i>
                            <span class="<?= $discountGrowth >= 0 ? 'text-green-500' : 'text-red-500' ?>"><?= abs(round($discountGrowth, 1)) ?>% <?= $periodText ?></span>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="flex items-center justify-between w-full mb-4">
                            <div class="icon text-green-500 bg-green-100 rounded-full p-3">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                                </div>
                        <h3 class="text-gray-500 text-sm font-medium mb-2">Total Revenue</h3>
                        <div class="text-2xl font-bold mb-1 total-revenue">₱<?= number_format($totals['total_revenue'], 2) ?></div>
                        <div class="text-sm revenue-growth">
                            <i class="fas fa-arrow-<?= $revenueGrowth >= 0 ? 'up text-green-500' : 'down text-red-500' ?> mr-1"></i>
                            <span class="<?= $revenueGrowth >= 0 ? 'text-green-500' : 'text-red-500' ?>"><?= abs(round($revenueGrowth, 1)) ?>% <?= $periodText ?></span>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="flex items-center justify-between w-full mb-4">
                            <div class="icon text-purple-500 bg-purple-100 rounded-full p-3">
                                <i class="fas fa-receipt text-xl"></i>
                            </div>
                                </div>
                        <h3 class="text-gray-500 text-sm font-medium mb-2">Total Transactions</h3>
                        <div class="text-2xl font-bold mb-1 total-transactions"><?= number_format($totals['total_transactions']) ?></div>
                        <div class="text-sm transactions-growth">
                            <i class="fas fa-arrow-<?= $transactionGrowth >= 0 ? 'up text-green-500' : 'down text-red-500' ?> mr-1"></i>
                            <span class="<?= $transactionGrowth >= 0 ? 'text-green-500' : 'text-red-500' ?>"><?= abs(round($transactionGrowth, 1)) ?>% <?= $periodText ?></span>
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
                        <div class="chart-title"><?php echo $reportType === 'products' ? 'Product Performance' : 'Sales Trend Analysis'; ?></div>
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
                    <div class="overflow-x-auto">
                            <table class="detailed-table">
                                <thead>
                                <tr>
                                        <th>Date & Time</th>
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
                                    <?php if ($reportType === 'products'): ?>
                                        <?php if (empty($reportData)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-12">
                                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                                        <i class="fas fa-box text-gray-400 text-2xl"></i>
                                                    </div>
                                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Product Data Found</h3>
                                                    <p class="text-sm text-gray-600">
                                                        No product sales data is available for the selected date range.
                                                    </p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                            // Get detailed sales data with proper date filtering
                                            $detailedQuery = "SELECT 
                                                s.created_at,
                                                s.transaction_id,
                                                s.subtotal,
                                                s.discount_amount,
                                                s.tax_amount,
                                                s.total_amount,
                                                s.payment_method,
                                                s.status
                                                FROM sales s 
                                                WHERE DATE(s.created_at) BETWEEN :start_date AND :end_date
                                                ORDER BY s.created_at DESC";
                                            
                                            $detailedStmt = $pdo->prepare($detailedQuery);
                                            $detailedStmt->execute([
                                                ':start_date' => $startDate,
                                                ':end_date' => $endDate
                                            ]);
                                            $detailedData = $detailedStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (empty($detailedData)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4 text-gray-500">No transactions found for the selected period.</td>
                                                </tr>
                                            <?php else:
                                            foreach ($detailedData as $row): 
                                                    $status_class = match($row['status']) {
                                                        'completed' => 'bg-green-100 text-green-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'cancelled' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                            ?>
                                            <tr>
                                                <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                                                <td>₱<?= number_format($row['subtotal'], 2) ?></td>
                                                <td>₱<?= number_format($row['discount_amount'], 2) ?></td>
                                                <td>₱<?= number_format($row['tax_amount'], 2) ?></td>
                                                <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                                <td><?= ucfirst(htmlspecialchars($row['payment_method'])) ?></td>
                                                <td>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $status_class ?>">
                                                        <?= ucfirst(htmlspecialchars($row['status'])) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; 
                                            endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (empty($reportData)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-12">
                                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                                        <i class="fas fa-chart-line text-gray-400 text-2xl"></i>
                                                    </div>
                                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Sales Data Found</h3>
                                                    <p class="text-sm text-gray-600">
                                                        No sales data is available for the selected date range.
                                                    </p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php
                                            // Get detailed sales data with proper date filtering
                                            $detailedQuery = "SELECT 
                                                s.created_at,
                                                s.transaction_id,
                                                s.subtotal,
                                                s.discount_amount,
                                                s.tax_amount,
                                                s.total_amount,
                                                s.payment_method,
                                                s.status
                                                FROM sales s 
                                                WHERE DATE(s.created_at) BETWEEN :start_date AND :end_date
                                                ORDER BY s.created_at DESC";
                                            
                                            $detailedStmt = $pdo->prepare($detailedQuery);
                                            $detailedStmt->execute([
                                                ':start_date' => $startDate,
                                                ':end_date' => $endDate
                                            ]);
                                            $detailedData = $detailedStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (empty($detailedData)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4 text-gray-500">No transactions found for the selected period.</td>
                                                </tr>
                                            <?php else:
                                            foreach ($detailedData as $row): 
                                                    $status_class = match($row['status']) {
                                                        'completed' => 'bg-green-100 text-green-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'cancelled' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                            ?>
                                            <tr>
                                                <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                                                <td>₱<?= number_format($row['subtotal'], 2) ?></td>
                                                <td>₱<?= number_format($row['discount_amount'], 2) ?></td>
                                                <td>₱<?= number_format($row['tax_amount'], 2) ?></td>
                                                <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                                                <td><?= ucfirst(htmlspecialchars($row['payment_method'])) ?></td>
                                                <td>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $status_class ?>">
                                                        <?= ucfirst(htmlspecialchars($row['status'])) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; 
                                            endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($detailedData)): ?>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <td colspan="2" class="font-semibold">Total</td>
                                        <td class="font-semibold">₱<?= number_format(array_sum(array_column($detailedData, 'subtotal')), 2) ?></td>
                                        <td class="font-semibold">₱<?= number_format(array_sum(array_column($detailedData, 'discount_amount')), 2) ?></td>
                                        <td class="font-semibold">₱<?= number_format(array_sum(array_column($detailedData, 'tax_amount')), 2) ?></td>
                                        <td class="font-semibold">₱<?= number_format(array_sum(array_column($detailedData, 'total_amount')), 2) ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>

                            <!-- No Results Message -->
                            <div x-show="!hasVisibleData" 
                                 x-cloak
                                 class="py-12 text-center bg-gray-50 rounded-lg border-t">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                    <i class="fas <?= $reportType === 'products' ? 'fa-box' : 'fa-chart-line' ?> text-gray-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">
                                    <?= $reportType === 'products' ? 'No Product Data Found' : 'No Sales Data Found' ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    <?= $reportType === 'products' 
                                        ? 'No product sales data is available for the selected date range.'
                                        : 'No sales data is available for the selected date range.' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart configuration
            const commonOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                        display: true,
                        position: 'top'
                        },
                        tooltip: {
                        mode: 'index',
                        intersect: false,
                            callbacks: {
                                label: function(context) {
                                let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                    label += '₱' + context.parsed.y.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                return '₱' + value.toLocaleString('en-US');
                                }
                            }
                        }
                    }
            };

            // Prepare chart data
            const labels = <?php echo json_encode($chartLabels); ?>;
            const salesData = <?php echo json_encode(array_map('floatval', $revenueData)); ?>;
            const transactionsData = <?php echo json_encode(array_map('intval', $transactionData)); ?>;

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
            new Chart(revenueCtx, {
                    type: 'line',
                data: {
                        labels: labels,
                    datasets: [{
                            label: 'Revenue',
                            data: salesData,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        fill: true
                    }]
                },
                    options: commonOptions
                });
            }

            // Transactions Chart
            const transactionsCtx = document.getElementById('transactionsChart');
            if (transactionsCtx) {
            new Chart(transactionsCtx, {
                type: 'bar',
                data: {
                        labels: labels,
                    datasets: [{
                            label: 'Transactions',
                            data: transactionsData,
                            backgroundColor: '#10b981'
                    }]
                },
                options: {
                        ...commonOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                        return value.toLocaleString('en-US');
                                }
                            }
                        }
                    }
                }
            });
            }

            <?php if ($reportType === 'sales'): ?>
            // Moving Average Chart
            const maCtx = document.getElementById('movingAverageChart');
            if (maCtx) {
                const movingAverages = salesData.map((val, idx, arr) => {
                    if (idx < 2) return null;
                    return (arr[idx] + arr[idx - 1] + arr[idx - 2]) / 3;
                });

                new Chart(maCtx, {
                    type: 'line',
                data: {
                        labels: labels,
                        datasets: [{
                            label: 'Daily Sales',
                            data: salesData,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            fill: true
                        }, {
                            label: '3-Day Moving Average',
                            data: movingAverages,
                            borderColor: '#10b981',
                            borderDash: [5, 5],
                            fill: false
                        }]
                    },
                    options: commonOptions
                });
            }

            // Forecast Chart
            const forecastCtx = document.getElementById('forecastChart');
            if (forecastCtx) {
                // Simple linear regression for forecast
                const xValues = Array.from({length: salesData.length}, (_, i) => i);
                const xMean = xValues.reduce((a, b) => a + b, 0) / xValues.length;
                const yMean = salesData.reduce((a, b) => a + b, 0) / salesData.length;
                
                const slope = xValues.reduce((sum, x, i) => {
                    return sum + (x - xMean) * (salesData[i] - yMean);
                }, 0) / xValues.reduce((sum, x) => sum + Math.pow(x - xMean, 2), 0);
                
                const intercept = yMean - slope * xMean;
                
                // Generate forecast
                const futureDays = 3;
                const forecastDates = Array.from({length: futureDays}, (_, i) => {
                    const lastDate = new Date(labels[labels.length - 1]);
                    lastDate.setDate(lastDate.getDate() + i + 1);
                    return lastDate.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
                });
                
                const forecast = Array.from({length: futureDays}, (_, i) => {
                    const x = salesData.length + i;
                    return Math.max(0, slope * x + intercept);
                });

            new Chart(forecastCtx, {
                type: 'line',
                data: {
                        labels: [...labels, ...forecastDates],
                    datasets: [{
                            label: 'Actual Sales',
                            data: [...salesData, ...Array(futureDays).fill(null)],
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        fill: true
                    }, {
                            label: 'Forecast',
                            data: [...Array(salesData.length).fill(null), ...forecast],
                        borderColor: '#ef4444',
                        borderDash: [5, 5],
                        fill: false
                    }]
                },
                    options: commonOptions
                });
            }
            <?php endif; ?>

            // Time Series Chart
            const timeSeriesCtx = document.getElementById('timeSeriesChart');
            if (timeSeriesCtx) {
                <?php if ($reportType === 'products'): ?>
                // For products, create a stacked bar chart
                const productData = <?php echo json_encode($dailyProductData); ?>;
                
                if (productData.length === 0) {
                    // Display "No data" message when there are no products
                    new Chart(timeSeriesCtx, {
                type: 'bar',
                data: {
                            labels: ['No Data'],
                    datasets: [{
                                label: 'No products found',
                                data: [0],
                                backgroundColor: '#cbd5e1'
                    }]
                },
                options: {
                            ...commonOptions,
                    plugins: {
                                ...commonOptions.plugins,
                                title: {
                                    display: true,
                                    text: 'No Product Data Available',
                                    font: { size: 16, weight: 'bold' }
                                }
                            }
                        }
                    });
                } else {
                    // Get unique dates and format them
                    const uniqueDates = [...new Set(productData.map(item => item.sale_date))].sort();
                    const formattedDates = uniqueDates.map(date => {
                        const [year, month, day] = date.split('-');
                        return new Date(year, month - 1, day).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        });
                    });

                    // Get unique products
                    const uniqueProducts = <?php echo json_encode($chartLabels); ?>;
                    
                    // Prepare datasets
                    const datasets = uniqueProducts.map(product => {
                        const data = uniqueDates.map(date => {
                            const entry = productData.find(item => item.sale_date === date && item.product_name === product);
                            return entry ? parseFloat(entry.daily_revenue) : 0;
                        });
                        return {
                            label: product,
                            data: data,
                            backgroundColor: getRandomColor(),
                            borderWidth: 1
                        };
                    });

                    new Chart(timeSeriesCtx, {
                        type: 'bar',
                        data: {
                            labels: formattedDates,
                            datasets: datasets
                        },
                        options: {
                            ...commonOptions,
                    scales: {
                                x: {
                                    stacked: true
                                },
                        y: {
                                    stacked: true,
                            beginAtZero: true,
                            ticks: {
                                        callback: value => '₱' + value.toLocaleString('en-US')
                                    }
                                }
                            },
                            plugins: {
                                ...commonOptions.plugins,
                                title: {
                                    display: true,
                                    text: 'Daily Product Sales Distribution',
                                    font: { size: 16, weight: 'bold' }
                        }
                    }
                }
            });
                }
                <?php else: ?>
                // Regular time series chart for sales
                new Chart(timeSeriesCtx, {
                    type: 'line',
                data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sales Trend',
                            data: salesData,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            fill: true
                        }]
                },
                options: {
                        ...commonOptions,
                    plugins: {
                            ...commonOptions.plugins,
                            title: {
                            display: true,
                                text: 'Sales Trend Analysis',
                                font: { size: 16, weight: 'bold' }
                            }
                        }
                    }
                });
                <?php endif; ?>
            }

            // Distribution Chart
            const distributionCtx = document.getElementById('distributionChart');
            if (distributionCtx) {
                <?php if ($reportType === 'products'): ?>
                // Product Revenue Distribution chart
                const productData = <?php echo json_encode($reportData); ?>;
                const productLabels = productData.map(item => item.product_name);
                const productRevenue = productData.map(item => parseFloat(item.total_revenue));
                const productQuantities = productData.map(item => parseInt(item.total_quantity));

                // Create the product performance chart
                new Chart(distributionCtx, {
                    type: 'bar',
                    data: {
                        labels: productLabels,
                        datasets: [{
                            label: 'Revenue',
                            data: productRevenue,
                            backgroundColor: '#4F46E5',
                            yAxisID: 'y',
                            order: 1
                        }, {
                            label: 'Quantity Sold',
                            data: productQuantities,
                            backgroundColor: '#10B981',
                            yAxisID: 'y1',
                            type: 'line',
                            order: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue (₱)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '₱' + value.toLocaleString();
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Quantity Sold'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Product Performance Analysis',
                                font: { size: 16, weight: 'bold' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.dataset.label || '';
                                        const value = context.parsed.y;
                                        if (label === 'Revenue') {
                                            return label + ': ₱' + value.toLocaleString();
                                        }
                                        return label + ': ' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
                <?php else: ?>
                // Regular distribution chart for sales
                const dailyStats = calculateDailyStats(salesData);
                new Chart(distributionCtx, {
                    type: 'bar',
                data: {
                        labels: labels,
                    datasets: [{
                            label: 'Daily Sales',
                            data: salesData,
                            backgroundColor: '#10b981',
                            borderRadius: 4
                    }, {
                            label: 'Average',
                            data: Array(labels.length).fill(dailyStats.average),
                            type: 'line',
                            borderColor: '#ef4444',
                        borderDash: [5, 5],
                        fill: false
                    }]
                },
                options: {
                        ...commonOptions,
                    plugins: {
                            ...commonOptions.plugins,
                            title: {
                                display: true,
                                text: 'Daily Sales Distribution',
                                font: { size: 16, weight: 'bold' }
                            }
                        }
                    }
                });
                <?php endif; ?>
            }

            // Helper function to calculate daily statistics
            function calculateDailyStats(data) {
                const validData = data.filter(val => val !== null && !isNaN(val));
                const sum = validData.reduce((a, b) => a + b, 0);
                const average = sum / validData.length;
                const sorted = [...validData].sort((a, b) => a - b);
                const median = sorted.length % 2 === 0 
                    ? (sorted[sorted.length/2 - 1] + sorted[sorted.length/2]) / 2
                    : sorted[Math.floor(sorted.length/2)];
                
                return {
                    average,
                    median,
                    min: Math.min(...validData),
                    max: Math.max(...validData)
                };
            }

            // Helper function to generate random colors
            function getRandomColor() {
                const letters = '0123456789ABCDEF';
                let color = '#';
                for (let i = 0; i < 6; i++) {
                    color += letters[Math.floor(Math.random() * 16)];
                }
                return color;
            }
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
            
            // Set initial constraints
            modalStartDate.max = todayStr;
            modalEndDate.max = todayStr;
            
            // Add event listener for start date changes
            modalStartDate.addEventListener('change', function() {
                // Update end date minimum when start date changes
                modalEndDate.min = this.value;
                
                // If end date is now less than start date, update it
                if (modalEndDate.value < this.value) {
                    modalEndDate.value = this.value;
                }
            });
            
            // Add event listener for end date changes
            modalEndDate.addEventListener('change', function() {
                // If end date is less than start date, show error
                if (this.value < modalStartDate.value) {
                    showDateError('End date cannot be earlier than start date');
                    this.value = modalStartDate.value;
                } else {
                    hideDateError();
                }
            });
            
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

        // Update hidden fields when custom date is applied
        function setHiddenDateFields(start, end) {
            document.getElementById('hiddenStartDate').value = start;
            document.getElementById('hiddenEndDate').value = end;
        }
        document.getElementById('customDateForm').onsubmit = function(e) {
            e.preventDefault();
            const start = document.getElementById('modalStartDate').value;
            const end = document.getElementById('modalEndDate').value;
            setHiddenDateFields(start, end);
            document.getElementById('customDateModal').style.display = 'none';
            document.getElementById('reportForm').submit();
        };

        // Export functions
        function exportToPDF() {
            window.location.href = 'get_sale_details.php?export=pdf&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&report_type=<?php echo $reportType; ?>&date_range=<?php echo $dateRange; ?>';
        }

        function exportToCSV() {
            const reportType = '<?php echo $reportType; ?>';
            let url = 'get_sale_details.php?export=csv&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&report_type=' + reportType;
            
            if (reportType === 'products') {
                // Add additional parameters for product report
                url += '&include_categories=true&include_quantities=true';
            }
            
            window.location.href = url;
        }
    </script>
</body>
</html> 