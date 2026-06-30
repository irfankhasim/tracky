<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireOpsApi();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$order_id = (int) ($input['order_id'] ?? 0);
$runner_id = (int) ($input['runner_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$rid = activeRestaurantId();

if ($order_id < 1 || $runner_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Data tidak sah']);
    exit;
}

$ostmt = mysqli_prepare($conn, "SELECT id, order_no, status FROM orders WHERE id = ? AND restaurant_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($ostmt, 'ii', $order_id, $rid);
mysqli_stmt_execute($ostmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($ostmt));
mysqli_stmt_close($ostmt);
if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order tidak dijumpai atau bukan pending']);
    exit;
}

// Runner must belong to the same restaurant as the order.
$rcheck = mysqli_prepare($conn, 'SELECT id FROM runners WHERE id = ? AND restaurant_id = ? LIMIT 1');
mysqli_stmt_bind_param($rcheck, 'ii', $runner_id, $rid);
mysqli_stmt_execute($rcheck);
$rsame = mysqli_fetch_assoc(mysqli_stmt_get_result($rcheck));
mysqli_stmt_close($rcheck);
if (!$rsame) {
    echo json_encode(['success' => false, 'message' => 'Runner bukan dari restoran ini']);
    exit;
}

if (!isRunnerAvailable($conn, $runner_id)) {
    echo json_encode(['success' => false, 'message' => 'Runner sedang busy']);
    exit;
}

$exist = mysqli_prepare($conn, 'SELECT id FROM deliveries WHERE order_id = ? LIMIT 1');
mysqli_stmt_bind_param($exist, 'i', $order_id);
mysqli_stmt_execute($exist);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($exist));
mysqli_stmt_close($exist);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Order sudah di-assign']);
    exit;
}

$rstmt = mysqli_prepare($conn, "SELECT id FROM runners WHERE id = ? AND status = 'online'");
mysqli_stmt_bind_param($rstmt, 'i', $runner_id);
mysqli_stmt_execute($rstmt);
$runner = mysqli_fetch_assoc(mysqli_stmt_get_result($rstmt));
mysqli_stmt_close($rstmt);
if (!$runner) {
    $cstmt = mysqli_prepare($conn, 'SELECT status FROM runners WHERE id = ?');
    mysqli_stmt_bind_param($cstmt, 'i', $runner_id);
    mysqli_stmt_execute($cstmt);
    $check = mysqli_fetch_assoc(mysqli_stmt_get_result($cstmt));
    mysqli_stmt_close($cstmt);
    $msg = ($check['status'] ?? '') === 'offline'
        ? 'Runner sedang offline — tidak boleh di-assign'
        : 'Runner tidak tersedia';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

mysqli_begin_transaction($conn);
try {
    $dstmt = mysqli_prepare($conn, "INSERT INTO deliveries (order_id, runner_id, assigned_by, status) VALUES (?, ?, ?, 'assigned')");
    mysqli_stmt_bind_param($dstmt, 'iii', $order_id, $runner_id, $user_id);
    mysqli_stmt_execute($dstmt);
    $delivery_id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($dstmt);

    $ustmt = mysqli_prepare($conn, "UPDATE orders SET status = 'assigned' WHERE id = ?");
    mysqli_stmt_bind_param($ustmt, 'i', $order_id);
    mysqli_stmt_execute($ustmt);
    mysqli_stmt_close($ustmt);

    $bstmt = mysqli_prepare($conn, "UPDATE runners SET status = 'busy' WHERE id = ?");
    mysqli_stmt_bind_param($bstmt, 'i', $runner_id);
    mysqli_stmt_execute($bstmt);
    mysqli_stmt_close($bstmt);

    logStatusChange($conn, $order_id, 'pending', 'assigned', $user_id);
    addNotification($conn, 'Runner Assigned', "Order {$order['order_no']} telah di-assign.", 'delivery', $rid);

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'delivery_id' => $delivery_id, 'message' => 'Runner berjaya di-assign']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Assign gagal']);
}
