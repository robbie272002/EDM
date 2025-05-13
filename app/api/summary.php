<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Enable CORS for API endpoints
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Check if user is logged in
requireLogin();

// Get parameters
$dateRange = $_GET['date_range'] ?? 'month';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Debug log
error_log("Original parameters - Date Range: $dateRange, Start: $startDate, End: $endDate");

// Handle predefined date ranges
switch ($dateRange) {
    case 'today':
        $startDate = date('Y-m-d 00:00:00');
        $endDate = date('Y-m-d 23:59:59');
        break;
    case 'yesterday':
        $startDate = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $endDate = date('Y-m-d 23:59:59', strtotime('-1 day'));
        break;
    case 'week':
        // Get the start of current week (Monday)
        $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
        // End of week (Sunday)
        $endDate = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        break;
    case 'month':
        // First day of current month
        $startDate = date('Y-m-01 00:00:00');
        // Last day of current month
        $endDate = date('Y-m-t 23:59:59');
        break;
    case 'quarter':
        $currentQuarter = ceil(date('n') / 3);
        $startMonth = (($currentQuarter - 1) * 3) + 1;
        // First day of quarter
        $startDate = date('Y-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . '-01 00:00:00');
        // Last day of quarter
        $endMonth = $startMonth + 2;
        $endDate = date('Y-' . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime($startDate)) . ' 23:59:59');
        break;
    case 'year':
        // First day of current year
        $startDate = date('Y-01-01 00:00:00');
        // Last day of current year
        $endDate = date('Y-12-31 23:59:59');
        break;
    case 'season':
        $month = date('n');
        if ($month >= 3 && $month <= 5) {
            $startDate = date('Y-03-01');
            $endDate = date('Y-05-31');
        } elseif ($month >= 6 && $month <= 8) {
            $startDate = date('Y-06-01');
            $endDate = date('Y-08-31');
        } elseif ($month >= 9 && $month <= 11) {
            $startDate = date('Y-09-01');
            $endDate = date('Y-11-30');
        } else {
            $startDate = date('Y-12-01');
            $endDate = date('Y-02-28');
        }
        break;
    case 'custom':
        // Add time components to custom dates
        $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
        break;
    default:
        // Default to current month
        $startDate = date('Y-m-01 00:00:00');
        $endDate = date('Y-m-t 23:59:59');
        break;
}

// Debug log after date calculations
error_log("After switch - Start: $startDate, End: $endDate");

// Calculate previous period
$periodLength = strtotime($endDate) - strtotime($startDate);
$prevStartDate = date('Y-m-d H:i:s', strtotime($startDate) - $periodLength - 1);
$prevEndDate = date('Y-m-d H:i:s', strtotime($startDate) - 1);

// Debug log for previous period
error_log("Previous period - Start: $prevStartDate, End: $prevEndDate");

// Get current period totals
$query = "SELECT 
            COALESCE(SUM(subtotal), 0) as total_sales,
            COALESCE(SUM(discount_amount), 0) as total_discount,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(DISTINCT id) as total_transactions,
            MIN(created_at) as earliest_date,
            MAX(created_at) as latest_date,
            COUNT(*) as total_records
          FROM sales 
          WHERE created_at >= ? AND created_at <= ?
          AND status = 'completed'";

// Debug info
error_log("Date Range Selected: " . $dateRange);
error_log("Current Period - Start: " . $startDate . ", End: " . $endDate);
error_log("Previous Period - Start: " . $prevStartDate . ", End: " . $prevEndDate);

$stmt = $pdo->prepare($query);
$stmt->execute([$startDate, $endDate]);
$currentPeriod = $stmt->fetch(PDO::FETCH_ASSOC);

// Get previous period totals
$stmt = $pdo->prepare($query);
$stmt->execute([$prevStartDate, $prevEndDate]);
$prevPeriod = $stmt->fetch(PDO::FETCH_ASSOC);

// Add debug information to response
$response = [
    'total_sales' => floatval($currentPeriod['total_sales']),
    'total_discount' => floatval($currentPeriod['total_discount']),
    'total_revenue' => floatval($currentPeriod['total_revenue']),
    'total_transactions' => intval($currentPeriod['total_transactions']),
    'debug_info' => [
        'date_range' => $dateRange,
        'current_period' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'earliest_record' => $currentPeriod['earliest_date'],
            'latest_record' => $currentPeriod['latest_date'],
            'total_records' => $currentPeriod['total_records']
        ],
        'previous_period' => [
            'start_date' => $prevStartDate,
            'end_date' => $prevEndDate,
            'earliest_record' => $prevPeriod['earliest_date'],
            'latest_record' => $prevPeriod['latest_date'],
            'total_records' => $prevPeriod['total_records']
        ]
    ],
    'sales_growth' => $prevPeriod['total_sales'] > 0 
        ? (($currentPeriod['total_sales'] - $prevPeriod['total_sales']) / $prevPeriod['total_sales']) * 100 
        : 0,
    'discount_growth' => $prevPeriod['total_discount'] > 0 
        ? (($currentPeriod['total_discount'] - $prevPeriod['total_discount']) / $prevPeriod['total_discount']) * 100 
        : 0,
    'revenue_growth' => $prevPeriod['total_revenue'] > 0 
        ? (($currentPeriod['total_revenue'] - $prevPeriod['total_revenue']) / $prevPeriod['total_revenue']) * 100 
        : 0,
    'transactions_growth' => $prevPeriod['total_transactions'] > 0 
        ? (($currentPeriod['total_transactions'] - $prevPeriod['total_transactions']) / $prevPeriod['total_transactions']) * 100 
        : 0
];

// Log the final response with all debug info
error_log("Final Response with Debug Info: " . json_encode($response));

echo json_encode($response); 