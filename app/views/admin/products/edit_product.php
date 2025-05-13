<?php
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = $_POST['product_id'];
        $name = $_POST['name'];
        $category_id = $_POST['category_id'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $status = $_POST['status'] ?? 'active';
        
        // Handle image upload if a new image is provided
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'C:/xampp/htdocs/NEW/app/uploads/products/';
            
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            $file_info = pathinfo($_FILES['image']['name']);
            $extension = strtolower($file_info['extension']);
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($extension, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            }
            
            $filename = uniqid() . '.' . $extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = '/NEW/app/uploads/products/' . $filename;
                
                // Delete old image if it exists
                $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $old_image = $stmt->fetchColumn();
                
                if ($old_image && file_exists('C:/xampp/htdocs/NEW' . $old_image)) {
                    unlink('C:/xampp/htdocs/NEW' . $old_image);
                }
            }
        }
        
        // Fetch old product data for logging
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $oldProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update product in database
        $sql = "UPDATE products SET name = ?, category_id = ?, price = ?, stock = ?, status = ?";
        $params = [$name, $category_id, $price, $stock, $status];
        
        if ($image_path) {
            $sql .= ", image = ?";
            $params[] = $image_path;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $product_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Log product update activity
        session_start();
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../../../helpers/logger.php';
            $actionType = 'update_product';
            $desc = 'Updated product: ' . $name;
            if ($oldProduct['status'] !== $status) {
                if ($status === 'active') {
                    $actionType = 'activate_product';
                    $desc = 'Activated product: ' . $name;
                } elseif ($status === 'inactive') {
                    $actionType = 'deactivate_product';
                    $desc = 'Deactivated product: ' . $name;
                }
            }
            logProductActivity(
                $_SESSION['user_id'],
                $actionType,
                $product_id,
                $desc,
                $oldProduct,
                [
                    'name' => $name,
                    'category_id' => $category_id,
                    'price' => $price,
                    'stock' => $stock,
                    'status' => $status,
                    'image' => $image_path ?? $oldProduct['image']
                ]
            );
        }
        
        header('Location: index.php?success=Product updated successfully');
        exit;
    } catch (Exception $e) {
        header('Location: index.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
} 