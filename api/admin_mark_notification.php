<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireOpsApi();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';
$notification_id = (int) ($input['notification_id'] ?? 0);
$rid = activeRestaurantId();

if ($action === 'mark_all') {
    $stmt = mysqli_prepare($conn, 'UPDATE notifications SET is_read = 1 WHERE restaurant_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $rid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}

if ($notification_id > 0) {
    $stmt = mysqli_prepare($conn, 'UPDATE notifications SET is_read = 1 WHERE id = ? AND restaurant_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $rid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
