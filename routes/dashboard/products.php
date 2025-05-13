<?php
require_once __DIR__ . '/../../../app/config/database.php';
require_once __DIR__ . '/../../../app/views/auth/check_session.php';

// Ensure user is authenticated
checkAuth('admin');

header('Content-Type: application/json');

$period = $_GET['period'] ?? 'week';

try {
    $query = '';
    
    switch ($period) {
        case 'today':
            $query = "
                SELECT p.name, SUM(si.quantity) as total_quantity
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s ON si.sale_id = s.id
                WHERE DATE(s.created_at) = CURDATE()
                GROUP BY p.id, p.name
                ORDER BY total_quantity DESC
                LIMIT 10
            ";
            break;
            
        case 'week':
            $query = "
                SELECT p.name, SUM(si.quantity) as total_quantity
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s ON si.sale_id = s.id
                WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY p.id, p.name
                ORDER BY total_quantity DESC
                LIMIT 10
            ";
            break;
            
        case 'month':
            $query = "
                SELECT p.name, SUM(si.quantity) as total_quantity
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s ON si.sale_id = s.id
                WHERE YEAR(s.created_at) = YEAR(CURDATE())
                AND MONTH(s.created_at) = MONTH(CURDATE())
                GROUP BY p.id, p.name
                ORDER BY total_quantity DESC
                LIMIT 10
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
        $labels[] = $row['name'];
        $values[] = (int)$row['total_quantity'];
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
        'message' => 'Error fetching products data: ' . $e->getMessage()
    ]);
} 