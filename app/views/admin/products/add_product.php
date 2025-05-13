<?php
require_once __DIR__ . '/../../../config/database.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'C:/xampp/htdocs/NEW/app/uploads/products/';
            
            // Debug information
            error_log("Upload directory: " . $upload_dir);
            error_log("File info: " . print_r($_FILES['image'], true));
            
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to create upload directory: ' . error_get_last()['message']);
                }
            }
            
            if (!is_writable($upload_dir)) {
                throw new Exception('Upload directory is not writable');
            }
            
            $file_info = pathinfo($_FILES['image']['name']);
            $extension = strtolower($file_info['extension']);
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($extension, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            }
            
            $filename = uniqid() . '.' . $extension;
            $target_path = $upload_dir . $filename;
            
            error_log("Target path: " . $target_path);
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = '/NEW/app/uploads/products/' . $filename;
                error_log("File uploaded successfully. Image path: " . $image_path);
            } else {
                $error = error_get_last();
                throw new Exception('Failed to upload image. Error: ' . ($error ? $error['message'] : 'Unknown error'));
            }
        } else if (isset($_FILES['image'])) {
            $error = $_FILES['image']['error'];
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            throw new Exception('Upload error: ' . ($error_messages[$error] ?? 'Unknown error'));
        }
        // Generate SKU (format: CAT-YYYYMMDD-XXX where XXX is a random number)
        $category_id = $_POST['category_id'];
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        $category_prefix = strtoupper(substr($category['name'], 0, 3));
        $date = date('Ymd');
        $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        $sku = $category_prefix . '-' . $date . '-' . $random;
        $stmt = $pdo->prepare("
            INSERT INTO products (name, category_id, price, stock, sku, image, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['category_id'],
            $_POST['price'],
            isset($_POST['stock']) ? (int)$_POST['stock'] : 0,
            $sku,
            $image_path
        ]);
        $product_id = $pdo->lastInsertId();
        // Log product creation activity
        session_start();
        if (isset($_SESSION['user_id'])) {
            require_once __DIR__ . '/../../../helpers/logger.php';
            logProductActivity(
                $_SESSION['user_id'],
                'create_product',
                $product_id,
                'Created product: ' . $_POST['name'],
                null,
                [
                    'name' => $_POST['name'],
                    'category_id' => $_POST['category_id'],
                    'price' => $_POST['price'],
                    'stock' => isset($_POST['stock']) ? (int)$_POST['stock'] : 0,
                    'sku' => $sku,
                    'image' => $image_path
                ]
            );
        }
        header('Location: index.php?success=Product added successfully');
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<?php if (!empty($error_message)): ?>
    <div style="color: red; font-weight: bold; padding: 1em; background: #fee; border: 1px solid #f99;">
        <?= htmlspecialchars($error_message) ?>
    </div>
<?php endif; ?> 