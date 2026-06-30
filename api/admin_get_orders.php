<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireOpsApi();
header('Content-Type: application/json; charset=utf-8');

$rid = activeRestaurantId();
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = 'WHERE o.restaurant_id = ' . $rid;
if ($status !== '' && $status !== 'all') {
    if ($status === 'in_transit') {
        $where .= " AND o.status IN ('picked_up','in_transit')";
    } else {
        $status_esc = mysqli_real_escape_string($conn, $status);
        $where .= " AND o.status = '$status_esc'";
    }
}
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_no LIKE '%$s%' OR o.customer_name LIKE '%$s%')";
}

$count_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders o $where");
$total = (int) mysqli_fetch_assoc($count_res)['c'];

$sql = "SELECT o.*, d.runner_id, d.status AS delivery_status, u.name AS runner_name, r.phone AS runner_phone
        FROM orders o
        LEFT JOIN deliveries d ON d.order_id = o.id AND d.status != 'delivered'
        LEFT JOIN runners r ON r.id = d.runner_id
        LEFT JOIN users u ON u.id = r.user_id
        $where
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['items'] = getOrderItems($conn, (int) $row['id']);
    $row['items_count'] = count($row['items']);
    $orders[] = $row;
}

echo json_encode([
    'success' => true,
    'orders' => $orders,
    'total' => $total,
    'page' => $page,
    'pages' => max(1, (int) ceil($total / $limit)),
]);
