<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log incoming request
$rawInput = file_get_contents('php://input');
error_log("Received transaction request: " . $rawInput);

// Get JSON input
$data = json_decode($rawInput, true);

if (!$data) {
    error_log("Invalid JSON input received. JSON error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input: ' . json_last_error_msg()]);
    exit;
}

// Log decoded data
error_log("Decoded transaction data: " . print_r($data, true));

// Validate required fields
$requiredFields = ['cart', 'paymentMethod', 'cashier', 'transactionId', 'total', 'tax'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        error_log("Missing required field: $field");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $pdo->beginTransaction();
    error_log("Started database transaction");

    // Get user_id from cashier name
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE name = ?");
    $userStmt->execute([$data['cashier']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("Cashier not found: " . $data['cashier']);
        throw new Exception('Cashier not found: ' . $data['cashier']);
    }
    $user_id = $user['id'];
    error_log("Found user_id: " . $user_id);

    // Insert into sales table
    $saleStmt = $pdo->prepare("INSERT INTO sales 
        (transaction_id, user_id, total_amount, subtotal, discount_amount, tax_amount, payment_method) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $saleParams = [
        $data['transactionId'],
        $user_id,
        $data['total'],
        $data['subtotal'],
        $data['discount']['amount'],
        $data['tax'],
        $data['paymentMethod']
    ];
    error_log("Executing sales insert with params: " . print_r($saleParams, true));
    
    $saleStmt->execute($saleParams);
    $sale_id = $pdo->lastInsertId();
    error_log("Created sale with ID: " . $sale_id);

    // Log the sale activity
    logSaleActivity(
        $user_id,
        'create_sale',
        $sale_id,
        "Created new sale transaction: {$data['transactionId']}",
        null,
        [
            'transaction_id' => $data['transactionId'],
            'total_amount' => $data['total'],
            'subtotal' => $data['subtotal'],
            'discount_amount' => $data['discount']['amount'],
            'tax_amount' => $data['tax'],
            'payment_method' => $data['paymentMethod'],
            'items' => $data['cart']
        ]
    );

    // Prepare statements for sale_items and stock update
    $itemStmt = $pdo->prepare("INSERT INTO sale_items 
        (sale_id, product_id, quantity, price) 
        VALUES (?, ?, ?, ?)");
    $prodStmt = $pdo->prepare("SELECT id, stock FROM products WHERE sku = ?");
    $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

    foreach ($data['cart'] as $item) {
        // Validate item data
        if (!isset($item['sku']) || !isset($item['quantity']) || !isset($item['price'])) {
            error_log("Invalid item data: " . json_encode($item));
            throw new Exception('Invalid item data: ' . json_encode($item));
        }

        // Get product_id
        $prodStmt->execute([$item['sku']]);
        $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            error_log("Product not found: " . $item['sku']);
            throw new Exception('Product not found: ' . $item['sku']);
        }

        // Check stock
        if ($product['stock'] < $item['quantity']) {
            error_log("Insufficient stock for product: " . $item['sku'] . " (Requested: " . $item['quantity'] . ", Available: " . $product['stock'] . ")");
            throw new Exception('Insufficient stock for product: ' . $item['sku']);
        }

        // Insert sale item
        $itemStmt->execute([
            $sale_id,
            $product['id'],
            $item['quantity'],
            $item['price']
        ]);
        error_log("Added sale item: " . json_encode($item));

        // Update stock
        $stockStmt->execute([
            $item['quantity'],
            $product['id']
        ]);
        error_log("Updated stock for product: " . $item['sku']);

        // Log stock adjustment
        logStockActivity(
            $user_id,
            'remove_stock',
            $product['id'],
            "Removed {$item['quantity']} units of stock for sale",
            ['stock' => $product['stock']],
            ['stock' => $product['stock'] - $item['quantity']]
        );
    }

    $pdo->commit();
    error_log("Transaction completed successfully");
    echo json_encode([
        'success' => true,
        'message' => 'Transaction recorded successfully',
        'sale_id' => $sale_id
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Transaction failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 