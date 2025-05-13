<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Enable CORS for API endpoints
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Check if user is logged in
requireLogin();

// Get parameters
$reportType = $_GET['report_type'] ?? 'sales';
$dateRange = $_GET['date_range'] ?? 'month';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Handle predefined date ranges
switch ($dateRange) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
    case 'yesterday':
        $startDate = date('Y-m-d', strtotime('-1 day'));
        $endDate = date('Y-m-d', strtotime('-1 day'));
        break;
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
        // Validate custom date range
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $startDate = date('Y-m-d', strtotime($_GET['start_date']));
            $endDate = date('Y-m-d', strtotime($_GET['end_date']));
            
            // Ensure end date is not before start date
            if ($endDate < $startDate) {
                $endDate = $startDate;
            }
        }
        break;
}

$response = [];

switch ($reportType) {
    case 'products':
        // Query for product sales data
        $query = "SELECT 
                    p.name as label,
                    SUM(si.quantity) as quantity,
                    SUM(si.quantity * si.price) as total_sales
                  FROM sales s
                  JOIN sale_items si ON s.id = si.sale_id
                  JOIN products p ON si.product_id = p.id
                  WHERE s.created_at BETWEEN ? AND ?
                  AND s.status = 'completed'
                  GROUP BY p.id, p.name
                  ORDER BY total_sales DESC
                  LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['labels'] = array_column($data, 'label');
        $response['quantities'] = array_column($data, 'quantity');
        $response['sales'] = array_column($data, 'total_sales');
        break;

    case 'sales':
    default:
        // Query for daily sales data
        $query = "SELECT 
                    DATE(created_at) as date,
                    SUM(total_amount) as total_sales
                  FROM sales
                  WHERE created_at BETWEEN ? AND ?
                  AND status = 'completed'
                  GROUP BY DATE(created_at)
                  ORDER BY date ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['labels'] = array_column($data, 'date');
        $response['sales'] = array_column($data, 'total_sales');
        break;
}

echo json_encode($response); 