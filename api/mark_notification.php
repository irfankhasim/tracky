<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $input['action'] ?? '';
$notification_id = (int) ($input['notification_id'] ?? 0);

if ($action === 'mark_all') {
    mysqli_query($conn, 'UPDATE notifications SET is_read = 1');
    echo json_encode(['success' => true]);
    exit;
}

if ($notification_id > 0) {
    $stmt = mysqli_prepare($conn, 'UPDATE notifications SET is_read = 1 WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $notification_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
