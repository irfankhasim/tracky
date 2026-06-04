<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');

$today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at) = CURDATE()"));
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'"));
$transit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status IN ('picked_up','in_transit')"));
$delivered = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'delivered' AND DATE(created_at) = CURDATE()"));

$recent = [];
$res = mysqli_query($conn, "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count FROM orders o ORDER BY o.created_at DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($res)) {
    $recent[] = [
        'id' => (int) $row['id'],
        'order_no' => $row['order_no'],
        'customer_name' => $row['customer_name'],
        'item_count' => (int) $row['item_count'],
        'total_amount' => (float) $row['total_amount'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'time_ago' => timeAgo($row['created_at']),
    ];
}

echo json_encode([
    'success' => true,
    'stats' => [
        'today' => (int) ($today['c'] ?? 0),
        'pending' => (int) ($pending['c'] ?? 0),
        'transit' => (int) ($transit['c'] ?? 0),
        'delivered' => (int) ($delivered['c'] ?? 0),
    ],
    'recent' => $recent,
]);
