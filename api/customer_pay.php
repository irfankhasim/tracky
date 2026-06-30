<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

/*
 * Simulated payment gateway endpoint ("TrackyPay").
 * This does NOT contact any real bank/payment provider. It validates the order,
 * simulates a successful charge, and marks the order as paid with a fake reference.
 */

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$order_no = trim($input['order_no'] ?? '');
$method   = in_array($input['method'] ?? '', ['fpx', 'card'], true) ? $input['method'] : 'fpx';

if ($order_no === '') {
    echo json_encode(['success' => false, 'message' => 'Pesanan tidak sah']);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, order_no, total_amount, payment_method, payment_status, restaurant_id FROM orders WHERE order_no = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $order_no);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Pesanan tidak dijumpai']);
    exit;
}
if ($order['payment_method'] !== 'online') {
    echo json_encode(['success' => false, 'message' => 'Pesanan ini bukan pembayaran online']);
    exit;
}
if ($order['payment_status'] === 'paid') {
    echo json_encode(['success' => true, 'already_paid' => true, 'message' => 'Pesanan telah dibayar']);
    exit;
}

// Simulate a transaction reference (e.g. TPY-20260629-7F3A21).
$ref = 'TPY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$oid = (int) $order['id'];

$u = mysqli_prepare($conn, "UPDATE orders SET payment_status = 'paid', payment_ref = ?, paid_at = NOW() WHERE id = ?");
mysqli_stmt_bind_param($u, 'si', $ref, $oid);
$ok = mysqli_stmt_execute($u);
mysqli_stmt_close($u);

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Pembayaran gagal diproses. Sila cuba lagi.']);
    exit;
}

$rid = $order['restaurant_id'] !== null ? (int) $order['restaurant_id'] : null;
addNotification(
    $conn,
    'Pembayaran Diterima',
    "Order {$order['order_no']} telah dibayar (RM " . number_format((float) $order['total_amount'], 2) . ") melalui " . strtoupper($method) . ".",
    'order',
    $rid
);

echo json_encode([
    'success'     => true,
    'reference'   => $ref,
    'method'      => $method,
    'amount'      => number_format((float) $order['total_amount'], 2, '.', ''),
    'redirect'    => '/tracky/customer/customer_order_success.php?order_no=' . urlencode($order['order_no']),
    'message'     => 'Pembayaran berjaya',
]);
