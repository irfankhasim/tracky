<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireOpsApi();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$order_id = (int) ($input['order_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$rid = activeRestaurantId();

if ($order_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Order tidak sah']);
    exit;
}

$dstmt = mysqli_prepare($conn, "SELECT d.*, o.order_no, o.status AS order_status FROM deliveries d JOIN orders o ON o.id = d.order_id WHERE d.order_id = ? AND o.restaurant_id = ? AND d.status IN ('assigned','picked_up','in_transit') ORDER BY d.id DESC LIMIT 1");
mysqli_stmt_bind_param($dstmt, 'ii', $order_id, $rid);
mysqli_stmt_execute($dstmt);
$delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($dstmt));
mysqli_stmt_close($dstmt);

if (!$delivery) {
    echo json_encode(['success' => false, 'message' => 'Tiada penghantaran aktif untuk order ini']);
    exit;
}

$runner_id = (int) $delivery['runner_id'];
$delivery_id = (int) $delivery['id'];
$old_status = $delivery['order_status'];

mysqli_begin_transaction($conn);

$u1 = mysqli_prepare($conn, "UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ?");
mysqli_stmt_bind_param($u1, 'i', $delivery_id);
$ok1 = mysqli_stmt_execute($u1);
mysqli_stmt_close($u1);

$u2 = mysqli_prepare($conn, "UPDATE orders SET status = 'delivered' WHERE id = ?");
mysqli_stmt_bind_param($u2, 'i', $order_id);
$ok2 = mysqli_stmt_execute($u2);
mysqli_stmt_close($u2);

$astmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM deliveries WHERE runner_id = ? AND status IN ('assigned','picked_up','in_transit') AND id != ?");
mysqli_stmt_bind_param($astmt, 'ii', $runner_id, $delivery_id);
mysqli_stmt_execute($astmt);
$active = mysqli_fetch_assoc(mysqli_stmt_get_result($astmt));
mysqli_stmt_close($astmt);
$runner_status = ((int) ($active['c'] ?? 0) > 0) ? 'busy' : 'online';

$u3 = mysqli_prepare($conn, 'UPDATE runners SET status = ? WHERE id = ?');
mysqli_stmt_bind_param($u3, 'si', $runner_status, $runner_id);
$ok3 = mysqli_stmt_execute($u3);
mysqli_stmt_close($u3);

if (!$ok1 || !$ok2 || !$ok3) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal mark delivered']);
    exit;
}

logStatusChange($conn, $order_id, $old_status, 'delivered', $user_id);
addNotification($conn, 'Order Delivered', "Order {$delivery['order_no']} ditandakan selesai oleh admin.", 'delivery', $rid);
mysqli_commit($conn);

echo json_encode(['success' => true, 'message' => 'Order ditandakan sebagai dihantar']);
