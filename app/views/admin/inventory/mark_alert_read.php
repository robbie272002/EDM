<?php
require_once __DIR__ . '/../../auth/check_session.php';
$user = checkAuth('admin');

require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: history.php');
    exit();
}

$alert_id = $_POST['alert_id'] ?? null;

if (!$alert_id) {
    $_SESSION['error_message'] = "Invalid alert ID";
    header('Location: history.php');
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE stock_alerts SET is_read = 1 WHERE id = ?");
    $stmt->execute([$alert_id]);
    $_SESSION['success_message'] = "Alert marked as read";
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error marking alert as read: " . $e->getMessage();
}

header('Location: history.php');
exit(); 