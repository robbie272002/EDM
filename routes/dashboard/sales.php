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
                SELECT DATE(created_at) as date, SUM(total_amount) as total
                FROM sales
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            break;
            
        case '30':
            $query = "
                SELECT DATE(created_at) as date, SUM(total_amount) as total
                FROM sales
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            break;
            
        case 'month':
            $query = "
                SELECT DATE(created_at) as date, SUM(total_amount) as total
                FROM sales
                WHERE YEAR(created_at) = YEAR(CURDATE())
                AND MONTH(created_at) = MONTH(CURDATE())
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            break;
            
        case 'year':
            $query = "
                SELECT MONTH(created_at) as month, SUM(total_amount) as total
                FROM sales
                WHERE YEAR(created_at) = YEAR(CURDATE())
                GROUP BY MONTH(created_at)
                ORDER BY month
            ";
            break;
            
        default:
            throw new Exception('Invalid period specified');
    }
    
    $stmt = $pdo->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $values = [];
    
    foreach ($results as $row) {
        if ($period === 'year') {
            $labels[] = date('F', mktime(0, 0, 0, $row['month'], 1));
        } else {
            $labels[] = date('M d', strtotime($row['date']));
        }
        $values[] = (float)$row['total'];
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