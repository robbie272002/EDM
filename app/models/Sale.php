<?php
namespace app\models;

use PDO;

class Sale {
    private $db;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    public function getTodaySales() {
        $query = "
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM sales 
            WHERE DATE(created_at) = CURDATE()
        ";
        return $this->db->query($query)->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    public function getTodayTransactions() {
        $query = "
            SELECT COUNT(*) as count 
            FROM sales 
            WHERE DATE(created_at) = CURDATE()
        ";
        return $this->db->query($query)->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    public function getRecentTransactions($limit = 10) {
        $query = "
            SELECT s.*, u.name as cashier_name, 
                   (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
            FROM sales s
            JOIN users u ON s.user_id = u.id
            ORDER BY s.created_at DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSalesData($period) {
        $query = '';
        
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
        }
        
        $results = $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
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
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }
    
    public function store($data) {
        try {
            $this->db->beginTransaction();
            
            // Insert sale
            $query = "
                INSERT INTO sales (transaction_id, user_id, total_amount, tax_amount, payment_method)
                VALUES (:transaction_id, :user_id, :total_amount, :tax_amount, :payment_method)
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':transaction_id' => $data['transaction_id'],
                ':user_id' => $data['user_id'],
                ':total_amount' => $data['total_amount'],
                ':tax_amount' => $data['tax_amount'],
                ':payment_method' => $data['payment_method']
            ]);
            
            $saleId = $this->db->lastInsertId();
            
            // Insert sale items
            foreach ($data['items'] as $item) {
                $query = "
                    INSERT INTO sale_items (sale_id, product_id, quantity, price)
                    VALUES (:sale_id, :product_id, :quantity, :price)
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':sale_id' => $saleId,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price']
                ]);
                
                // Update product stock
                $query = "
                    UPDATE products 
                    SET stock = stock - :quantity 
                    WHERE id = :product_id
                ";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $item['product_id']
                ]);
            }
            
            $this->db->commit();
            return $saleId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
} 