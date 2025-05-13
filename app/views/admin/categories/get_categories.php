<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';

try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($categories);
} catch (PDOException $e) {
    echo json_encode([]);
} 