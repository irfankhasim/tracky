<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');

$rid = activeRestaurantId();
$count_only = isset($_GET['count_only']) && $_GET['count_only'] == '1';
$limit = $count_only ? 0 : 50;

$count_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0 AND restaurant_id = $rid");
$unread = $count_res ? (int) mysqli_fetch_assoc($count_res)['c'] : 0;

if ($count_only) {
    echo json_encode(['success' => true, 'unread_count' => $unread, 'count' => $unread]);
    exit;
}

$result = mysqli_query($conn, "SELECT * FROM notifications WHERE restaurant_id = $rid ORDER BY created_at DESC LIMIT $limit");
$list = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
}

echo json_encode(['success' => true, 'unread_count' => $unread, 'notifications' => $list]);
