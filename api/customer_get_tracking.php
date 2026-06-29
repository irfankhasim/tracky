<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$order_id = (int) ($_GET['order_id'] ?? 0);
$order_no = trim($_GET['order_no'] ?? '');

if ($order_id < 1 && $order_no === '') {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

if ($order_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
} else {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE order_no = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $order_no);
}
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$oid = (int) $order['id'];
$order['items'] = getOrderItems($conn, $oid);

$dstmt = mysqli_prepare(
    $conn,
    "SELECT d.*, u.name AS runner_name, r.phone AS runner_phone, r.vehicle_no
     FROM deliveries d
     JOIN runners r ON r.id = d.runner_id
     JOIN users u ON u.id = r.user_id
     WHERE d.order_id = ?
     ORDER BY d.id DESC LIMIT 1"
);
mysqli_stmt_bind_param($dstmt, 'i', $oid);
mysqli_stmt_execute($dstmt);
$delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($dstmt));
mysqli_stmt_close($dstmt);

$logs = [];
$lstmt = mysqli_prepare($conn, 'SELECT * FROM status_logs WHERE order_id = ? ORDER BY changed_at ASC');
mysqli_stmt_bind_param($lstmt, 'i', $oid);
mysqli_stmt_execute($lstmt);
$lres = mysqli_stmt_get_result($lstmt);
while ($row = mysqli_fetch_assoc($lres)) {
    $logs[] = $row;
}
mysqli_stmt_close($lstmt);

echo json_encode([
    'success' => true,
    'order' => $order,
    'delivery' => $delivery,
    'timeline' => $logs,
]);
