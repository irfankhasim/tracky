<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');

$activeSql = "(SELECT COUNT(*) FROM deliveries d WHERE d.runner_id = r.id AND d.status IN ('assigned','picked_up','in_transit'))";

$sql = "SELECT r.id, r.vehicle_no, r.status, r.phone,
        u.name, u.phone AS user_phone, u.is_active,
        $activeSql AS active_deliveries,
        (SELECT COUNT(*) FROM deliveries d WHERE d.runner_id = r.id AND DATE(d.delivered_at) = CURDATE() AND d.status = 'delivered') AS deliveries_today,
        (SELECT o.order_no FROM deliveries d JOIN orders o ON o.id = d.order_id
         WHERE d.runner_id = r.id AND d.status IN ('assigned','picked_up','in_transit')
         ORDER BY d.assigned_at DESC LIMIT 1) AS current_order_no
        FROM runners r
        JOIN users u ON u.id = r.user_id
        WHERE u.is_active = 1
        ORDER BY u.name ASC";

$result = mysqli_query($conn, $sql);
$available = [];
$busy = [];
$offline = [];
$seen = [];

while ($row = mysqli_fetch_assoc($result)) {
    $rid = (int) $row['id'];
    if (isset($seen[$rid])) {
        continue;
    }
    $seen[$rid] = true;
    $active = (int) $row['active_deliveries'];
    $row['id'] = (int) $row['id'];
    $row['deliveries_today'] = (int) $row['deliveries_today'];
    $row['active_deliveries'] = $active;

    if ($active > 0) {
        $busy[] = $row;
    } elseif ($row['status'] === 'online') {
        $available[] = $row;
    } elseif ($row['status'] === 'offline') {
        $offline[] = $row;
    } else {
        $busy[] = $row;
    }
}

usort($available, fn($a, $b) => strcasecmp($a['name'], $b['name']));

echo json_encode([
    'success' => true,
    'available' => $available,
    'busy' => $busy,
    'offline' => $offline,
    'runners' => $available,
]);
