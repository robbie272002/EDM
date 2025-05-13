<?php
require_once __DIR__ . '/../app/views/auth/check_session.php';
$user = checkAuth();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

header('Content-Type: application/json');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $category = $_GET['category'] ?? null;
            $search = $_GET['search'] ?? null;
            
            $query = "SELECT * FROM products WHERE 1=1";
            $params = [];
            
            if ($category && $category !== 'All Items') {
                $query .= " AND category = ?";
                $params[] = $category;
            }
            
            if ($search) {
                $query .= " AND (name LIKE ? OR sku LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $query .= " ORDER BY name";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'products' => $products]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching products: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'POST':
        if ($user['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid request data']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (sku, name, description, price, stock, category)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['sku'],
                $data['name'],
                $data['description'],
                $data['price'],
                $data['stock'],
                $data['category']
            ]);
            
            $productId = $pdo->lastInsertId();
            
            // Log the activity
            logProductActivity(
                $user['id'],
                'create_product',
                $productId,
                "Created new product: {$data['name']}",
                null,
                [
                    'sku' => $data['sku'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'stock' => $data['stock'],
                    'category' => $data['category']
                ]
            );
            
            echo json_encode([
                'success' => true,
                'product_id' => $productId,
                'message' => 'Product added successfully'
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error adding product: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'PUT':
        if ($user['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid request data']);
            exit;
        }
        
        try {
            // Get old values for logging
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$data['id']]);
            $oldValues = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, stock = ?, category = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['price'],
                $data['stock'],
                $data['category'],
                $data['id']
            ]);
            
            // Log the activity
            logProductActivity(
                $user['id'],
                'update_product',
                $data['id'],
                "Updated product: {$data['name']}",
                $oldValues,
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'stock' => $data['stock'],
                    'category' => $data['category']
                ]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Product updated successfully'
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error updating product: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        if ($user['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid request data']);
            exit;
        }
        
        try {
            // Get product info for logging
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$data['id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            // Log the activity
            logProductActivity(
                $user['id'],
                'delete_product',
                $data['id'],
                "Deleted product: {$product['name']}",
                $product,
                null
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting product: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 