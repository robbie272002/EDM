<?php
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/views/auth/check_session.php';

// Ensure user is authenticated
checkAuth('admin');

header('Content-Type: application/json');

$period = $_GET['period'] ?? 'month';

try {
    $query = '';
    $params = [];
    
    switch ($period) {
        case '7':
            $query = "
                SELECT 
                    DATE(created_at) as date, 
                    COALESCE(SUM(total_amount), 0) as total
                FROM sales
                WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";
            break;
            
        case '30':
            $query = "
                SELECT 
                    DATE(created_at) as date, 
                    COALESCE(SUM(total_amount), 0) as total
                FROM sales
                WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 29 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";
            break;
            
        case 'month':
            $query = "
                SELECT 
                    DATE(created_at) as date, 
                    COALESCE(SUM(total_amount), 0) as total
                FROM sales
                WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";
            break;
            
        case 'year':
            $query = "
                SELECT 
                    MONTH(created_at) as month,
                    COALESCE(SUM(total_amount), 0) as total
                FROM sales
                WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
                GROUP BY MONTH(created_at)
                ORDER BY month ASC
            ";
            break;
            
        default:
            throw new Exception('Invalid period specified');
    }
    
    $stmt = $pdo->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in missing dates with zero values
    $labels = [];
    $values = [];
    
    if ($period === 'year') {
        // Fill all months
        for ($i = 1; $i <= 12; $i++) {
            $monthName = date('F', mktime(0, 0, 0, $i, 1));
            $labels[] = $monthName;
            $found = false;
            foreach ($results as $row) {
                if ((int)$row['month'] === $i) {
                    $values[] = (float)$row['total'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $values[] = 0;
            }
        }
    } else {
        // Calculate date range
        $startDate = new DateTime();
        $endDate = new DateTime();
        
        switch ($period) {
            case '7':
                $startDate->modify('-6 days');
                break;
            case '30':
                $startDate->modify('-29 days');
                break;
            case 'month':
                $startDate->modify('first day of this month');
                $endDate->modify('last day of this month');
                break;
        }
        
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
        
        foreach ($dateRange as $date) {
            $labels[] = $date->format('M d');
            $dateStr = $date->format('Y-m-d');
            $found = false;
            foreach ($results as $row) {
                if ($row['date'] === $dateStr) {
                    $values[] = (float)$row['total'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $values[] = 0;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'values' => $values
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching sales data: ' . $e->getMessage()
    ]);
} 