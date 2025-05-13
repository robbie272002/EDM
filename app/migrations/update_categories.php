<?php
require_once __DIR__ . '/../../config/database.php';

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get all unique categories from products
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Insert each unique category
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    foreach ($categories as $category) {
        $stmt->execute([$category]);
    }

    // Update products to use category_id
    $stmt = $pdo->query("
        UPDATE products p
        JOIN categories c ON p.category = c.name
        SET p.category_id = c.id
    ");

    // Drop the old category column
    $pdo->query("ALTER TABLE products DROP COLUMN category");

    // Commit transaction
    $pdo->commit();
    echo "Migration completed successfully!\n";
} catch(PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    die("Migration failed: " . $e->getMessage() . "\n");
} 