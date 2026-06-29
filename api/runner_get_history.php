<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRunnerApi();
header('Content-Type: application/json; charset=utf-8');

$runner_id = (int) ($_SESSION['runner_id'] ?? 0);
if ($runner_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Runner tidak sah', 'history' => []]);
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$date_filter = match ($filter) {
    'today' => 'AND DATE(d.delivered_at) = CURDATE()',
    'week' => 'AND YEARWEEK(d.delivered_at, 1) = YEARWEEK(CURDATE(), 1)',
    'month' => 'AND MONTH(d.delivered_at) = MONTH(CURDATE()) AND YEAR(d.delivered_at) = YEAR(CURDATE())',
    default => '',
};

$sql = "SELECT
        o.order_no,
        o.customer_name,
        o.customer_phone,
        o.delivery_address,
        o.total_amount,
        o.payment_method,
        o.notes,
        d.id AS delivery_id,
        d.assigned_at,
        d.picked_up_at,
        d.delivered_at,
        d.status,
        TIMESTAMPDIFF(MINUTE, d.assigned_at, d.delivered_at) AS duration_minutes,
        GROUP_CONCAT(CONCAT(oi.item_name, ' x', oi.quantity) SEPARATOR ', ') AS items_list
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE d.runner_id = ?
    AND d.status = 'delivered'
    $date_filter
    GROUP BY d.id, o.order_no, o.customer_name, o.customer_phone, o.delivery_address,
             o.total_amount, o.payment_method, o.notes, d.assigned_at, d.picked_up_at,
             d.delivered_at, d.status
    ORDER BY d.delivered_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $runner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$history = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['delivery_id'] = (int) $row['delivery_id'];
    $row['total_amount'] = (float) $row['total_amount'];
    $row['duration_minutes'] = $row['duration_minutes'] !== null ? (int) $row['duration_minutes'] : null;
    $history[] = $row;
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'history' => $history]);
