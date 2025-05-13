<?php
require_once __DIR__ . '/../app/views/auth/check_session.php';
$user = checkAuth();

require_once __DIR__ . '/../app/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert sale
        $stmt = $pdo->prepare("
            INSERT INTO sales (transaction_id, user_id, total_amount, tax_amount, payment_method)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['transaction_id'],
            $user['user_id'],
            $data['total_amount'],
            $data['tax_amount'],
            $data['payment_method']
        ]);
        
        $saleId = $pdo->lastInsertId();
        
        // Insert sale items
        $stmt = $pdo->prepare("
            INSERT INTO sale_items (sale_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($data['items'] as $item) {
            $stmt->execute([
                $saleId,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);
            
            // Update product stock
            $pdo->prepare("
                UPDATE products 
                SET stock = stock - ? 
                WHERE id = ?
            ")->execute([$item['quantity'], $item['product_id']]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'sale_id' => $saleId,
            'message' => 'Sale completed successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error processing sale: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 