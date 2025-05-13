<?php
namespace app\models;

use app\core\Database;

class Product {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getTopProducts($limit = 5) {
        $query = "SELECT 
                    p.name, 
                    COUNT(si.id) as sale_count,
                    SUM(si.quantity) as total_quantity
                 FROM products p 
                 LEFT JOIN sale_items si ON p.id = si.product_id 
                 WHERE si.id IS NOT NULL
                 GROUP BY p.id, p.name 
                 ORDER BY sale_count DESC 
                 LIMIT :limit";
                 
        $this->db->query($query);
        $this->db->bind(':limit', $limit);
        
        return $this->db->resultSet();
    }
    
    public function getLowStockCount($threshold = 10) {
        $query = "SELECT COUNT(*) as count FROM products WHERE stock <= :threshold";
        $this->db->query($query);
        $this->db->bind(':threshold', $threshold);
        
        $result = $this->db->single();
        return $result->count;
    }
} 