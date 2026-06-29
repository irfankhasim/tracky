<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRunnerApi();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$delivery_id = (int) ($input['delivery_id'] ?? 0);
$new_status = trim($input['new_status'] ?? '');
$runner_id = (int) ($_SESSION['runner_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

$allowed = ['picked_up', 'in_transit', 'delivered'];
if ($delivery_id < 1 || !in_array($new_status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak sah']);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT d.*, o.order_no, o.status AS order_status FROM deliveries d JOIN orders o ON o.id = d.order_id WHERE d.id = ? AND d.runner_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $delivery_id, $runner_id);
mysqli_stmt_execute($stmt);
$delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$delivery) {
    echo json_encode(['success' => false, 'message' => 'Delivery tidak dijumpai']);
    exit;
}

$flow = ['assigned' => 'picked_up', 'picked_up' => 'in_transit', 'in_transit' => 'delivered'];
if (($flow[$delivery['status']] ?? '') !== $new_status) {
    echo json_encode(['success' => false, 'message' => 'Status tidak sah']);
    exit;
}

$order_id = (int) $delivery['order_id'];
$old_order_status = $delivery['order_status'];
$new_order_status = mapDeliveryToOrderStatus($new_status);

mysqli_begin_transaction($conn);
try {
    if ($new_status === 'picked_up') {
        $u = mysqli_prepare($conn, "UPDATE deliveries SET status = 'picked_up', picked_up_at = NOW() WHERE id = ?");
    } elseif ($new_status === 'in_transit') {
        $u = mysqli_prepare($conn, "UPDATE deliveries SET status = 'in_transit' WHERE id = ?");
    } else {
        $u = mysqli_prepare($conn, "UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ?");
    }
    mysqli_stmt_bind_param($u, 'i', $delivery_id);
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);

    $ou = mysqli_prepare($conn, 'UPDATE orders SET status = ? WHERE id = ?');
    mysqli_stmt_bind_param($ou, 'si', $new_order_status, $order_id);
    mysqli_stmt_execute($ou);
    mysqli_stmt_close($ou);

    logStatusChange($conn, $order_id, $old_order_status, $new_order_status, $user_id);

    if ($new_status === 'delivered') {
        $astmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM deliveries WHERE runner_id = ? AND status IN ('assigned','picked_up','in_transit') AND id != ?");
        mysqli_stmt_bind_param($astmt, 'ii', $runner_id, $delivery_id);
        mysqli_stmt_execute($astmt);
        $active = mysqli_fetch_assoc(mysqli_stmt_get_result($astmt));
        mysqli_stmt_close($astmt);
        $runner_status = ((int) ($active['c'] ?? 0) > 0) ? 'busy' : 'online';

        $ru = mysqli_prepare($conn, 'UPDATE runners SET status = ? WHERE id = ?');
        mysqli_stmt_bind_param($ru, 'si', $runner_status, $runner_id);
        mysqli_stmt_execute($ru);
        mysqli_stmt_close($ru);
        $_SESSION['runner_status'] = $runner_status;
        addNotification($conn, 'Order Delivered', "Order {$delivery['order_no']} telah dihantar.", 'delivery');
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'new_status' => $new_order_status, 'message' => 'Status dikemaskini']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Kemaskini gagal']);
}
